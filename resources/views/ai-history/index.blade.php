@extends('layouts.app', [
    'pageTitle' => 'AI Processing History',
    'breadcrumb' => 'AI Processing History',
])

@section('title', 'AI Processing History')

@section('content')
<div class="space-y-6">
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6">
        <div class="pb-4 border-b border-divider">
            <h2 class="text-base font-semibold text-primary">AI Processing Runs &amp; Thesis Evaluation Log</h2>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Audit trail of Voice/OCR/LLM runs, latency, and cashier confirmation accuracy (no API keys stored or shown).
            </p>
        </div>

        <div class="mt-4">
            @if ($runs->isEmpty())
                <x-empty-state title="No AI Runs Recorded" description="AI voice and OCR order runs will appear here for thesis evaluation." />
            @else
                <x-table :headers="['Date', 'Cashier', 'Input', 'Stage', 'Model', 'Duration', 'Items (Confirmed/Edited/Rejected)', 'Status']">
                    @foreach ($runs as $run)
                        @php
                            $confirmed = $run->items->where('review_status', \App\Enums\ReviewStatus::Confirmed)->count();
                            $edited = $run->items->where('review_status', \App\Enums\ReviewStatus::Edited)->count();
                            $rejected = $run->items->where('review_status', \App\Enums\ReviewStatus::Rejected)->count();
                        @endphp
                        <tr class="hover:bg-surface-container-low/40">
                            <td class="py-2.5 px-3 text-xs text-on-surface-variant whitespace-nowrap">{{ $run->created_at?->format('d M Y H:i') }}</td>
                            <td class="py-2.5 px-3 text-xs">{{ $run->orderInput?->user?->name ?? '—' }}</td>
                            <td class="py-2.5 px-3 text-xs capitalize">{{ $run->orderInput?->input_type->value ?? '—' }}</td>
                            <td class="py-2.5 px-3 text-xs">{{ str_replace('_', ' ', $run->processing_type->value) }}</td>
                            <td class="py-2.5 px-3 font-mono text-xs">{{ $run->model_name ?? '—' }}</td>
                            <td class="py-2.5 px-3 tabular-nums text-xs">{{ $run->processing_duration_ms ? $run->processing_duration_ms . ' ms' : '—' }}</td>
                            <td class="py-2.5 px-3 text-xs tabular-nums">
                                <span class="text-emerald-700 font-semibold">{{ $confirmed }}</span> /
                                <span class="text-amber-700 font-semibold">{{ $edited }}</span> /
                                <span class="text-red-700 font-semibold">{{ $rejected }}</span>
                            </td>
                            <td class="py-2.5 px-3"><x-status-badge :status="$run->processing_status->value" /></td>
                        </tr>
                    @endforeach
                </x-table>

                <div class="mt-4">{{ $runs->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
