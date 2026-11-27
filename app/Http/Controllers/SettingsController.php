<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'aiSetting' => AiSetting::current(),
            'providers' => AiSetting::PROVIDERS,
            'modelsByProvider' => AiSetting::MODELS_BY_PROVIDER,
        ]);
    }

    public function updateAi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys(AiSetting::PROVIDERS))],
            'api_key' => ['nullable', 'string', 'min:8', 'max:500'],
            'clear_api_key' => ['nullable', 'boolean'],
            'model_name' => ['required', 'string', 'max:100'],
            'custom_model_name' => ['nullable', 'required_if:model_name,__custom__', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'voice_enabled' => ['nullable', 'boolean'],
            'ocr_enabled' => ['nullable', 'boolean'],
            'matching_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $resolvedModel = $validated['model_name'] === '__custom__'
            ? trim((string) ($validated['custom_model_name'] ?? ''))
            : trim($validated['model_name']);

        $setting = AiSetting::query()->first() ?? new AiSetting();

        $setting->provider = $validated['provider'];
        $setting->model_name = $resolvedModel;
        $setting->base_url = $validated['base_url'] ?? null;
        $setting->timeout_seconds = (int) $validated['timeout_seconds'];
        $setting->voice_enabled = $request->boolean('voice_enabled');
        $setting->ocr_enabled = $request->boolean('ocr_enabled');
        $setting->matching_enabled = $request->boolean('matching_enabled');
        $setting->is_active = $request->boolean('is_active');
        $setting->updated_by = $request->user()->id;

        if ($request->boolean('clear_api_key')) {
            $setting->api_key = null;
        } elseif (filled($validated['api_key'] ?? null)) {
            $setting->api_key = $validated['api_key'];
        }

        $setting->save();

        return redirect()
            ->route('settings.index')
            ->with('status', 'AI API configuration has been saved securely.');
    }
}
