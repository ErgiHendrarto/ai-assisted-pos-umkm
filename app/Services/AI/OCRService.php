<?php

namespace App\Services\AI;

use App\Enums\AiProcessingStatus;
use App\Enums\AiProcessingType;
use App\Models\AIProcessingRun;
use App\Models\OrderInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class OCRService
{
    public function __construct(
        private readonly AiGatewayService $gateway
    ) {}

    public function extractText(OrderInput $orderInput, UploadedFile $imageFile): AIProcessingRun
    {
        $setting = $this->gateway->getActiveSetting();
        if (! $setting->ocr_enabled) {
            throw new RuntimeException('Fitur Image / OCR Order sedang dinonaktifkan di menu Settings.');
        }

        $storedPath = $imageFile->store('order_inputs/images', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        $orderInput->update([
            'file_path' => $storedPath,
            'metadata' => array_merge($orderInput->metadata ?? [], [
                'original_filename' => $imageFile->getClientOriginalName(),
                'mime_type' => $imageFile->getMimeType(),
                'size_bytes' => $imageFile->getSize(),
            ]),
        ]);

        $startedAt = microtime(true);

        try {
            $prompt = 'Baca seluruh teks daftar belanja pada gambar ini secara akurat (Bahasa Indonesia). Tuliskan baris demi baris apa adanya tanpa menambah harga atau produk yang tidak tertulis.';
            $recognizedText = $this->gateway->generateText(
                $setting,
                $prompt,
                [
                    'mime_type' => $imageFile->getMimeType() ?: 'image/jpeg',
                    'data_base64' => base64_encode((string) file_get_contents($absolutePath)),
                ],
                false
            );

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $orderInput->update(['original_text' => $recognizedText]);

            return AIProcessingRun::create([
                'order_input_id' => $orderInput->id,
                'processing_type' => AiProcessingType::Ocr,
                'service_name' => $setting->provider,
                'model_name' => $setting->model_name,
                'recognized_text' => $recognizedText,
                'processing_status' => AiProcessingStatus::Completed,
                'processing_duration_ms' => $durationMs,
            ]);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            AIProcessingRun::create([
                'order_input_id' => $orderInput->id,
                'processing_type' => AiProcessingType::Ocr,
                'service_name' => $setting->provider,
                'model_name' => $setting->model_name,
                'processing_status' => AiProcessingStatus::Failed,
                'error_message' => $e->getMessage(),
                'processing_duration_ms' => $durationMs,
            ]);

            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
