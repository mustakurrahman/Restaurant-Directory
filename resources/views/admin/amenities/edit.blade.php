<x-admin.layout title="Edit amenity">
    <x-admin.name-slug-form noun="amenity" method="PUT" :item="$amenity"
                            :action="route('admin.amenities.update', $amenity)"
                            :cancel="route('admin.amenities.index')" />
</x-admin.layout>
