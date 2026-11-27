@extends('layouts.app', [
    'pageTitle' => 'Add Product',
    'breadcrumb' => 'Products / Add Product',
])

@section('title', 'Add Product')

@section('content')
<div class="max-w-3xl">
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] overflow-hidden">
        <div class="px-6 py-4 border-b border-divider">
            <h2 class="text-base font-semibold text-primary">Create New Product</h2>
            <p class="text-xs text-on-surface-variant mt-0.5">Add an official item to the UMKM catalog.</p>
        </div>

        <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            @include('products._form')

            <div class="pt-4 border-t border-divider flex items-center justify-between gap-3">
                <x-button variant="secondary" :href="route('products.index')">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Product</x-button>
            </div>
        </form>
    </div>
</div>
@endsection
