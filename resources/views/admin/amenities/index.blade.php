<x-admin.layout title="Amenities">
    <x-slot:actions>
        <x-button :href="route('admin.amenities.create')">Add amenity</x-button>
    </x-slot:actions>

    <x-admin.name-slug-table :items="$amenities" route-prefix="admin.amenities" noun="amenity" plural="amenities" unlink-warning />
</x-admin.layout>
