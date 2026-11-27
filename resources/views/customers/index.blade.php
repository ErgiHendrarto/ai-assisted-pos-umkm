@extends('layouts.app', [
    'pageTitle' => 'Customers',
    'breadcrumb' => 'Customers',
])

@section('title', 'Customers')

@section('content')
<div
    x-data="{
        showCreateModal: false,
        editingCustomer: null,
        openEdit(c) {
            this.editingCustomer = { ...c };
        }
    }"
    class="space-y-6"
>
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

    <div class="rounded-xl bg-surface-container-lowest border border-divider/80 shadow-[0_1px_3px_0_rgba(37,43,54,0.04)]">
        <div class="p-5 border-b border-divider flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <form method="GET" action="{{ route('customers.index') }}" class="flex flex-wrap items-center gap-2.5 flex-1">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search Customer ID, name, or phone..."
                    class="rounded-lg border border-divider bg-white px-3.5 py-2 text-sm min-w-[220px]"
                >
                <x-button type="submit" variant="secondary">Search</x-button>
            </form>

            <div class="flex items-center gap-2">
                <x-button variant="secondary" :href="route('debts.index')">
                    <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
                    <span>Customer Debts</span>
                </x-button>
                <x-button type="button" variant="primary" @click="showCreateModal = true">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    <span>Add Customer</span>
                </x-button>
            </div>
        </div>

        <div class="p-5">
            @if ($customers->isEmpty())
                <x-empty-state title="No Customers Found" description="Add retail customers to track credit/debt transactions." />
            @else
                <x-table :headers="['Customer ID', 'Customer', 'Phone', 'Address', 'Transactions', 'Outstanding Debt', 'Status', 'Actions']">
                    @foreach ($customers as $customer)
                        @php $debt = (float) ($customer->outstanding_debt_sum ?? 0); @endphp
                        <tr class="hover:bg-surface-container-low/40">
                            <td class="py-3 px-3 font-mono text-xs font-semibold text-outline">{{ $customer->customer_code }}</td>
                            <td class="py-3 px-3">
                                <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-primary hover:underline">{{ $customer->name }}</a>
                            </td>
                            <td class="py-3 px-3 text-xs text-on-surface-variant">{{ $customer->phone ?: '—' }}</td>
                            <td class="py-3 px-3 text-xs text-on-surface-variant max-w-xs truncate">{{ $customer->address ?: '—' }}</td>
                            <td class="py-3 px-3 tabular-nums">{{ $customer->transactions_count }}</td>
                            <td class="py-3 px-3 tabular-nums font-semibold {{ $debt > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                {{ \App\Support\Format::rupiah($debt) }}
                            </td>
                            <td class="py-3 px-3"><x-status-badge :status="$customer->is_active ? 'active' : 'inactive'" /></td>
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-2">
                                    <x-button size="sm" variant="secondary" :href="route('customers.show', $customer)">Detail</x-button>
                                    <button
                                        type="button"
                                        @click="openEdit({{ \Illuminate\Support\Js::from([
                                            'id' => $customer->id,
                                            'name' => $customer->name,
                                            'phone' => $customer->phone,
                                            'address' => $customer->address,
                                            'notes' => $customer->notes,
                                            'is_active' => $customer->is_active,
                                        ]) }})"
                                        class="inline-flex items-center justify-center rounded-lg font-medium bg-surface-container-lowest text-on-surface hover:bg-surface-container-low border border-divider px-3 h-8 text-xs cursor-pointer"
                                    >
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
                <div class="mt-4">{{ $customers->links() }}</div>
            @endif
        </div>
    </div>

    <!-- Create Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
        <div @click.away="showCreateModal = false" class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                <h3 class="text-base font-semibold text-primary">Add Customer</h3>
                <button type="button" @click="showCreateModal = false" class="text-outline hover:text-primary"><span class="material-symbols-outlined text-[20px]">close</span></button>
            </div>
            <form method="POST" action="{{ route('customers.store') }}" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold mb-1">Customer Name</label>
                    <input type="text" name="name" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Phone Number</label>
                    <input type="text" name="phone" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Address</label>
                    <textarea name="address" rows="2" class="w-full rounded-lg border border-divider px-3 py-2 text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Notes</label>
                    <input type="text" name="notes" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="rounded border-divider"> Active</label>
                <div class="pt-3 border-t border-divider flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="showCreateModal = false">Cancel</x-button>
                    <x-button type="submit" variant="primary">Save Customer</x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="editingCustomer !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
        <div @click.away="editingCustomer = null" class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                <h3 class="text-base font-semibold text-primary">Edit Customer</h3>
                <button type="button" @click="editingCustomer = null" class="text-outline hover:text-primary"><span class="material-symbols-outlined text-[20px]">close</span></button>
            </div>
            <form method="POST" :action="'{{ url('/customers') }}/' + (editingCustomer ? editingCustomer.id : '')" class="p-5 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold mb-1">Customer Name</label>
                    <input type="text" name="name" x-model="editingCustomer.name" required class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Phone Number</label>
                    <input type="text" name="phone" x-model="editingCustomer.phone" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Address</label>
                    <textarea name="address" rows="2" x-model="editingCustomer.address" class="w-full rounded-lg border border-divider px-3 py-2 text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Notes</label>
                    <input type="text" name="notes" x-model="editingCustomer.notes" class="w-full rounded-lg border border-divider px-3 py-2 text-sm">
                </div>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" :checked="editingCustomer && editingCustomer.is_active" class="rounded border-divider"> Active</label>
                <div class="pt-3 border-t border-divider flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="editingCustomer = null">Cancel</x-button>
                    <x-button type="submit" variant="primary">Update Customer</x-button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
