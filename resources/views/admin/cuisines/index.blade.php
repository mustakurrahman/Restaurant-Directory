<x-admin.layout title="Cuisines">
    <x-slot:actions>
        <x-button :href="route('admin.cuisines.create')">Add cuisine</x-button>
    </x-slot:actions>

    <x-admin.name-slug-table :items="$cuisines" route-prefix="admin.cuisines" noun="cuisine" plural="cuisines" unlink-warning />
</x-admin.layout>
