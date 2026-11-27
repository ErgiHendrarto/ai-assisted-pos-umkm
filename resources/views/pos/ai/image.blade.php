@extends('layouts.app', [
    'pageTitle' => 'Image / OCR Order (AI)',
    'breadcrumb' => 'POS / Image OCR Order',
])

@section('title', 'Image / OCR Order')

@section('content')
<div
    x-data="{
        previewUrl: null,
        handleFile(event) {
            const file = event.target.files[0];
            if (!file) {
                this.previewUrl = null;
                return;
            }
            this.previewUrl = URL.createObjectURL(file);
        },
        clearFile() {
            this.$refs.imageInput.value = '';
            this.previewUrl = null;
        }
    }"
    class="max-w-3xl space-y-6"
>
    @if (! $aiAvailable)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 flex items-start gap-2.5">
            <span class="material-symbols-outlined text-[18px] text-amber-600 mt-0.5">warning</span>
            <div>
                <p class="font-semibold">Layanan AI OCR belum aktif atau API Key belum diisi.</p>
                <p class="text-xs mt-0.5">Administrator dapat mengaktifkannya di menu Settings. Anda tetap dapat menggunakan POS Manual kapan saja.</p>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)] p-6 space-y-6">
        <div class="border-b border-divider pb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-primary">Image / Shopping List OCR Order</h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Unggah foto catatan belanja pelanggan untuk dibaca oleh OCR dan dicocokkan ke katalog.</p>
            </div>
            <x-button size="sm" variant="secondary" :href="route('pos.index')">Back to POS</x-button>
        </div>

        <form method="POST" action="{{ route('pos.ai.image.process') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="rounded-xl border-2 border-dashed border-divider bg-surface-container-low/40 p-6 text-center space-y-3">
                <template x-if="!previewUrl">
                    <div class="space-y-2">
                        <span class="material-symbols-outlined text-[36px] text-outline">add_photo_alternate</span>
                        <p class="text-sm font-medium text-on-surface">Pilih atau ambil foto daftar belanja (.jpg, .png, .webp, max 5MB)</p>
                    </div>
                </template>

                <template x-if="previewUrl">
                    <div class="space-y-3">
                        <img :src="previewUrl" alt="Preview daftar belanja" class="mx-auto max-h-64 rounded-lg border border-divider object-contain">
                        <button type="button" @click="clearFile()" class="text-xs font-semibold text-red-600 hover:underline cursor-pointer">
                            Remove / Replace Image
                        </button>
                    </div>
                </template>

                <input
                    x-ref="imageInput"
                    type="file"
                    name="image_file"
                    accept="image/jpeg,image/png,image/webp"
                    capture="environment"
                    required
                    @change="handleFile($event)"
                    class="mx-auto block text-xs text-on-surface file:mr-3 file:rounded-md file:border-0 file:bg-primary-container file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-on-primary"
                >
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-divider">
                <x-button variant="secondary" :href="route('pos.index')">Cancel</x-button>
                <x-button type="submit" variant="primary">
                    <span class="material-symbols-outlined text-[18px]">document_scanner</span>
                    <span>Scan &amp; Extract Order</span>
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
