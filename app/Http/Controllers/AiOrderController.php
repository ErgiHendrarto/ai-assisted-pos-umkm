<?php

namespace App\Http\Controllers;

use App\Enums\AiProcessingType;
use App\Enums\OrderInputStatus;
use App\Enums\OrderInputType;
use App\Enums\ReviewStatus;
use App\Models\AIProcessingRun;
use App\Models\AiSetting;
use App\Models\OrderInput;
use App\Models\OrderInputItem;
use App\Models\Product;
use App\Services\AI\LLMOrderExtractionService;
use App\Services\AI\OCRService;
use App\Services\AI\ProductMatchingService;
use App\Services\AI\SpeechRecognitionService;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AiOrderController extends Controller
{
    public function __construct(
        private readonly SpeechRecognitionService $speechService,
        private readonly OCRService $ocrService,
        private readonly LLMOrderExtractionService $llmService,
        private readonly ProductMatchingService $matchingService,
        private readonly CartService $cartService
    ) {}

    public function voiceForm(): View
    {
        $setting = AiSetting::current();

        return view('pos.ai.voice', [
            'aiAvailable' => $setting->is_active && $setting->hasApiKey() && $setting->voice_enabled,
        ]);
    }

    public function processVoice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'audio_file' => ['nullable', 'file', 'mimes:webm,wav,mp3,ogg,m4a', 'max:10240'],
            'recognized_text' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $request->hasFile('audio_file') && blank($validated['recognized_text'] ?? null)) {
            throw ValidationException::withMessages([
                'audio_file' => 'Silakan rekam/unggah audio pesanan atau ketik teks transkripsi pesanan.',
            ]);
        }

        $cart = $this->cartService->getOrCreateActiveCart($request->user());

        $orderInput = OrderInput::create([
            'user_id' => $request->user()->id,
            'cart_id' => $cart->id,
            'input_type' => OrderInputType::Voice,
            'original_text' => $validated['recognized_text'] ?? null,
            'processing_status' => OrderInputStatus::Processing,
        ]);

        try {
            $textToExtract = trim((string) ($validated['recognized_text'] ?? ''));

            if ($request->hasFile('audio_file')) {
                $speechRun = $this->speechService->transcribe($orderInput, $request->file('audio_file'));
                $textToExtract = (string) $speechRun->recognized_text;
            }

            $llmRun = $this->llmService->extract($orderInput, $textToExtract);
            $this->matchingService->matchRunItems($llmRun);
            $orderInput->update(['processing_status' => OrderInputStatus::Completed]);

            return redirect()
                ->route('pos.ai.review', $orderInput)
                ->with('status', 'Pesanan suara berhasil diproses. Silakan periksa dan konfirmasi item sebelum masuk keranjang.');
        } catch (Throwable $e) {
            $orderInput->update(['processing_status' => OrderInputStatus::Failed]);

            return redirect()
                ->route('pos.ai.review', $orderInput)
                ->withErrors(['ai' => $e->getMessage()]);
        }
    }

    public function imageForm(): View
    {
        $setting = AiSetting::current();

        return view('pos.ai.image', [
            'aiAvailable' => $setting->is_active && $setting->hasApiKey() && $setting->ocr_enabled,
        ]);
    }

    public function processImage(Request $request): RedirectResponse
    {
        $request->validate([
            'image_file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $cart = $this->cartService->getOrCreateActiveCart($request->user());

        $orderInput = OrderInput::create([
            'user_id' => $request->user()->id,
            'cart_id' => $cart->id,
            'input_type' => OrderInputType::Image,
            'processing_status' => OrderInputStatus::Processing,
        ]);

        try {
            $ocrRun = $this->ocrService->extractText($orderInput, $request->file('image_file'));
            $llmRun = $this->llmService->extract($orderInput, (string) $ocrRun->recognized_text);
            $this->matchingService->matchRunItems($llmRun);
            $orderInput->update(['processing_status' => OrderInputStatus::Completed]);

            return redirect()
                ->route('pos.ai.review', $orderInput)
                ->with('status', 'Gambar daftar belanja berhasil dibaca (OCR). Silakan periksa dan konfirmasi sebelum masuk keranjang.');
        } catch (Throwable $e) {
            $orderInput->update(['processing_status' => OrderInputStatus::Failed]);

            return redirect()
                ->route('pos.ai.review', $orderInput)
                ->withErrors(['ai' => $e->getMessage()]);
        }
    }

    public function extractFromText(Request $request, OrderInput $orderInput): RedirectResponse
    {
        $this->authorizeOwner($request->user()->id, $orderInput);

        $validated = $request->validate([
            'recognized_text' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $orderInput->update([
            'original_text' => $validated['recognized_text'],
            'processing_status' => OrderInputStatus::Processing,
        ]);

        try {
            $llmRun = $this->llmService->extract($orderInput, $validated['recognized_text']);
            $this->matchingService->matchRunItems($llmRun);
            $orderInput->update(['processing_status' => OrderInputStatus::Completed]);

            return redirect()
                ->route('pos.ai.review', $orderInput)
                ->with('status', 'Teks berhasil diekstrak ulang dan dicocokkan dengan katalog produk.');
        } catch (Throwable $e) {
            $orderInput->update(['processing_status' => OrderInputStatus::Failed]);

            return redirect()
                ->route('pos.ai.review', $orderInput)
                ->withErrors(['ai' => $e->getMessage()]);
        }
    }

    public function review(Request $request, OrderInput $orderInput): View
    {
        $this->authorizeOwner($request->user()->id, $orderInput);

        $latestLlmRun = $orderInput->aiProcessingRuns()
            ->where('processing_type', AiProcessingType::LlmExtraction)
            ->with(['items.matchedProduct', 'items.confirmedProduct'])
            ->latest('id')
            ->first();

        $activeProducts = Product::query()
            ->active()
            ->orderBy('product_name')
            ->get(['id', 'product_code', 'product_name', 'price', 'stock', 'unit']);

        $candidatesByItem = [];
        if ($latestLlmRun) {
            foreach ($latestLlmRun->items as $item) {
                $candidatesByItem[$item->id] = $this->matchingService->rankCandidates(
                    $item->requested_product_name,
                    $activeProducts,
                    5
                );
            }
        }

        return view('pos.ai.review', [
            'orderInput' => $orderInput,
            'latestLlmRun' => $latestLlmRun,
            'activeProducts' => $activeProducts,
            'candidatesByItem' => $candidatesByItem,
        ]);
    }

    public function confirm(Request $request, OrderInput $orderInput): RedirectResponse
    {
        $this->authorizeOwner($request->user()->id, $orderInput);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:order_input_items,id'],
            'items.*.action' => ['required', 'in:confirm,reject'],
            'items.*.confirmed_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.confirmed_quantity' => ['nullable', 'numeric', 'min:0.001', 'max:99999'],
        ]);

        $addedCount = 0;

        DB::transaction(function () use ($request, $orderInput, $validated, &$addedCount): void {
            foreach ($validated['items'] as $row) {
                /** @var OrderInputItem $item */
                $item = OrderInputItem::query()->findOrFail($row['id']);

                if ($row['action'] === 'reject') {
                    $item->update([
                        'review_status' => ReviewStatus::Rejected,
                    ]);
                    continue;
                }

                $productId = $row['confirmed_product_id'] ?? null;
                $quantity = (float) ($row['confirmed_quantity'] ?? 0);

                if (! $productId || $quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Item '{$item->requested_product_name}' belum memiliki produk katalog atau jumlah yang valid. Pilih produk atau pilih Tolak/Hapus.",
                    ]);
                }

                /** @var Product $product */
                $product = Product::query()->findOrFail($productId);
                if (! $product->is_active || (float) $product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$product->product_name} tidak mencukupi (tersedia: {$product->stock} {$product->unit}).",
                    ]);
                }

                $wasEdited = ((int) $item->matched_product_id !== (int) $product->id)
                    || (abs((float) $item->requested_quantity - $quantity) > 0.0001);

                $item->update([
                    'confirmed_product_id' => $product->id,
                    'confirmed_quantity' => $quantity,
                    'review_status' => $wasEdited ? ReviewStatus::Edited : ReviewStatus::Confirmed,
                ]);

                $this->cartService->addItem($request->user(), $product, $quantity);
                $addedCount++;
            }

            $orderInput->update([
                'processing_status' => OrderInputStatus::Reviewed,
            ]);
        });

        return redirect()
            ->route('pos.index')
            ->with('status', "{$addedCount} item hasil konfirmasi AI telah ditambahkan ke keranjang.");
    }

    public function history(Request $request): View
    {
        $runs = AIProcessingRun::query()
            ->with(['orderInput.user', 'items.matchedProduct', 'items.confirmedProduct'])
            ->latest('id')
            ->paginate(15);

        return view('ai-history.index', [
            'runs' => $runs,
        ]);
    }

    private function authorizeOwner(int $userId, OrderInput $orderInput): void
    {
        if ($orderInput->user_id !== $userId) {
            abort(403, 'Anda tidak memiliki akses ke input pesanan ini.');
        }
    }
}
