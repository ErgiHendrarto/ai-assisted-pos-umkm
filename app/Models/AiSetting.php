<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider',
    'api_key',
    'model_name',
    'base_url',
    'timeout_seconds',
    'voice_enabled',
    'ocr_enabled',
    'matching_enabled',
    'is_active',
    'updated_by',
])]
#[Hidden(['api_key'])]
class AiSetting extends Model
{
    public const PROVIDERS = [
        'gemini' => 'Google Gemini API',
        'openai' => 'OpenAI API',
        'anthropic' => 'Anthropic Claude API',
        'custom' => 'Custom / OpenAI-Compatible Endpoint',
    ];

    public const MODELS_BY_PROVIDER = [
        'gemini' => [
            'gemini-3-flash-preview' => 'Gemini 3 Flash Preview (Recommended)',
            'gemini-4-hydrogen' => 'Gemini 4 Hydrogen',
            'gemini-4-neon' => 'Gemini 4 Neon',
            'gemini-4-carbon' => 'Gemini 4 Carbon',
            'gemini-4-argon' => 'Gemini 4 Argon',
            'gemini-3.8-flash' => 'Gemini 3.8 Flash',
            'gemini-3.6-flash' => 'Gemini 3.6 Flash',
            'gemini-flash-latest' => 'Gemini Flash Latest',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro',
        ],
        'openai' => [
            'gpt-4o-mini' => 'GPT-4o Mini (Recommended)',
            'gpt-4o' => 'GPT-4o',
            'gpt-4.1-mini' => 'GPT-4.1 Mini',
        ],
        'anthropic' => [
            'claude-3-5-haiku-latest' => 'Claude 3.5 Haiku (Recommended)',
            'claude-3-7-sonnet-latest' => 'Claude 3.7 Sonnet',
        ],
        'custom' => [],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'timeout_seconds' => 'integer',
            'voice_enabled' => 'boolean',
            'ocr_enabled' => 'boolean',
            'matching_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return static::query()->first() ?? new static([
            'provider' => config('ai.provider', 'gemini'),
            'model_name' => config('ai.model', 'gemini-3-flash-preview'),
            'base_url' => config('ai.base_url'),
            'timeout_seconds' => config('ai.timeout', 30),
            'voice_enabled' => true,
            'ocr_enabled' => true,
            'matching_enabled' => true,
            'is_active' => false,
        ]);
    }

    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }

    public function maskedApiKey(): ?string
    {
        if (! $this->hasApiKey()) {
            return null;
        }

        $key = (string) $this->api_key;
        $length = mb_strlen($key);

        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return mb_substr($key, 0, 4) . str_repeat('•', min(12, $length - 8)) . mb_substr($key, -4);
    }
}
