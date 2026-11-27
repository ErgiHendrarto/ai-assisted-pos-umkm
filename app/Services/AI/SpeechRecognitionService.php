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

class SpeechRecognitionService
{
    public function __construct(
        private readonly AiGatewayService $gateway
    ) {}

    public function transcribe(OrderInput $orderInput, UploadedFile $audioFile): AIProcessingRun
    {
        $setting = $this->gateway->getActiveSetting();
        if (! $setting->voice_enabled) {
            throw new RuntimeException('Fitur Voice Order sedang dinonaktifkan di menu Settings.');
        }

        $storedPath = $audioFile->store('order_inputs/voice', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        $orderInput->update([
            'file_path' => $storedPath,
            'metadata' => array_merge($orderInput->metadata ?? [], [
                'original_filename' => $audioFile->getClientOriginalName(),
                'mime_type' => $audioFile->getMimeType(),
                'size_bytes' => $audioFile->getSize(),
            ]),
        ]);

        $startedAt = microtime(true);

        try {
            $mimeType = $this->normalizeAudioMimeType(
                $audioFile->getMimeType(),
                $audioFile->getClientOriginalExtension()
            );

            $recognizedText = $this->gateway->transcribeAudio(
                $setting,
                $absolutePath,
                $mimeType
            );

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $orderInput->update(['original_text' => $recognizedText]);

            return AIProcessingRun::create([
                'order_input_id' => $orderInput->id,
                'processing_type' => AiProcessingType::SpeechRecognition,
                'service_name' => $setting->provider,
                'model_name' => $setting->provider === 'gemini' ? $setting->model_name : 'whisper-1',
                'recognized_text' => $recognizedText,
                'processing_status' => AiProcessingStatus::Completed,
                'processing_duration_ms' => $durationMs,
            ]);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            AIProcessingRun::create([
                'order_input_id' => $orderInput->id,
                'processing_type' => AiProcessingType::SpeechRecognition,
                'service_name' => $setting->provider,
                'model_name' => $setting->model_name,
                'processing_status' => AiProcessingStatus::Failed,
                'error_message' => $e->getMessage(),
                'processing_duration_ms' => $durationMs,
            ]);

            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function normalizeAudioMimeType(?string $detectedMime, string $extension): string
    {
        $mime = strtolower(trim((string) $detectedMime));
        $ext = strtolower(trim($extension));

        if ($mime === 'video/webm' || $ext === 'webm') {
            return 'audio/webm';
        }

        if ($mime === 'video/ogg' || $ext === 'ogg') {
            return 'audio/ogg';
        }

        if (str_starts_with($mime, 'audio/')) {
            return $mime;
        }

        return match ($ext) {
            'mp3' => 'audio/mp3',
            'wav' => 'audio/wav',
            'm4a', 'mp4' => 'audio/mp4',
            default => 'audio/webm',
        };
    }
}
