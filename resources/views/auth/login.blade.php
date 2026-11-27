@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
<main class="min-h-screen w-full grid grid-cols-1 lg:grid-cols-12">
    <!-- Left Section: Branding & System Preview -->
    <section class="lg:col-span-6 xl:col-span-7 bg-gradient-to-br from-primary to-primary-container text-on-primary relative overflow-hidden flex flex-col justify-between p-6 sm:p-10 lg:p-12 xl:p-16">
        <div class="bg-subtle-grid absolute inset-0 pointer-events-none"></div>

        <div class="relative z-10 max-w-xl mx-auto lg:mx-0 w-full flex flex-col gap-8">
            <!-- Brand Header & System Chip -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-lg bg-surface-container-lowest p-2 flex items-center justify-center shrink-0 text-primary">
                        <svg class="w-full h-full" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="12" rx="2" />
                            <path d="M7 20h10M9 16v4M15 16v4" />
                        </svg>
                    </div>
                    <span class="text-xl font-semibold text-on-primary tracking-tight">AI-Assisted POS</span>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full bg-surface-container-lowest/10 border border-on-primary-container/30 text-xs font-medium text-primary-fixed">
                    Retail Precision · UMKM
                </span>
            </div>

            <!-- Headline & Supporting Text -->
            <div class="space-y-3 pt-2">
                <h1 class="text-2xl sm:text-[32px] sm:leading-[40px] font-semibold text-on-primary tracking-tight">
                    Smart &amp; Faster Transactions for UMKM
                </h1>
                <p class="text-sm sm:text-base leading-6 text-primary-fixed-dim max-w-lg">
                    A smarter way to manage retail transactions using modern technology.
                </p>
            </div>

            <!-- Retail / POS Visual Workspace Card -->
            <div class="rounded-xl bg-surface-container-lowest/10 border border-on-primary-container/25 p-5 space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary-fixed">storefront</span>
                        <span class="text-xs font-medium text-primary-fixed">Toko Sembako · Retail Workspace</span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-tertiary-container text-tertiary-fixed text-[11px] font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-tertiary-fixed"></span>
                        POS Ready
                    </span>
                </div>

                <!-- Clean POS Illustration -->
                <div class="rounded-lg border border-on-primary-container/20 bg-primary/45 p-4">
                    <svg viewBox="0 0 480 155" class="w-full h-auto" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Retail POS register interface illustration">
                        <rect x="4" y="4" width="290" height="147" rx="10" fill="#0A254D" stroke="#485E89" stroke-opacity="0.45"/>
                        <rect x="16" y="16" width="266" height="22" rx="5" fill="#243B64"/>
                        <circle cx="28" cy="27" r="3.5" fill="#9BF6B2"/>
                        <rect x="38" y="24" width="84" height="6" rx="3" fill="#D7E2FF"/>
                        <rect x="218" y="22" width="54" height="10" rx="5" fill="#004521"/>
                        <rect x="226" y="25.5" width="38" height="3" rx="1.5" fill="#80D998"/>

                        <rect x="16" y="48" width="82" height="44" rx="6" fill="#243B64" stroke="#485E89" stroke-opacity="0.5"/>
                        <rect x="24" y="57" width="44" height="5" rx="2.5" fill="#D7E2FF"/>
                        <rect x="24" y="68" width="30" height="4" rx="2" fill="#90A6D5"/>
                        <rect x="24" y="79" width="48" height="6" rx="3" fill="#A8CCFF" fill-opacity="0.4"/>

                        <rect x="108" y="48" width="82" height="44" rx="6" fill="#243B64" stroke="#A8CCFF" stroke-opacity="0.65"/>
                        <rect x="116" y="57" width="48" height="5" rx="2.5" fill="#FFFFFF"/>
                        <rect x="116" y="68" width="34" height="4" rx="2" fill="#B0C7F8"/>
                        <rect x="116" y="79" width="48" height="6" rx="3" fill="#9BF6B2" fill-opacity="0.65"/>

                        <rect x="200" y="48" width="82" height="44" rx="6" fill="#243B64" stroke="#485E89" stroke-opacity="0.5"/>
                        <rect x="208" y="57" width="42" height="5" rx="2.5" fill="#D7E2FF"/>
                        <rect x="208" y="68" width="28" height="4" rx="2" fill="#90A6D5"/>
                        <rect x="208" y="79" width="48" height="6" rx="3" fill="#A8CCFF" fill-opacity="0.4"/>

                        <rect x="16" y="102" width="266" height="38" rx="6" fill="#243B64"/>
                        <path d="M28 130 L74 121 L120 125 L166 113 L212 118 L258 108 L272 112" stroke="#A8CCFF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="166" cy="113" r="3.5" fill="#9BF6B2"/>
                        <circle cx="258" cy="108" r="3.5" fill="#FFFFFF"/>

                        <!-- Receipt Panel -->
                        <rect x="308" y="8" width="168" height="139" rx="10" fill="#FFFFFF"/>
                        <rect x="322" y="22" width="70" height="7" rx="3.5" fill="#0A254D"/>
                        <rect x="322" y="34" width="96" height="4.5" rx="2.25" fill="#74777F"/>
                        <line x1="322" y1="48" x2="462" y2="48" stroke="#E5E8ED" stroke-dasharray="3 3"/>
                        <rect x="322" y="58" width="76" height="5" rx="2.5" fill="#44474E"/>
                        <rect x="422" y="58" width="40" height="5" rx="2.5" fill="#0A254D"/>
                        <rect x="322" y="72" width="66" height="5" rx="2.5" fill="#44474E"/>
                        <rect x="422" y="72" width="40" height="5" rx="2.5" fill="#0A254D"/>
                        <line x1="322" y1="88" x2="462" y2="88" stroke="#E5E8ED" stroke-dasharray="3 3"/>
                        <rect x="322" y="98" width="46" height="6" rx="3" fill="#74777F"/>
                        <rect x="410" y="97" width="52" height="8" rx="4" fill="#243B64"/>
                        <rect x="322" y="116" width="140" height="20" rx="6" fill="#EBF7EE"/>
                        <rect x="358" y="123" width="68" height="5" rx="2.5" fill="#1B7A43"/>
                    </svg>
                </div>
            </div>

            <!-- Feature Bullets -->
            <ul class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                <li class="flex items-center gap-2 text-[13px] text-primary-fixed">
                    <span class="material-symbols-outlined text-[18px] text-tertiary-fixed-dim shrink-0">monitoring</span>
                    <span>Real-time sales KPI</span>
                </li>
                <li class="flex items-center gap-2 text-[13px] text-primary-fixed">
                    <span class="material-symbols-outlined text-[18px] text-tertiary-fixed-dim shrink-0">inventory_2</span>
                    <span>Low-stock monitoring</span>
                </li>
                <li class="flex items-center gap-2 text-[13px] text-primary-fixed">
                    <span class="material-symbols-outlined text-[18px] text-tertiary-fixed-dim shrink-0">badge</span>
                    <span>Admin &amp; Cashier roles</span>
                </li>
            </ul>
        </div>

        <div class="relative z-10 hidden lg:flex items-center justify-between pt-8 text-on-primary-container text-xs font-medium">
            <span>Secure retail point-of-sale workspace</span>
            <span>UMKM Thesis Project</span>
        </div>
    </section>

    <!-- Right Section: Login Form -->
    <section class="lg:col-span-6 xl:col-span-5 bg-surface-container-lowest flex flex-col justify-between p-6 sm:p-10 lg:p-12 xl:p-16">
        <div
            class="max-w-md w-full mx-auto my-auto py-4 space-y-6"
            x-data="{
                showPassword: false,
                selectedRole: '{{ old('email') === 'cashier1@pos.test' ? 'cashier' : 'administrator' }}',
                selectRole(role) {
                    this.selectedRole = role;
                    const emailInput = document.getElementById('email');
                    if (emailInput) {
                        emailInput.value = role === 'administrator' ? 'admin@pos.test' : 'cashier1@pos.test';
                    }
                }
            }"
        >
            <!-- Mobile Brand Header -->
            <div class="flex items-center gap-3 lg:hidden">
                <div class="w-10 h-10 rounded-lg bg-primary-container text-on-primary flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="12" rx="2" />
                        <path d="M7 20h10M9 16v4M15 16v4" />
                    </svg>
                </div>
                <div>
                    <p class="text-base font-semibold text-primary">AI-Assisted POS</p>
                    <p class="text-xs text-on-surface-variant">Smart &amp; Faster Transactions for UMKM</p>
                </div>
            </div>

            <!-- Form Header -->
            <div class="space-y-1.5">
                <h2 class="text-2xl font-semibold tracking-[-0.01em] text-on-surface">Welcome Back</h2>
                <p class="text-[13px] text-on-surface-variant">Sign in with your store credentials to continue.</p>
            </div>

            <!-- Dev Role Email Helper Pills (Fills Email Only) -->
            <div class="space-y-2">
                <span class="block text-xs font-medium text-on-surface-variant">Quick-fill account email (Dev)</span>
                <div class="grid grid-cols-2 gap-2 p-1 rounded-lg bg-surface-container-low border border-outline-variant/70">
                    <button
                        type="button"
                        @click="selectRole('administrator')"
                        :class="selectedRole === 'administrator' ? 'bg-surface-container-lowest text-primary border border-outline-variant/60 shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                        class="min-h-[40px] rounded-md px-3 py-1.5 text-[13px] font-medium flex items-center justify-center gap-2 transition-colors cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px]">admin_panel_settings</span>
                        <span>Administrator</span>
                    </button>
                    <button
                        type="button"
                        @click="selectRole('cashier')"
                        :class="selectedRole === 'cashier' ? 'bg-surface-container-lowest text-primary border border-outline-variant/60 shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                        class="min-h-[40px] rounded-md px-3 py-1.5 text-[13px] font-medium flex items-center justify-center gap-2 transition-colors cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
                        <span>Cashier</span>
                    </button>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-error bg-error-container px-3.5 py-2.5 flex items-start gap-2.5" role="alert">
                    <span class="material-symbols-outlined text-[18px] text-on-error-container shrink-0 mt-0.5">error</span>
                    <div class="text-[13px] text-on-error-container space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Real Laravel Login Form -->
            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
                @csrf

                <!-- Email Field -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-on-surface" for="email">Email address</label>
                    <div class="relative">
                        <span class="material-symbols-outlined text-[20px] text-on-surface-variant absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">mail</span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            autofocus
                            value="{{ old('email') }}"
                            placeholder="admin@pos.test"
                            class="w-full min-h-[44px] pl-11 pr-4 py-2.5 rounded-lg border bg-surface-container-lowest text-on-surface text-sm font-medium placeholder:text-outline focus:outline-none focus:ring-2 transition-colors {{ $errors->has('email') ? 'border-error focus:border-error focus:ring-error-container' : 'border-outline-variant focus:border-primary-container focus:ring-primary-fixed' }}"
                        >
                    </div>
                    @error('email')
                        <p class="text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Field + Visibility Toggle -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-on-surface" for="password">Password</label>
                    <div class="relative">
                        <span class="material-symbols-outlined text-[20px] text-on-surface-variant absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">lock</span>
                        <input
                            id="password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            placeholder="Enter your password"
                            class="w-full min-h-[44px] pl-11 pr-12 py-2.5 rounded-lg border bg-surface-container-lowest text-on-surface text-sm font-medium placeholder:text-outline focus:outline-none focus:ring-2 transition-colors {{ $errors->has('password') ? 'border-error focus:border-error focus:ring-error-container' : 'border-outline-variant focus:border-primary-container focus:ring-primary-fixed' }}"
                        >
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            class="min-w-[44px] min-h-[44px] absolute right-0 top-1/2 -translate-y-1/2 flex items-center justify-center text-on-surface-variant hover:text-on-surface rounded-r-lg focus:outline-none focus:ring-2 focus:ring-primary-fixed cursor-pointer"
                        >
                            <span class="material-symbols-outlined text-[20px]" x-text="showPassword ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between pt-0.5">
                    <label for="remember" class="inline-flex items-center gap-2.5 cursor-pointer select-none">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            value="1"
                            {{ old('remember') ? 'checked' : '' }}
                            class="w-4 h-4 rounded border-outline-variant text-primary-container focus:ring-primary-fixed"
                        >
                        <span class="text-[13px] text-on-surface-variant">Remember me</span>
                    </label>
                </div>

                <!-- Primary Submit Button -->
                <button
                    type="submit"
                    class="w-full min-h-[44px] px-4 py-2.5 rounded-lg bg-primary-container hover:bg-primary text-on-primary text-sm font-semibold flex items-center justify-center gap-2 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-fixed focus:ring-offset-2 cursor-pointer"
                >
                    <span>Sign In</span>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </form>
        </div>

        <footer class="pt-6 text-center">
            <p class="text-xs font-medium text-on-surface-variant">
                AI-Assisted POS &middot; Retail Management System &middot; UMKM Thesis Project
            </p>
        </footer>
    </section>
</main>
@endsection
