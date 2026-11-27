<?php

namespace App\Services\AI;

use App\Models\AiSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiGatewayService
{
    public function getActiveSetting(): AiSetting
    {
        $setting = AiSetting::current();

        if (! $setting->is_active) {
            throw new RuntimeException('Integrasi AI belum diaktifkan oleh Administrator di menu Settings. Gunakan POS Manual untuk melanjutkan transaksi.');
        }

        if (! $setting->hasApiKey()) {
            throw new RuntimeException('API Key AI belum dikonfigurasi oleh Administrator di menu Settings.');
        }

        return $setting;
    }

    /**
     * Send a multimodal or text prompt to the configured AI provider.
     *
     * @param  array{mime_type: string, data_base64: string}|null  $inlineMedia
     */
    public function generateText(
        AiSetting $setting,
        string $prompt,
        ?array $inlineMedia = null,
        bool $expectJson = false
    ): string {
        $provider = $setting->provider;
        $timeout = max(5, (int) $setting->timeout_seconds);

        return match ($provider) {
            'gemini' => $this->callGemini($setting, $prompt, $inlineMedia, $expectJson, $timeout),
            'anthropic' => $this->callAnthropic($setting, $prompt, $inlineMedia, $timeout),
            default => $this->callOpenAiCompatible($setting, $prompt, $inlineMedia, $expectJson, $timeout),
        };
    }

    /**
     * Transcribe audio via OpenAI-compatible audio transcription endpoint or Gemini multimodal.
     */
    public function transcribeAudio(AiSetting $setting, string $absolutePath, string $mimeType): string
    {
        if ($setting->provider === 'gemini') {
            $base64 = base64_encode((string) file_get_contents($absolutePath));
            $prompt = 'Transkrip audio pesanan toko kelontong/sembako dalam Bahasa Indonesia ini menjadi teks biasa yang akurat. Kembalikan HANYA teks transkripsi murni tanpa kalimat pembuka, tanpa tanda kutip, dan tanpa format markdown.';

            return $this->callGemini($setting, $prompt, [
                'mime_type' => $mimeType,
                'data_base64' => $base64,
            ], false, max(10, (int) $setting->timeout_seconds));
        }

        if ($setting->provider === 'anthropic') {
            throw new RuntimeException('Provider Anthropic tidak mendukung transkripsi audio langsung. Gunakan teks atau provider Gemini/OpenAI.');
        }

        $baseUrl = rtrim($setting->base_url ?: 'https://api.openai.com/v1', '/');
        $response = Http::timeout(max(10, (int) $setting->timeout_seconds))
            ->withToken((string) $setting->api_key)
            ->attach('file', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post("{$baseUrl}/audio/transcriptions", [
                'model' => 'whisper-1',
                'language' => 'id',
            ]);

        $this->ensureSuccess($response, 'Speech Recognition');

        $text = trim((string) $response->json('text', ''));
        if ($text === '') {
            throw new RuntimeException('Layanan Speech Recognition tidak mendeteksi ucapan pada audio.');
        }

        return $text;
    }

    /**
     * @param  array{mime_type: string, data_base64: string}|null  $inlineMedia
     */
    private function callGemini(
        AiSetting $setting,
        string $prompt,
        ?array $inlineMedia,
        bool $expectJson,
        int $timeout
    ): string {
        $baseUrl = rtrim($setting->base_url ?: 'https://generativelanguage.googleapis.com/v1beta', '/');
        $model = preg_replace('/^models\//', '', trim($setting->model_name ?: 'gemini-3-flash-preview'));
        $url = "{$baseUrl}/models/{$model}:generateContent?key=" . urlencode((string) $setting->api_key);

        $parts = [['text' => $prompt]];
        if ($inlineMedia !== null) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $inlineMedia['mime_type'],
                    'data' => $inlineMedia['data_base64'],
                ],
            ];
        }

        $payload = [
            'contents' => [
                ['parts' => $parts],
            ],
        ];

        if ($expectJson) {
            $payload['generationConfig'] = [
                'responseMimeType' => 'application/json',
            ];
        }

        $response = Http::timeout($timeout)->post($url, $payload);
        $this->ensureSuccess($response, 'Gemini AI');

        $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
        if ($text === '') {
            throw new RuntimeException('Respons dari Gemini AI kosong atau tidak dapat dibaca.');
        }

        return $text;
    }

    /**
     * @param  array{mime_type: string, data_base64: string}|null  $inlineMedia
     */
    private function callOpenAiCompatible(
        AiSetting $setting,
        string $prompt,
        ?array $inlineMedia,
        bool $expectJson,
        int $timeout
    ): string {
        $baseUrl = rtrim($setting->base_url ?: 'https://api.openai.com/v1', '/');
        $model = $setting->model_name ?: 'gpt-4o-mini';

        $content = [['type' => 'text', 'text' => $prompt]];
        if ($inlineMedia !== null && str_starts_with($inlineMedia['mime_type'], 'image/')) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $inlineMedia['mime_type'] . ';base64,' . $inlineMedia['data_base64'],
                ],
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($expectJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout($timeout)
            ->withToken((string) $setting->api_key)
            ->post("{$baseUrl}/chat/completions", $payload);

        $this->ensureSuccess($response, 'AI API');

        $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($text === '') {
            throw new RuntimeException('Respons dari layanan AI kosong.');
        }

        return $text;
    }

    /**
     * @param  array{mime_type: string, data_base64: string}|null  $inlineMedia
     */
    private function callAnthropic(
        AiSetting $setting,
        string $prompt,
        ?array $inlineMedia,
        int $timeout
    ): string {
        $baseUrl = rtrim($setting->base_url ?: 'https://api.anthropic.com/v1', '/');
        $model = $setting->model_name ?: 'claude-3-5-haiku-latest';

        $content = [];
        if ($inlineMedia !== null && str_starts_with($inlineMedia['mime_type'], 'image/')) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $inlineMedia['mime_type'],
                    'data' => $inlineMedia['data_base64'],
                ],
            ];
        }
        $content[] = ['type' => 'text', 'text' => $prompt];

        $response = Http::timeout($timeout)
            ->withHeaders([
                'x-api-key' => (string) $setting->api_key,
                'anthropic-version' => '2023-06-01',
            ])
            ->post("{$baseUrl}/messages", [
                'model' => $model,
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        $this->ensureSuccess($response, 'Anthropic AI');

        $text = trim((string) data_get($response->json(), 'content.0.text', ''));
        if ($text === '') {
            throw new RuntimeException('Respons dari Anthropic AI kosong.');
        }

        return $text;
    }

    private function ensureSuccess(Response $response, string $serviceLabel): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $apiDetail = $response->json('error.message');
        $message = match (true) {
            $status === 401 || $status === 403 => "{$serviceLabel}: API Key tidak valid atau tidak memiliki izin.",
            $status === 404 => "{$serviceLabel}: Model atau endpoint tidak ditemukan (HTTP 404)." . ($apiDetail ? " Detail: {$apiDetail}" : ''),
            $status === 429 => "{$serviceLabel}: Batas kuota permintaan (rate limit) tercapai. Silakan coba lagi sebentar.",
            $status >= 500 => "{$serviceLabel}: Server layanan AI sedang mengalami gangguan ({$status}).",
            default => "{$serviceLabel}: Permintaan gagal dengan kode HTTP {$status}." . ($apiDetail ? " Detail: {$apiDetail}" : ''),
        };

        throw new RuntimeException($message);
    }
}
