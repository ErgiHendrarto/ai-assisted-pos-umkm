@extends('layouts.app', [
    'pageTitle' => 'Settings',
    'breadcrumb' => 'Settings',
])

@section('title', 'Settings')

@section('content')
<div class="max-w-4xl space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Please fix the following errors:</p>
            <ul class="mt-1.5 list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (auth()->user()->isAdministrator())
        <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] overflow-hidden">
            <div class="px-6 py-5 border-b border-divider flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-primary">smart_toy</span>
                        <h2 class="text-base font-semibold text-primary">AI API Configuration</h2>
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        Configure the AI provider and encrypted API key for future Voice (Speech Recognition), OCR, and Product Matching features.
                    </p>
                </div>
                <x-status-badge
                    :status="$aiSetting->hasApiKey() && $aiSetting->is_active ? 'completed' : 'pending'"
                    :label="$aiSetting->hasApiKey() ? ($aiSetting->is_active ? 'API Active' : 'Key Saved (Inactive)') : 'API Key Not Set'"
                />
            </div>

            <form
                method="POST"
                action="{{ route('settings.ai.update') }}"
                x-data="{
                    modelsByProvider: @js($modelsByProvider),
                    provider: @js(old('provider', $aiSetting->provider)),
                    selectedModel: @js(old('model_name', $aiSetting->model_name)),
                    customModel: @js(old('custom_model_name', $aiSetting->model_name)),
                    init() {
                        this.syncModelSelection();
                    },
                    get availableModels() {
                        return this.modelsByProvider[this.provider] || {};
                    },
                    syncModelSelection() {
                        const keys = Object.keys(this.availableModels);
                        if (keys.length === 0) {
                            this.selectedModel = '__custom__';
                            return;
                        }
                        if (!keys.includes(this.selectedModel)) {
                            this.selectedModel = keys[0];
                        }
                    }
                }"
                class="p-6 space-y-5"
            >
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="provider" class="block text-xs font-semibold text-on-surface mb-1.5">
                            AI Provider
                        </label>
                        <select
                            id="provider"
                            name="provider"
                            x-model="provider"
                            @change="syncModelSelection()"
                            class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                        >
                            @foreach ($providers as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label for="model_name" class="block text-xs font-semibold text-on-surface mb-1.5">
                            Model Name
                        </label>
                        <select
                            id="model_name"
                            name="model_name"
                            x-model="selectedModel"
                            required
                            class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                        >
                            <template x-for="[modelId, modelLabel] in Object.entries(availableModels)" :key="modelId">
                                <option :value="modelId" x-text="modelLabel" :selected="selectedModel === modelId"></option>
                            </template>
                            <option value="__custom__">Custom Model Name...</option>
                        </select>

                        <div x-show="selectedModel === '__custom__'" x-cloak>
                            <input
                                type="text"
                                name="custom_model_name"
                                x-model="customModel"
                                placeholder="Enter custom model ID"
                                :required="selectedModel === '__custom__'"
                                class="w-full rounded-lg border border-divider bg-white px-3.5 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                            >
                        </div>
                    </div>
                </div>

                <div x-data="{ showKey: false }">
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="api_key" class="block text-xs font-semibold text-on-surface">
                            AI API Key
                        </label>
                        @if ($aiSetting->hasApiKey())
                            <span class="text-[11px] font-mono text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-2 py-0.5">
                                Saved: {{ $aiSetting->maskedApiKey() }}
                            </span>
                        @endif
                    </div>

                    <div class="relative">
                        <input
                            id="api_key"
                            :type="showKey ? 'text' : 'password'"
                            name="api_key"
                            autocomplete="new-password"
                            placeholder="{{ $aiSetting->hasApiKey() ? 'Leave blank to keep existing encrypted API key' : 'Enter your AI API key (encrypted at rest)' }}"
                            class="w-full rounded-lg border border-divider bg-white pl-3.5 pr-11 py-2.5 text-sm text-on-surface font-mono focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                        >
                        <button
                            type="button"
                            @click="showKey = !showKey"
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-outline hover:text-primary"
                            aria-label="Toggle API key visibility"
                        >
                            <span class="material-symbols-outlined text-[18px]" x-text="showKey ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                    <p class="mt-1.5 text-[11px] text-on-surface-variant">
                        API keys are encrypted with Laravel's application encryption before being stored.
                    </p>

                    @if ($aiSetting->hasApiKey())
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-red-600 cursor-pointer">
                            <input type="checkbox" name="clear_api_key" value="1" class="rounded border-divider text-red-600 focus:ring-red-500">
                            <span>Clear saved API key</span>
                        </label>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div class="sm:col-span-2">
                        <label for="base_url" class="block text-xs font-semibold text-on-surface mb-1.5">
                            API Base URL / Endpoint <span class="font-normal text-outline">(Optional)</span>
                        </label>
                        <input
                            id="base_url"
                            type="url"
                            name="base_url"
                            value="{{ old('base_url', $aiSetting->base_url) }}"
                            placeholder="https://generativelanguage.googleapis.com/v1beta"
                            class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                        >
                    </div>

                    <div>
                        <label for="timeout_seconds" class="block text-xs font-semibold text-on-surface mb-1.5">
                            Timeout (seconds)
                        </label>
                        <input
                            id="timeout_seconds"
                            type="number"
                            name="timeout_seconds"
                            min="5"
                            max="120"
                            value="{{ old('timeout_seconds', $aiSetting->timeout_seconds) }}"
                            required
                            class="w-full rounded-lg border border-divider bg-white px-3.5 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                        >
                    </div>
                </div>

                <div class="rounded-lg border border-divider/80 bg-surface-container-low/60 p-4 space-y-3">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wider text-outline">
                        AI Capabilities & Activation
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $aiSetting->is_active)) class="rounded border-divider text-primary focus:ring-primary">
                            <span class="text-on-surface font-medium">Enable AI Integration</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="voice_enabled" value="1" @checked(old('voice_enabled', $aiSetting->voice_enabled)) class="rounded border-divider text-primary focus:ring-primary">
                            <span class="text-on-surface">Voice Input (Speech Recognition)</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="ocr_enabled" value="1" @checked(old('ocr_enabled', $aiSetting->ocr_enabled)) class="rounded border-divider text-primary focus:ring-primary">
                            <span class="text-on-surface">Image Input (OCR Receipt/List)</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="matching_enabled" value="1" @checked(old('matching_enabled', $aiSetting->matching_enabled)) class="rounded border-divider text-primary focus:ring-primary">
                            <span class="text-on-surface">AI Product Matching</span>
                        </label>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-between gap-3 border-t border-divider">
                    <x-button variant="secondary" :href="route('dashboard')">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        <span>Back to Dashboard</span>
                    </x-button>

                    <x-button type="submit" variant="primary">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        <span>Save AI Configuration</span>
                    </x-button>
                </div>
            </form>
        </div>
    @else
        <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[22px] text-primary mt-0.5">admin_panel_settings</span>
                <div>
                    <h2 class="text-base font-semibold text-primary">AI & Store Settings</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        AI API credentials and system-level configuration are managed by the store Administrator.
                    </p>
                    <p class="mt-3 text-xs text-on-surface">
                        AI Integration Status:
                        <span class="font-semibold">{{ $aiSetting->is_active && $aiSetting->hasApiKey() ? 'Active (' . $aiSetting->provider . ')' : 'Not Configured' }}</span>
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
