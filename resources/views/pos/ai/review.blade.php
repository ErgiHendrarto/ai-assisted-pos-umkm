@extends('layouts.app', [
    'pageTitle' => 'AI Order Confirmation',
    'breadcrumb' => 'POS / AI Confirmation',
])

@section('title', 'AI Order Confirmation')

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Perhatian:</p>
            <ul class="mt-1 list-disc list-inside text-xs space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Step 1: Recognized / OCR Text Review & Re-Extract -->
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-divider">
            <div>
                <h2 class="text-base font-semibold text-primary">
                    Recognized Order Text ({{ ucfirst($orderInput->input_type->value) }})
                </h2>
                <p class="text-xs text-on-surface-variant mt-0.5">
                    Anda dapat memperbaiki teks hasil Speech Recognition / OCR lalu klik <strong>Re-Extract Items</strong> jika perlu.
                </p>
            </div>
            <x-button size="sm" variant="secondary" :href="route('pos.index')">Back to Manual POS</x-button>
        </div>

        <form method="POST" action="{{ route('pos.ai.extract', $orderInput) }}" class="mt-4 space-y-3">
            @csrf
            <textarea
                name="recognized_text"
                rows="3"
                required
                class="w-full rounded-lg border border-divider px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none"
            >{{ old('recognized_text', $orderInput->original_text) }}</textarea>
            <div class="flex justify-end">
                <x-button type="submit" size="sm" variant="secondary">
                    <span class="material-symbols-outlined text-[16px]">refresh</span>
                    <span>Re-Extract Items with AI</span>
                </x-button>
            </div>
        </form>
    </div>

    <!-- Step 2: Cashier Review & Confirmation Table -->
    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6">
        <div class="pb-4 border-b border-divider">
            <h2 class="text-base font-semibold text-primary">Review &amp; Confirm Matched Products</h2>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Harga dan stok selalu diambil dari database resmi. Item berstatus <strong>Ambiguous</strong> atau <strong>Unmatched</strong> wajib dipilih produknya secara manual atau ditolak.
            </p>
        </div>

        @if (! $latestLlmRun || $latestLlmRun->items->isEmpty())
            <x-empty-state
                title="No Extracted Items Available"
                description="Edit the recognized text above and click Re-Extract Items, or return to Manual POS."
            />
        @else
            <form method="POST" action="{{ route('pos.ai.confirm', $orderInput) }}" class="mt-4 space-y-5">
                @csrf

                <div class="space-y-3">
                    @foreach ($latestLlmRun->items as $idx => $item)
                        @php
                            $candidates = $candidatesByItem[$item->id] ?? collect();
                            $selectedId = old("items.{$idx}.confirmed_product_id", $item->confirmed_product_id);
                        @endphp
                        <div class="rounded-xl border border-divider p-4 bg-white space-y-3">
                            <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $item->id }}">

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-outline">AI Extracted:</span>
                                    <span class="text-sm font-bold text-primary ml-1">
                                        "{{ $item->requested_product_name }}" ({{ \App\Support\Format::quantity($item->requested_quantity) }} {{ $item->requested_unit ?? 'unit' }})
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($item->match_confidence !== null)
                                        <span class="text-[11px] font-mono text-outline">
                                            Confidence: {{ number_format((float) $item->match_confidence * 100, 0) }}%
                                        </span>
                                    @endif
                                    <x-status-badge
                                        :status="$item->match_status->value === 'matched' ? 'completed' : ($item->match_status->value === 'ambiguous' ? 'pending' : 'cancelled')"
                                        :label="ucfirst($item->match_status->value)"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div class="md:col-span-6">
                                    <label class="block text-xs font-semibold text-on-surface mb-1">Official Catalog Product (Database Price &amp; Stock)</label>
                                    <select name="items[{{ $idx }}][confirmed_product_id]" class="w-full rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                                        <option value="">-- Pilih Produk Katalog --</option>
                                        @if ($candidates->isNotEmpty())
                                            <optgroup label="Top AI Candidates">
                                                @foreach ($candidates as $cand)
                                                    @php $p = $cand['product']; @endphp
                                                    <option value="{{ $p->id }}" @selected((string) $selectedId === (string) $p->id)>
                                                        {{ $p->product_name }} ({{ $p->product_code }}) — {{ \App\Support\Format::rupiah($p->price) }} | Stock: {{ \App\Support\Format::quantity($p->stock) }} {{ $p->unit }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        <optgroup label="All Active Products">
                                            @foreach ($activeProducts as $p)
                                                <option value="{{ $p->id }}" @selected((string) $selectedId === (string) $p->id)>
                                                    {{ $p->product_name }} ({{ $p->product_code }}) — {{ \App\Support\Format::rupiah($p->price) }} | Stock: {{ \App\Support\Format::quantity($p->stock) }} {{ $p->unit }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </div>

                                <div class="md:col-span-3">
                                    <label class="block text-xs font-semibold text-on-surface mb-1">Confirmed Qty</label>
                                    <input
                                        type="number"
                                        step="0.001"
                                        min="0.001"
                                        name="items[{{ $idx }}][confirmed_quantity]"
                                        value="{{ old("items.{$idx}.confirmed_quantity", (float) ($item->confirmed_quantity ?? $item->requested_quantity)) }}"
                                        class="w-full rounded-lg border border-divider px-3 py-2 text-sm tabular-nums"
                                    >
                                </div>

                                <div class="md:col-span-3">
                                    <label class="block text-xs font-semibold text-on-surface mb-1">Cashier Decision</label>
                                    <select name="items[{{ $idx }}][action]" class="w-full rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                                        <option value="confirm" @selected($item->match_status->value !== 'unmatched')>Confirm &amp; Add to Cart</option>
                                        <option value="reject" @selected($item->match_status->value === 'unmatched')>Reject / Skip Item</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-4 border-t border-divider flex flex-wrap items-center justify-between gap-3">
                    <x-button variant="secondary" :href="route('pos.index')">Cancel &amp; Return to POS</x-button>
                    <x-button type="submit" variant="primary" size="lg">
                        <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                        <span>Confirm &amp; Add Selected to Cart</span>
                    </x-button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
