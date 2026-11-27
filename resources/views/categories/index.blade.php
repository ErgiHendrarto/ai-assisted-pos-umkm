@extends('layouts.app', [
    'pageTitle' => 'Categories',
    'breadcrumb' => 'Categories',
])

@section('title', 'Categories')

@section('content')
<div
    x-data="{
        showCreateModal: false,
        editingCategory: null,
        openEdit(cat) {
            this.editingCategory = { ...cat };
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
            <form method="GET" action="{{ route('categories.index') }}" class="flex flex-wrap items-center gap-2.5 flex-1">
                <div class="relative flex-1 min-w-[200px] max-w-xs">
                    <span class="material-symbols-outlined text-[18px] text-outline absolute left-3 top-1/2 -translate-y-1/2">search</span>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search categories..."
                        class="w-full rounded-lg border border-divider bg-white pl-9 pr-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/15"
                    >
                </div>
                <select
                    name="status"
                    onchange="this.form.submit()"
                    class="rounded-lg border border-divider bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                >
                    <option value="all" @selected($status === 'all')>All Status</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
                <x-button type="submit" variant="secondary">Filter</x-button>
            </form>

            <x-button type="button" variant="primary" @click="showCreateModal = true">
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Add Category</span>
            </x-button>
        </div>

        <div class="p-5">
            @if ($categories->isEmpty())
                <x-empty-state
                    title="No Categories Found"
                    description="Create a retail category to organize your store products."
                />
            @else
                <x-table :headers="['Category Name', 'Description', 'Products', 'Status', 'Actions']">
                    @foreach ($categories as $category)
                        <tr class="hover:bg-surface-container-low/40 transition-colors">
                            <td class="py-3 px-3 font-semibold text-on-surface">{{ $category->name }}</td>
                            <td class="py-3 px-3 text-on-surface-variant max-w-md">{{ $category->description ?: '—' }}</td>
                            <td class="py-3 px-3 tabular-nums font-medium text-on-surface">{{ $category->products_count }}</td>
                            <td class="py-3 px-3">
                                <x-status-badge :status="$category->is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEdit({{ \Illuminate\Support\Js::from([
                                            'id' => $category->id,
                                            'name' => $category->name,
                                            'description' => $category->description,
                                            'is_active' => $category->is_active,
                                        ]) }})"
                                        class="inline-flex items-center justify-center rounded-lg font-medium transition-colors duration-150 bg-surface-container-lowest text-on-surface hover:bg-surface-container-low border border-divider px-3 h-8 text-xs cursor-pointer"
                                    >
                                        Edit
                                    </button>
                                    @if ($category->products_count === 0)
                                        <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Hapus kategori ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" size="sm" variant="danger">Delete</x-button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>

                <div class="mt-4">
                    {{ $categories->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Create Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
        <div @click.away="showCreateModal = false" class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                <h3 class="text-base font-semibold text-primary">Add Category</h3>
                <button type="button" @click="showCreateModal = false" class="text-outline hover:text-primary">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <form method="POST" action="{{ route('categories.store') }}" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Category Name</label>
                    <input type="text" name="name" required class="w-full rounded-lg border border-divider px-3.5 py-2 text-sm focus:border-primary focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-lg border border-divider px-3.5 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-divider text-primary">
                    <span>Active category</span>
                </label>
                <div class="pt-3 border-t border-divider flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="showCreateModal = false">Cancel</x-button>
                    <x-button type="submit" variant="primary">Save Category</x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="editingCategory !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 p-4">
        <div @click.away="editingCategory = null" class="w-full max-w-md rounded-xl bg-white border border-divider shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-divider flex items-center justify-between">
                <h3 class="text-base font-semibold text-primary">Edit Category</h3>
                <button type="button" @click="editingCategory = null" class="text-outline hover:text-primary">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <form method="POST" :action="'{{ url('/categories') }}/' + (editingCategory ? editingCategory.id : '')" class="p-5 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Category Name</label>
                    <input type="text" name="name" x-model="editingCategory.name" required class="w-full rounded-lg border border-divider px-3.5 py-2 text-sm focus:border-primary focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Description</label>
                    <textarea name="description" rows="3" x-model="editingCategory.description" class="w-full rounded-lg border border-divider px-3.5 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" :checked="editingCategory && editingCategory.is_active" class="rounded border-divider text-primary">
                    <span>Active category</span>
                </label>
                <div class="pt-3 border-t border-divider flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="editingCategory = null">Cancel</x-button>
                    <x-button type="submit" variant="primary">Update Category</x-button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
