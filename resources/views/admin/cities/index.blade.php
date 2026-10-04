<x-admin.layout title="Cities">
    <x-slot:actions>
        <x-button :href="route('admin.cities.create')">Add city</x-button>
    </x-slot:actions>

    <x-admin.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">Name</th>
                <th class="px-4 py-3 font-medium">Slug</th>
                <th class="px-4 py-3 font-medium">Restaurants</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
        </x-slot:head>

        @forelse ($cities as $city)
            <tr>
                <td class="px-4 py-3 font-medium">{{ $city->name }}</td>
                <td class="px-4 py-3 text-cream/60">{{ $city->slug }}</td>
                <td class="px-4 py-3">{{ $city->restaurants_count }}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-2">
                        <x-button variant="outline" class="px-3 py-1.5" :href="route('admin.cities.edit', $city)">Edit</x-button>
                        <x-admin.delete-form :action="route('admin.cities.destroy', $city)"
                                             :message="'Delete '.$city->name.'? This cannot be undone.'" />
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-cream/60">No cities yet. Click "Add city" to create one.</td>
            </tr>
        @endforelse
    </x-admin.table>
</x-admin.layout>
