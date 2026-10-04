<x-admin.layout title="Add amenity">
    <x-admin.name-slug-form noun="amenity"
                            :action="route('admin.amenities.store')"
                            :cancel="route('admin.amenities.index')" />
</x-admin.layout>
