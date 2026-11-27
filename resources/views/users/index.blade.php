@extends('layouts.app', [
    'pageTitle' => 'Users',
    'breadcrumb' => 'Users',
])

@section('title', 'Users')

@section('content')
<div
    x-data="{
        showCreateModal: false,
        editingUser: null,
        openEdit(user) {
            this.editingUser = { ...user };
        }
    }"
    class="space-y-6"
>
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)]">
        <div class="p-5 border-b border-divider flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-center gap-2.5 flex-1">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search name or email..."
                    class="rounded-lg border border-divider bg-white px-3.5 py-2 text-sm focus:border-primary focus:outline-none min-w-[200px]"
                >
                <select name="role" onchange="this.form.submit()" class="rounded-lg border border-divider bg-white px-3 py-2 text-sm">
                    <option value="all" @selected($role === 'all')>All Roles</option>
                    <option value="administrator" @selected($role === 'administrator')>Administrator</option>
                    <option value="cashier" @selected($role === 'cashier')>Cashier</option>
                </select>
                <x-button type="submit" variant="secondary">Filter</x-button>
            </form>

            <x-button type="button" variant="primary" @click="showCreateModal = true">
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                <span>Add User</span>
            </x-button>
        </div>

        <div class="p-5">
            <x-table :headers="['Name', 'Email', 'Role', 'Transactions', 'Status', 'Actions']">
                @foreach ($users as $user)
                    <tr class="hover:bg-surface-container-low/40">
                        <td class="py-3 px-3 font-semibold text-on-surface">{{ $user->name }}</td>
                        <td class="py-3 px-3 text-on-surface-variant">{{ $user->email }}</td>
                        <td class="py-3 px-3"><x-status-badge :status="$user->role" :label="$user->roleLabel()" /></td>
                        <td class="py-3 px-3 tabular-nums">{{ $user->transactions_count }}</td>
                        <td class="py-3 px-3"><x-status-badge :status="$user->is_active ? 'active' : 'inactive'" /></td>
                        <td class="py-3 px-3">
                            <button
                                type="button"
                                @click="openEdit({{ \Illuminate\Support\Js::from([
                                    'id' => $user->id,
                                    'name' => $user->name,
                                    'email' => $user->email,
                                    'role' => $user->role,
                                    'is_active' => $user->is_active,
                                ]) }})"
                                class="inline-flex items-center justify-center rounded-lg font-medium transition-colors duration-150 bg-surface-container-lowest text-on-surface hover:bg-surface-container-low border border-divider px-3 h-8 text-xs cursor-pointer"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div class="mt-4">{{ $users->links() }}</div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
        <div @click.away="showCreateModal = false" class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                <h3 class="text-base font-semibold text-primary">Create User Account</h3>
                <button type="button" @click="showCreateModal = false" class="text-outline hover:text-primary">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <form method="POST" action="{{ route('users.store') }}" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Full Name</label>
                    <input type="text" name="name" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Email</label>
                    <input type="email" name="email" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Role</label>
                    <select name="role" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                        <option value="cashier">Cashier</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Password</label>
                        <input type="password" name="password" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Confirm</label>
                        <input type="password" name="password_confirmation" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-divider text-primary">
                    <span>Active account</span>
                </label>
                <div class="pt-3 border-t border-divider flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="showCreateModal = false">Cancel</x-button>
                    <x-button type="submit" variant="primary">Create Account</x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div x-show="editingUser !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
        <div @click.away="editingUser = null" class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                <h3 class="text-base font-semibold text-primary">Edit User</h3>
                <button type="button" @click="editingUser = null" class="text-outline hover:text-primary">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <form method="POST" :action="'{{ url('/users') }}/' + (editingUser ? editingUser.id : '')" class="p-5 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Full Name</label>
                    <input type="text" name="name" x-model="editingUser.name" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Email</label>
                    <input type="email" name="email" x-model="editingUser.email" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Role</label>
                    <select name="role" x-model="editingUser.role" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                        <option value="cashier">Cashier</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">New Password (Optional)</label>
                        <input type="password" name="password" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Confirm</label>
                        <input type="password" name="password_confirmation" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" :checked="editingUser && editingUser.is_active" class="rounded border-divider text-primary">
                    <span>Active account</span>
                </label>
                <div class="pt-3 border-t border-divider flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="editingUser = null">Cancel</x-button>
                    <x-button type="submit" variant="primary">Update User</x-button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
