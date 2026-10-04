<x-admin.layout title="Cities">
    <x-slot:actions>
        <x-button :href="route('admin.cities.create')">Add city</x-button>
    </x-slot:actions>

    <x-admin.name-slug-table :items="$cities" route-prefix="admin.cities" noun="city" plural="cities" />
</x-admin.layout>
