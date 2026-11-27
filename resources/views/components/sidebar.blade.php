@props([
    'user',
])

@php
    $isAdmin = $user && $user->isAdministrator();

    $navItems = [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'active' => request()->routeIs('dashboard'),
            'visible' => true,
            'icon' => 'dashboard',
        ],
        [
            'label' => 'POS / Transactions',
            'route' => 'pos.index',
            'active' => request()->routeIs('pos.*'),
            'visible' => true,
            'icon' => 'point_of_sale',
        ],
        [
            'label' => 'Products',
            'route' => 'products.index',
            'active' => request()->routeIs('products.*'),
            'visible' => $isAdmin,
            'icon' => 'inventory_2',
        ],
        [
            'label' => 'Categories',
            'route' => 'categories.index',
            'active' => request()->routeIs('categories.*'),
            'visible' => $isAdmin,
            'icon' => 'category',
        ],
        [
            'label' => 'Inventory',
            'route' => 'inventory.index',
            'active' => request()->routeIs('inventory.*'),
            'visible' => $isAdmin,
            'icon' => 'warehouse',
        ],
        [
            'label' => 'Transaction History',
            'route' => 'transactions.index',
            'active' => request()->routeIs('transactions.*'),
            'visible' => true,
            'icon' => 'receipt_long',
        ],
        [
            'label' => 'Customers',
            'route' => 'customers.index',
            'active' => request()->routeIs('customers.*'),
            'visible' => true,
            'icon' => 'contacts',
        ],
        [
            'label' => 'Customer Debts',
            'route' => 'debts.index',
            'active' => request()->routeIs('debts.*'),
            'visible' => true,
            'icon' => 'account_balance_wallet',
        ],
        [
            'label' => 'Sales Reports',
            'route' => 'reports.index',
            'active' => request()->routeIs('reports.*'),
            'visible' => $isAdmin,
            'icon' => 'bar_chart',
        ],
        [
            'label' => 'AI History',
            'route' => 'ai-history.index',
            'active' => request()->routeIs('ai-history.*'),
            'visible' => $isAdmin,
            'icon' => 'smart_toy',
        ],
        [
            'label' => 'Staff',
            'route' => 'staff.index',
            'active' => request()->routeIs('staff.*'),
            'visible' => $isAdmin,
            'icon' => 'badge',
        ],
        [
            'label' => 'Users',
            'route' => 'users.index',
            'active' => request()->routeIs('users.*'),
            'visible' => $isAdmin,
            'icon' => 'group',
        ],
        [
            'label' => 'Settings',
            'route' => 'settings.index',
            'active' => request()->routeIs('settings.*'),
            'visible' => true,
            'icon' => 'settings',
        ],
    ];
@endphp

<!-- Mobile Backdrop -->
<div
    x-show="mobileSidebarOpen"
    x-cloak
    x-transition.opacity.duration.200ms
    @click="mobileSidebarOpen = false"
    class="fixed inset-0 z-40 bg-primary/35 backdrop-blur-[2px] lg:hidden"
    aria-hidden="true"
></div>

<!-- Retail Precision Sidebar -->
<aside
    :class="[
        mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        sidebarCollapsed ? 'lg:w-20' : 'lg:w-[240px]'
    ]"
    class="fixed inset-y-0 left-0 z-50 flex w-[240px] flex-col justify-between bg-surface-container-lowest border-r border-divider/80 shadow-[0_1px_8px_rgba(0,0,0,0.04)] transition-all duration-200"
    aria-label="Sidebar Navigation"
>
    <div class="flex flex-col min-h-0">
        <!-- Brand Header -->
        <div class="h-14 px-4 flex items-center justify-between gap-2 border-b border-divider/60">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-container text-on-primary">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="12" rx="2" />
                        <path d="M7 20h10M9 16v4M15 16v4" />
                    </svg>
                </div>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate text-[16px] font-semibold tracking-[-0.005em] text-primary">
                    AI-Assisted POS
                </span>
            </a>

            <!-- Desktop Collapse Toggle -->
            <button
                type="button"
                @click="sidebarCollapsed = !sidebarCollapsed"
                class="hidden lg:inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-outline hover:bg-surface-container-low hover:text-primary transition-colors cursor-pointer"
                :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                aria-label="Toggle sidebar width"
            >
                <span
                    class="material-symbols-outlined text-[18px] transition-transform duration-200"
                    :class="sidebarCollapsed ? 'rotate-180' : ''"
                >chevron_left</span>
            </button>

            <!-- Mobile Close Button -->
            <button
                type="button"
                @click="mobileSidebarOpen = false"
                class="lg:hidden inline-flex h-8 w-8 items-center justify-center rounded-md text-outline hover:bg-surface-container-low hover:text-primary"
                aria-label="Close navigation menu"
            >
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Section Label -->
        <div x-show="!sidebarCollapsed" x-cloak class="px-4 pt-4 pb-1.5">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-outline">
                Menu Utama
            </span>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto px-3 py-2 flex flex-col gap-1">
            @foreach ($navItems as $item)
                @if ($item['visible'])
                    <a
                        href="{{ route($item['route']) }}"
                        @click="mobileSidebarOpen = false"
                        @if ($item['active']) aria-current="page" @endif
                        :title="sidebarCollapsed ? '{{ $item['label'] }}' : ''"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-[14px] font-medium transition-colors {{ $item['active'] ? 'bg-primary-container text-on-primary' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}"
                    >
                        <span class="material-symbols-outlined text-[20px] shrink-0">{{ $item['icon'] }}</span>
                        <span x-show="!sidebarCollapsed" x-cloak class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
    </div>

    <!-- Bottom User Card + Logout -->
    <div class="p-3">
        <div class="p-2.5 bg-surface-container-low rounded-xl border border-divider/70 flex items-center justify-between gap-2">
            @if ($user)
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 shrink-0 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-xs font-semibold">
                        {{ $user->initials() }}
                    </div>
                    <div x-show="!sidebarCollapsed" x-cloak class="flex flex-col min-w-0">
                        <span class="text-xs font-semibold text-on-surface truncate leading-tight">{{ $user->name }}</span>
                        <span class="text-[11px] font-medium text-outline truncate leading-tight">{{ $user->roleLabel() }}</span>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button
                    type="submit"
                    title="Logout"
                    aria-label="Logout"
                    class="p-1.5 text-on-surface-variant hover:text-error hover:bg-error-container rounded-lg transition-colors flex items-center justify-center cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[20px]">logout</span>
                </button>
            </form>
        </div>
    </div>
</aside>
