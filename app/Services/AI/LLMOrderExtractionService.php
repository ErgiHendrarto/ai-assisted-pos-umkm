<?php

namespace App\Services\AI;

use App\Enums\AiProcessingStatus;
use App\Enums\AiProcessingType;
use App\Models\AIProcessingRun;
use App\Models\OrderInput;
use RuntimeException;
use Throwable;

class LLMOrderExtractionService
{
    public const PROMPT_VERSION = 'v1.0';

    public function __construct(
        private readonly AiGatewayService $gateway
    ) {}

    public function extract(OrderInput $orderInput, string $rawText): AIProcessingRun
    {
        $cleanText = trim($rawText);
        if ($cleanText === '') {
            throw new RuntimeException('Teks pesanan kosong. Silakan masukkan atau rekam ulang pesanan.');
        }

        $setting = $this->gateway->getActiveSetting();
        $startedAt = microtime(true);

        $prompt = <<<PROMPT
Anda adalah asisten ekstraksi pesanan toko kelontong/sembako UMKM Indonesia.
Ekstrak daftar barang dan jumlahnya dari teks pesanan pelanggan berikut menjadi JSON valid SAJA dengan format persis seperti ini:
{
  "items": [
    {
      "product_name": "nama produk",
      "quantity": 1,
      "unit": "pcs"
    }
  ]
}
Aturan:
- Jangan mengarang harga, total, atau ID produk.
- Jika jumlah tidak disebut, gunakan 1.
- Ubah kata angka Bahasa Indonesia ("dua", "tiga", "setengah") menjadi angka desimal (2, 3, 0.5).
- Unit opsional (pcs, pack, bottle, box, kg, gram, liter).

Teks pesanan:
"{$cleanText}"
PROMPT;

        try {
            $rawResponse = $this->gateway->generateText($setting, $prompt, null, true);
            $structured = $this->parseAndValidateJson($rawResponse);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            return AIProcessingRun::create([
                'order_input_id' => $orderInput->id,
                'processing_type' => AiProcessingType::LlmExtraction,
                'service_name' => $setting->provider,
                'model_name' => $setting->model_name,
                'prompt_version' => self::PROMPT_VERSION,
                'recognized_text' => $cleanText,
                'structured_output' => $structured,
                'processing_status' => AiProcessingStatus::Completed,
                'processing_duration_ms' => $durationMs,
            ]);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            AIProcessingRun::create([
                'order_input_id' => $orderInput->id,
                'processing_type' => AiProcessingType::LlmExtraction,
                'service_name' => $setting->provider,
                'model_name' => $setting->model_name,
                'prompt_version' => self::PROMPT_VERSION,
                'recognized_text' => $cleanText,
                'processing_status' => AiProcessingStatus::Failed,
                'error_message' => $e->getMessage(),
                'processing_duration_ms' => $durationMs,
            ]);

            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate and normalize LLM JSON output.
     *
     * @return array{items: array<int, array{product_name: string, quantity: float, unit: ?string}>}
     */
    public function parseAndValidateJson(string $rawJson): array
    {
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($rawJson)) ?? $rawJson;
        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded) || ! isset($decoded['items']) || ! is_array($decoded['items'])) {
            throw new RuntimeException('Format JSON dari LLM tidak valid (field "items" tidak ditemukan).');
        }

        $merged = [];
        foreach ($decoded['items'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['product_name'] ?? ''));
            $qty = (float) ($entry['quantity'] ?? 0);
            $unit = isset($entry['unit']) && trim((string) $entry['unit']) !== ''
                ? trim((string) $entry['unit'])
                : null;

            if ($name === '' || $qty <= 0) {
                continue;
            }

            $key = mb_strtolower($name);
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] = round($merged[$key]['quantity'] + $qty, 3);
            } else {
                $merged[$key] = [
                    'product_name' => $name,
                    'quantity' => round($qty, 3),
                    'unit' => $unit,
                ];
            }
        }

        if (empty($merged)) {
            throw new RuntimeException('Tidak ada item produk yang berhasil diekstrak dari teks pesanan.');
        }

        return ['items' => array_values($merged)];
    }
}
