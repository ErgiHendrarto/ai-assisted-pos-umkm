@extends('layouts.guest')

@section('title', 'Access Denied')

@section('content')
<div class="min-h-screen flex items-center justify-center px-6 py-12 bg-surface">
    <div class="w-full max-w-md rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_4px_12px_0_rgba(37,43,54,0.08)] p-8 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-error-container text-on-error-container">
            <span class="material-symbols-outlined text-[24px]">gpp_bad</span>
        </div>
        <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-error">403 Forbidden</p>
        <h1 class="mt-1 text-xl font-semibold text-primary">Access Restricted</h1>
        <p class="mt-2 text-[13px] text-on-surface-variant leading-relaxed">
            Your current role does not have permission to view this page. Please contact the store administrator if you need access.
        </p>
        <div class="mt-6">
            <x-button variant="primary" :href="route('dashboard')">
                Return to Dashboard
            </x-button>
        </div>
    </div>
</div>
@endsection
