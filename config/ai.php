<?php

return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'gemini-3-flash-preview'),
    'base_url' => env('AI_BASE_URL'),
    'timeout' => (int) env('AI_TIMEOUT', 30),
];
