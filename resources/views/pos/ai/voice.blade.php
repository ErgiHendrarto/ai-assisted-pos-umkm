@extends('layouts.app', [
    'pageTitle' => 'Voice Order (AI)',
    'breadcrumb' => 'POS / Voice Order',
])

@section('title', 'Voice Order')

@section('content')
<div
    x-data="{
        recording: false,
        seconds: 0,
        timer: null,
        mediaRecorder: null,
        chunks: [],
        audioReady: false,
        errorMessage: '',
        async startRecording() {
            this.errorMessage = '';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.errorMessage = 'Browser Anda tidak mendukung perekaman mikrofon langsung. Silakan unggah file audio atau ketik teks pesanan di bawah.';
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.chunks = [];
                this.mediaRecorder = new MediaRecorder(stream);
                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) this.chunks.push(e.data);
                };
                this.mediaRecorder.onstop = () => {
                    const blob = new Blob(this.chunks, { type: 'audio/webm' });
                    const file = new File([blob], 'voice-order.webm', { type: 'audio/webm' });
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    this.$refs.audioInput.files = dt.files;
                    this.audioReady = true;
                    stream.getTracks().forEach(t => t.stop());
                };
                this.mediaRecorder.start();
                this.recording = true;
                this.seconds = 0;
                this.timer = setInterval(() => this.seconds++, 1000);
            } catch (err) {
                this.errorMessage = 'Akses mikrofon ditolak atau tidak tersedia.';
            }
        },
        stopRecording() {
            if (this.mediaRecorder && this.recording) {
                this.mediaRecorder.stop();
                this.recording = false;
                clearInterval(this.timer);
            }
        }
    }"
    class="max-w-3xl space-y-6"
>
    @if (! $aiAvailable)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 flex items-start gap-2.5">
            <span class="material-symbols-outlined text-[18px] text-amber-600 mt-0.5">warning</span>
            <div>
                <p class="font-semibold">Layanan AI Voice belum aktif atau API Key belum diisi.</p>
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
                <h2 class="text-base font-semibold text-primary">Voice Order Input (Speech Recognition + LLM)</h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Contoh: "Masukkan Indomie goreng tiga, Aqua botol dua, gula pasir satu kilo."</p>
            </div>
            <x-button size="sm" variant="secondary" :href="route('pos.index')">Back to POS</x-button>
        </div>

        <form method="POST" action="{{ route('pos.ai.voice.process') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <!-- Microphone Recorder -->
            <div class="rounded-xl border border-divider bg-surface-container-low/50 p-5 text-center space-y-3">
                <div class="flex justify-center">
                    <button
                        type="button"
                        x-show="!recording"
                        @click="startRecording()"
                        class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary-container text-on-primary shadow-md hover:bg-primary transition-colors cursor-pointer"
                        title="Start Recording"
                    >
                        <span class="material-symbols-outlined text-[28px]">mic</span>
                    </button>

                    <button
                        type="button"
                        x-show="recording"
                        x-cloak
                        @click="stopRecording()"
                        class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-red-600 text-white shadow-md hover:bg-red-700 animate-pulse cursor-pointer"
                        title="Stop Recording"
                    >
                        <span class="material-symbols-outlined text-[28px]">stop</span>
                    </button>
                </div>

                <p class="text-sm font-semibold text-primary" x-text="recording ? ('Merekam... ' + seconds + ' detik') : (audioReady ? 'Rekaman audio siap diproses!' : 'Tekan ikon mikrofon untuk merekam pesanan')"></p>
                <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-600"></p>

                <div class="pt-2">
                    <label class="block text-xs font-medium text-on-surface-variant mb-1.5">Atau unggah file audio (.webm, .wav, .mp3, .m4a, max 10MB)</label>
                    <input x-ref="audioInput" type="file" name="audio_file" accept="audio/*" class="mx-auto block text-xs text-on-surface file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary">
                </div>
            </div>

            <!-- Or Direct Text -->
            <div>
                <label for="recognized_text" class="block text-xs font-semibold text-on-surface mb-1.5">
                    Teks Pesanan <span class="font-normal text-outline">(Opsional jika sudah merekam audio, atau ketik langsung)</span>
                </label>
                <textarea
                    id="recognized_text"
                    name="recognized_text"
                    rows="3"
                    placeholder="Contoh: Indomie goreng 3, Aqua 600ml 2 botol, Gula pasir 1 kg"
                    class="w-full rounded-lg border border-divider px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none"
                >{{ old('recognized_text') }}</textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-divider">
                <x-button variant="secondary" :href="route('pos.index')">Cancel</x-button>
                <x-button type="submit" variant="primary">
                    <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                    <span>Process Voice Order</span>
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
