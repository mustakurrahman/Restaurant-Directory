{{--
    List page body for cities, cuisines and amenities. The items must be loaded with withCount('restaurants').
    <x-admin.name-slug-table :items="$cuisines" route-prefix="admin.cuisines" noun="cuisine" plural="cuisines" unlink-warning />
    unlink-warning: deleting is allowed, so the confirmation mentions the restaurants that use the item.
--}}
@props(['items', 'routePrefix', 'noun', 'plural', 'unlinkWarning' => false])

<x-admin.table>
    <x-slot:head>
        <tr>
            <th class="px-4 py-3 font-medium">Name</th>
            <th class="px-4 py-3 font-medium">Slug</th>
            <th class="px-4 py-3 font-medium">Restaurants</th>
            <th class="px-4 py-3 text-right font-medium">Actions</th>
        </tr>
    </x-slot:head>

    @forelse ($items as $item)
        @php
            $message = 'Delete '.$item->name.'? This cannot be undone.';
            if ($unlinkWarning && $item->restaurants_count > 0) {
                $message = 'Delete '.$item->name.'? It will be removed from '.$item->restaurants_count.' restaurant(s). The restaurants themselves are kept.';
            }
        @endphp
        <tr>
            <td class="px-4 py-3 font-medium">{{ $item->name }}</td>
            <td class="px-4 py-3 text-cream/60">{{ $item->slug }}</td>
            <td class="px-4 py-3">{{ $item->restaurants_count }}</td>
            <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                    <x-button variant="outline" class="px-3 py-1.5" :href="route($routePrefix.'.edit', $item)">Edit</x-button>
                    <x-admin.delete-form :action="route($routePrefix.'.destroy', $item)" :message="$message" />
                </div>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="4" class="px-4 py-8 text-center text-cream/60">
                No {{ $plural }} yet. Click "Add {{ $noun }}" to create one.
            </td>
        </tr>
    @endforelse
</x-admin.table>
