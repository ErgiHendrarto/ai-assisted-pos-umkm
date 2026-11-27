@props([
    'user',
    'pageTitle' => 'Dashboard',
    'breadcrumb' => 'Dashboard',
])

<header class="sticky top-0 z-30 h-14 bg-surface-container-lowest/90 backdrop-blur-xl border-b border-divider/80 shadow-[0_1px_8px_rgba(0,0,0,0.04)] flex items-center justify-between gap-4 px-4 sm:px-6">
    <!-- Left: Mobile toggle, Page Title/Breadcrumb, Date & Status -->
    <div class="flex items-center gap-3 min-w-0">
        <button
            type="button"
            @click="mobileSidebarOpen = true"
            class="lg:hidden inline-flex h-9 w-9 items-center justify-center rounded-lg border border-divider bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container-low"
            aria-label="Open navigation menu"
        >
            <span class="material-symbols-outlined text-[20px]">menu</span>
        </button>

        <div class="min-w-0 flex items-center gap-3">
            <div class="min-w-0">
                <h1 class="truncate text-[15px] font-semibold text-primary leading-tight">
                    {{ $pageTitle }}
                </h1>
                <nav class="hidden sm:flex items-center gap-1.5 text-[11px] text-outline" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors">AI-Assisted POS</a>
                    <span>/</span>
                    <span class="font-medium text-on-surface-variant truncate">{{ $breadcrumb }}</span>
                </nav>
            </div>

            <div class="hidden md:flex items-center gap-1.5 bg-surface-container px-2.5 py-1 rounded text-[11px] font-semibold text-on-surface-variant">
                <span class="material-symbols-outlined text-[15px]">calendar_today</span>
                <span>{{ now()->format('D, d M Y') }}</span>
            </div>

            <div class="hidden xl:flex items-center gap-1.5 bg-surface-container-low px-2.5 py-1 rounded-full text-[11px] font-medium text-on-surface">
                <span class="w-2 h-2 rounded-full bg-tertiary-fixed-dim inline-block"></span>
                <span>{{ $user && $user->isAdministrator() ? 'Owner Workspace' : 'Kasir Aktif' }}</span>
            </div>
        </div>
    </div>

    <!-- Right: Notification (UI Only) & User Profile -->
    <div class="flex items-center gap-3 shrink-0">
        <!-- Notification Icon (UI Only) -->
        <div x-data="{ open: false }" class="relative">
            <button
                type="button"
                @click="open = !open"
                @click.outside="open = false"
                class="relative p-1.5 text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors cursor-pointer"
                aria-label="Notifications"
            >
                <span class="material-symbols-outlined text-[22px]">notifications</span>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-error rounded-full"></span>
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition.origin.top.right
                class="absolute right-0 mt-2 w-72 rounded-xl border border-divider bg-surface-container-lowest p-3 shadow-[0_4px_12px_0_rgba(37,43,54,0.08)] z-50"
            >
                <div class="flex items-center justify-between pb-2 border-b border-divider">
                    <span class="text-xs font-semibold text-on-surface">Notifications</span>
                    <span class="text-[11px] font-medium text-outline">UI Preview</span>
                </div>
                <p class="py-4 text-center text-xs text-on-surface-variant">
                    Real-time store alerts will appear here in upcoming phases.
                </p>
            </div>
        </div>

        <!-- User Info -->
        @if ($user)
            <div class="flex items-center gap-2.5 pl-3 border-l border-divider">
                <div class="w-8 h-8 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-xs font-semibold">
                    {{ $user->initials() }}
                </div>
                <div class="hidden sm:flex flex-col text-left leading-tight">
                    <span class="text-xs font-semibold text-on-surface">{{ $user->name }}</span>
                    <span class="text-[11px] font-medium text-secondary">{{ $user->roleLabel() }}</span>
                </div>
            </div>
        @endif
    </div>
</header>
