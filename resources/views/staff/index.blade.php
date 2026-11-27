@extends('layouts.app', [
    'pageTitle' => 'Staff Management',
    'breadcrumb' => 'Staff Management',
])

@section('title', 'Staff Management')

@section('content')
<div x-data="{ editStaff: null }" class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Left: Add Staff Form (4 cols) -->
        <div class="lg:col-span-4 rounded-xl bg-surface-container-lowest border border-divider/80 p-5 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-primary">Add Staff Member</h2>
                <p class="text-xs text-on-surface-variant">Register employees who pick or prepare customer orders. Staff ID is generated automatically.</p>
            </div>

            <form method="POST" action="{{ route('staff.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Full Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Andi" class="w-full rounded-lg border border-divider px-3 py-2 text-sm focus:border-primary focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Phone Number <span class="font-normal text-outline">(Optional)</span></label>
                    <input type="text" name="phone" placeholder="0812..." class="w-full rounded-lg border border-divider px-3 py-2 text-sm focus:border-primary focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Notes <span class="font-normal text-outline">(Optional)</span></label>
                    <textarea name="notes" rows="2" placeholder="Optional notes..." class="w-full rounded-lg border border-divider px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
                </div>
                <label class="flex items-center gap-2 text-xs font-medium text-on-surface cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-divider text-primary">
                    <span>Active (available during checkout)</span>
                </label>
                <x-button type="submit" variant="primary" class="w-full">Add Staff</x-button>
            </form>
        </div>

        <!-- Right: Staff List (8 cols) -->
        <div class="lg:col-span-8 rounded-xl bg-surface-container-lowest border border-divider/80 p-5 space-y-4">
            <form method="GET" action="{{ route('staff.index') }}" class="flex flex-wrap items-center gap-2.5">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by Staff ID, name, or phone..." class="flex-1 min-w-[200px] rounded-lg border border-divider px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <select name="status" class="rounded-lg border border-divider px-3 py-2 text-sm">
                    <option value="all" @selected($status === 'all')>All Statuses</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
                <x-button type="submit" variant="primary" size="sm">Filter</x-button>
                <x-button variant="secondary" size="sm" :href="route('staff.index')">Reset</x-button>
            </form>

            @if ($staffList->isEmpty())
                <x-empty-state title="No Staff Found" description="Add staff members on the left to assign them during POS checkout." />
            @else
                <x-table :headers="['Staff ID', 'Full Name', 'Phone', 'Status', 'Notes', 'Actions']">
                    @foreach ($staffList as $staff)
                        <tr class="hover:bg-surface-container-low/40">
                            <td class="py-2.5 px-3 font-mono text-xs font-semibold text-primary">{{ $staff->staff_code }}</td>
                            <td class="py-2.5 px-3 text-sm font-semibold text-on-surface">{{ $staff->name }}</td>
                            <td class="py-2.5 px-3 text-xs text-on-surface-variant">{{ $staff->phone ?: '—' }}</td>
                            <td class="py-2.5 px-3">
                                <x-status-badge :status="$staff->is_active ? 'completed' : 'cancelled'" :label="$staff->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="py-2.5 px-3 text-xs text-on-surface-variant">{{ $staff->notes ?: '—' }}</td>
                            <td class="py-2.5 px-3">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="editStaff = {{ Js::from($staff) }}"
                                        class="rounded border border-divider bg-white px-2.5 py-1 text-xs font-semibold text-primary hover:bg-surface-container-low cursor-pointer"
                                    >
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('staff.toggle-active', $staff) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded border border-divider bg-white px-2.5 py-1 text-xs font-semibold {{ $staff->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} cursor-pointer">
                                            {{ $staff->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
                <div class="mt-4">{{ $staffList->links() }}</div>
            @endif
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div x-show="editStaff !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
        <div @click.away="editStaff = null" class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-divider pb-3">
                <h3 class="text-base font-semibold text-primary">Edit Staff Member</h3>
                <button type="button" @click="editStaff = null" class="text-outline hover:text-primary cursor-pointer">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <template x-if="editStaff">
                <form method="POST" :action="'/staff/' + editStaff.id" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Staff ID</label>
                        <input type="text" :value="editStaff.staff_code" disabled class="w-full rounded-lg border border-divider bg-surface-container-low px-3 py-2 text-xs font-mono text-outline">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Full Name *</label>
                        <input type="text" name="name" x-model="editStaff.name" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Phone Number</label>
                        <input type="text" name="phone" x-model="editStaff.phone" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Notes</label>
                        <textarea name="notes" x-model="editStaff.notes" rows="2" class="w-full rounded-lg border border-divider px-3 py-2 text-sm"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-medium text-on-surface cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" x-model="editStaff.is_active" class="rounded border-divider text-primary">
                        <span>Active</span>
                    </label>
                    <div class="flex justify-end gap-2 pt-2 border-t border-divider">
                        <button type="button" @click="editStaff = null" class="rounded-lg border border-divider px-3 py-1.5 text-xs font-semibold">Cancel</button>
                        <button type="submit" class="rounded-lg bg-primary-container text-on-primary px-3.5 py-1.5 text-xs font-semibold cursor-pointer">Save Changes</button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>
@endsection
