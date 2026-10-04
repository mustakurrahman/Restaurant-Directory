@php
    $search = request('q');
@endphp

<x-admin.layout title="Cities">
    <x-slot:actions>
        <x-button :href="route('admin.cities.create')">Add city</x-button>
    </x-slot:actions>

    {{-- Search: a GET form, so the search word lives in the address and survives paging --}}
    <form method="GET" action="{{ route('admin.cities.index') }}" class="mb-6 flex max-w-xl flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <x-input name="q" label="Search cities" type="search" :value="$search" placeholder="Name or slug" />
        </div>
        <x-button type="submit">Search</x-button>
        @if (filled($search))
            <x-button variant="outline" :href="route('admin.cities.index')">Clear</x-button>
        @endif
    </form>

    <p class="mb-3 text-sm text-cream/60">
        {{ $cities->total() }} {{ Str::plural('city', $cities->total()) }}@if (filled($search)) matching “{{ $search }}”@endif
    </p>

    <x-admin.name-slug-table :items="$cities" route-prefix="admin.cities" noun="city" plural="cities" with-description />

    <div class="mt-6">
        {{ $cities->links() }}
    </div>
</x-admin.layout>
