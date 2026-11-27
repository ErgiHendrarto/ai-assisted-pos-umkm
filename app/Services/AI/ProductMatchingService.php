<?php

namespace App\Services\AI;

use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Models\AIProcessingRun;
use App\Models\OrderInputItem;
use App\Models\Product;
use Illuminate\Support\Collection;

class ProductMatchingService
{
    /**
     * Match extracted AI items against official active products in the database.
     *
     * @return Collection<int, OrderInputItem>
     */
    public function matchRunItems(AIProcessingRun $run): Collection
    {
        $run->items()->delete();

        $products = Product::query()->active()->get();
        $extractedItems = $run->structured_output['items'] ?? [];
        $created = collect();

        foreach ($extractedItems as $item) {
            $requestedName = trim((string) ($item['product_name'] ?? ''));
            $requestedQty = (float) ($item['quantity'] ?? 1);
            $requestedUnit = $item['unit'] ?? null;

            if ($requestedName === '' || $requestedQty <= 0) {
                continue;
            }

            $ranked = $this->rankCandidates($requestedName, $products);
            $top = $ranked->first();
            $second = $ranked->skip(1)->first();

            $matchStatus = MatchStatus::Unmatched;
            $matchedProductId = null;
            $confidence = 0.0;

            if ($top && $top['score'] >= 0.72) {
                $gap = $second ? ($top['score'] - $second['score']) : 1.0;
                if ($second && $second['score'] >= 0.72 && $gap < 0.08) {
                    $matchStatus = MatchStatus::Ambiguous;
                } else {
                    $matchStatus = MatchStatus::Matched;
                }
                $matchedProductId = $top['product']->id;
                $confidence = $top['score'];
            } elseif ($top && $top['score'] >= 0.40) {
                $matchStatus = MatchStatus::Ambiguous;
                $matchedProductId = $top['product']->id;
                $confidence = $top['score'];
            }

            $record = OrderInputItem::create([
                'ai_processing_run_id' => $run->id,
                'requested_product_name' => $requestedName,
                'requested_quantity' => $requestedQty,
                'requested_unit' => $requestedUnit,
                'matched_product_id' => $matchedProductId,
                'match_confidence' => round($confidence, 4),
                'match_status' => $matchStatus,
                'confirmed_product_id' => $matchStatus === MatchStatus::Matched ? $matchedProductId : null,
                'confirmed_quantity' => $requestedQty,
                'review_status' => ReviewStatus::Pending,
            ]);

            $created->push($record);
        }

        return $created;
    }

    /**
     * Find top matching candidate products for UI selection (especially ambiguous items).
     *
     * @return Collection<int, array{product: Product, score: float}>
     */
    public function rankCandidates(string $query, ?Collection $products = null, int $limit = 5): Collection
    {
        $products ??= Product::query()->active()->get();
        $normalizedQuery = $this->normalize($query);
        $queryTokens = $this->tokens($normalizedQuery);

        return $products
            ->map(function (Product $product) use ($normalizedQuery, $queryTokens): array {
                $score = $this->computeSimilarity($normalizedQuery, $queryTokens, $product);

                return [
                    'product' => $product,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $row) => $row['score'] > 0.20)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<int, string>  $queryTokens
     */
    private function computeSimilarity(string $normalizedQuery, array $queryTokens, Product $product): float
    {
        $normalizedName = $this->normalize($product->product_name);
        $normalizedCode = $this->normalize($product->product_code);

        if ($normalizedQuery === $normalizedName || $normalizedQuery === $normalizedCode) {
            return 1.0;
        }

        $nameTokens = $this->tokens($normalizedName);
        $intersection = array_intersect($queryTokens, $nameTokens);
        $union = array_unique(array_merge($queryTokens, $nameTokens));
        $jaccard = count($union) > 0 ? count($intersection) / count($union) : 0.0;

        similar_text($normalizedQuery, $normalizedName, $percent);
        $similarScore = $percent / 100.0;

        $containsBonus = (str_contains($normalizedName, $normalizedQuery) || str_contains($normalizedQuery, $normalizedName))
            ? 0.15
            : 0.0;

        return min(1.0, round(($jaccard * 0.55) + ($similarScore * 0.45) + $containsBonus, 4));
    }

    private function normalize(string $text): string
    {
        $lower = mb_strtolower(trim($text));

        return trim((string) preg_replace('/[^a-z0-9\s]+/u', ' ', $lower));
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $normalized): array
    {
        $parts = preg_split('/\s+/', $normalized) ?: [];

        return array_values(array_filter($parts, fn (string $t) => $t !== ''));
    }
}
