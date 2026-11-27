<?php

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Models\AiSetting;
use App\Models\OrderInput;
use App\Models\OrderInputItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_voice_text_extraction_matching_and_cashier_confirmation_adds_to_cart(): void
    {
        $cashier = User::factory()->cashier()->create();

        $indomie = Product::factory()->create([
            'product_name' => 'Indomie Goreng',
            'product_code' => 'PRD-001',
            'price' => 3500,
            'stock' => 20,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        AiSetting::create([
            'provider' => 'gemini',
            'api_key' => 'AIzaFakeTestKeyForUnitTests',
            'model_name' => 'gemini-2.5-flash',
            'timeout_seconds' => 30,
            'voice_enabled' => true,
            'ocr_enabled' => true,
            'matching_enabled' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'items' => [
                                            ['product_name' => 'Indomie Goreng', 'quantity' => 3, 'unit' => 'pcs'],
                                            ['product_name' => 'Barang Tidak Ada XYZ', 'quantity' => 1, 'unit' => 'pcs'],
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Cashier submits voice/text order -> AI extracts & matches -> redirects to review screen
        $response = $this->actingAs($cashier)->post(route('pos.ai.voice.process'), [
            'recognized_text' => 'Indomie goreng tiga sama barang tidak ada satu',
        ]);

        $orderInput = OrderInput::firstOrFail();
        $response->assertRedirect(route('pos.ai.review', $orderInput));

        $matchedItem = OrderInputItem::where('requested_product_name', 'Indomie Goreng')->firstOrFail();
        $unmatchedItem = OrderInputItem::where('requested_product_name', 'Barang Tidak Ada XYZ')->firstOrFail();

        $this->assertSame(MatchStatus::Matched, $matchedItem->match_status);
        $this->assertSame($indomie->id, $matchedItem->matched_product_id);
        $this->assertSame(MatchStatus::Unmatched, $unmatchedItem->match_status);
        $this->assertNull($unmatchedItem->confirmed_product_id);

        // Cart is still empty until cashier explicitly confirms
        $this->assertDatabaseCount('cart_items', 0);

        // Cashier confirms Indomie Goreng (edited qty to 4) and rejects unmatched item
        $this->actingAs($cashier)->post(route('pos.ai.confirm', $orderInput), [
            'items' => [
                [
                    'id' => $matchedItem->id,
                    'action' => 'confirm',
                    'confirmed_product_id' => $indomie->id,
                    'confirmed_quantity' => 4,
                ],
                [
                    'id' => $unmatchedItem->id,
                    'action' => 'reject',
                ],
            ],
        ])->assertRedirect(route('pos.index'));

        $this->assertSame(ReviewStatus::Edited, $matchedItem->fresh()->review_status);
        $this->assertSame(ReviewStatus::Rejected, $unmatchedItem->fresh()->review_status);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $indomie->id,
            'quantity' => 4,
            'price_snapshot' => 3500,
            'subtotal' => 14000,
        ]);
    }
}
