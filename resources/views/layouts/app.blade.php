<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name', 'AI-Assisted POS') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0..1,0&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased selection:bg-primary-fixed selection:text-on-primary-fixed">
    <div
        x-data="{
            sidebarCollapsed: false,
            mobileSidebarOpen: false,
        }"
        @keydown.escape.window="mobileSidebarOpen = false"
        class="min-h-screen flex bg-surface"
    >
        <x-sidebar :user="auth()->user()" />

        <div
            class="flex-1 flex flex-col min-w-0 transition-all duration-200"
            :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-[240px]'"
        >
            <x-navbar
                :user="auth()->user()"
                :page-title="$pageTitle ?? 'Dashboard'"
                :breadcrumb="$breadcrumb ?? 'Dashboard'"
            />

            <main class="flex-1 px-4 sm:px-6 py-6 w-full max-w-[1440px] mx-auto">
                @yield('content')
            </main>

            <footer class="w-full bg-surface-container-lowest border-t border-divider py-3 px-4 sm:px-6">
                <div class="max-w-[1440px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 text-xs font-medium text-on-surface-variant">
                    <span>&copy; {{ date('Y') }} AI-Assisted POS UMKM Indonesia.</span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-tertiary-fixed-dim inline-block"></span>
                        Retail Precision UI &bull; Local Workspace
                    </span>
                </div>
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
