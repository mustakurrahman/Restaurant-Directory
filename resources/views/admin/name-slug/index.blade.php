{{-- The list page for cities, cuisines and amenities (see Admin\NameSlugController) --}}
<x-admin.layout :title="ucfirst($plural)">
    <x-slot:actions>
        <x-button :href="route($routePrefix.'.create')">Add {{ $noun }}</x-button>
    </x-slot:actions>

    {{-- Search: a GET form, so the search word lives in the address and survives paging --}}
    <form method="GET" action="{{ route($routePrefix.'.index') }}" class="mb-6 flex max-w-xl flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <x-input name="q" :label="'Search '.$plural" type="search" :value="$search" placeholder="Name or slug" />
        </div>
        <x-button type="submit">Search</x-button>
        @if (filled($search))
            <x-button variant="outline" :href="route($routePrefix.'.index')">Clear</x-button>
        @endif
    </form>

    <p class="mb-3 text-sm text-cream/60">
        {{ $items->total() }} {{ $items->total() === 1 ? $noun : $plural }}@if (filled($search)) matching “{{ $search }}”@endif
    </p>

    <x-admin.name-slug-table :items="$items" :route-prefix="$routePrefix" :noun="$noun" :plural="$plural" :features="$features" />

    <div class="mt-6">
        {{ $items->links() }}
    </div>
</x-admin.layout>
