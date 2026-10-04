{{--
    List page body for cities, cuisines and amenities. The items must be loaded with withCount('restaurants').
    <x-admin.name-slug-table :items="$items" route-prefix="admin.cuisines" noun="cuisine" plural="cuisines" />
    features: ['description'] adds a column with the first words of each description (cities);
              ['icon'] adds a column with each icon name (amenities).
    Deleting something that restaurants still use is refused by the controller with a friendly message.
--}}
@props(['items', 'routePrefix', 'noun', 'plural', 'features' => []])

@php
    $withDescription = in_array('description', $features, true);
    $withIcon = in_array('icon', $features, true);
@endphp

<x-admin.table>
    <x-slot:head>
        <tr>
            <th class="px-4 py-3 font-medium">Name</th>
            <th class="px-4 py-3 font-medium">Slug</th>
            @if ($withDescription)
                <th class="px-4 py-3 font-medium">Description</th>
            @endif
            @if ($withIcon)
                <th class="px-4 py-3 font-medium">Icon</th>
            @endif
            <th class="px-4 py-3 font-medium">Restaurants</th>
            <th class="px-4 py-3 text-right font-medium">Actions</th>
        </tr>
    </x-slot:head>

    @forelse ($items as $item)
        <tr>
            <td class="px-4 py-3 font-medium">{{ $item->name }}</td>
            <td class="px-4 py-3 text-cream/60">{{ $item->slug }}</td>
            @if ($withDescription)
                <td class="max-w-xs px-4 py-3 text-cream/60">{{ Str::limit((string) $item->description, 60) ?: '—' }}</td>
            @endif
            @if ($withIcon)
                <td class="px-4 py-3 text-cream/60">{{ $item->icon ?: '—' }}</td>
            @endif
            <td class="px-4 py-3">{{ $item->restaurants_count }}</td>
            <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                    <x-button variant="outline" class="px-3 py-1.5" :href="route($routePrefix.'.edit', $item)">Edit</x-button>
                    <x-admin.delete-form :action="route($routePrefix.'.destroy', $item)" :message="'Delete '.$item->name.'? This cannot be undone.'" />
                </div>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ 4 + (int) $withDescription + (int) $withIcon }}" class="px-4 py-8 text-center text-cream/60">
                No {{ $plural }} yet. Click "Add {{ $noun }}" to create one.
            </td>
        </tr>
    @endforelse
</x-admin.table>
