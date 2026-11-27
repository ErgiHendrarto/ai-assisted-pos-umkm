@extends('layouts.app', [
    'pageTitle' => $pageTitle,
    'breadcrumb' => $breadcrumb,
])

@section('title', $pageTitle)

@section('content')
<div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6 sm:p-8 max-w-3xl">
    <div class="pb-5 border-b border-divider">
        <x-status-badge status="placeholder" label="Upcoming Development Phase" />
        <h2 class="mt-3 text-xl font-semibold text-primary tracking-[-0.01em]">{{ $moduleName }}</h2>
        <p class="mt-1.5 text-sm text-on-surface-variant leading-relaxed">{{ $description }}</p>
    </div>

    @if (! empty($plannedFeatures))
        <div class="mt-6">
            <h3 class="text-[11px] font-semibold uppercase tracking-wider text-outline">
                Planned Capabilities
            </h3>
            <ul class="mt-3 space-y-2.5">
                @foreach ($plannedFeatures as $feature)
                    <li class="flex items-start gap-2.5 text-[13px] text-on-surface">
                        <span class="material-symbols-outlined text-[16px] text-secondary shrink-0 mt-0.5">check_circle</span>
                        <span>{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-7 pt-5 border-t border-divider flex items-center gap-3">
        <x-button variant="primary" :href="route('dashboard')">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Back to Dashboard</span>
        </x-button>
    </div>
</div>
@endsection
