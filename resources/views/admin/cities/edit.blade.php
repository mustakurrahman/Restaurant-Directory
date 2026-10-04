<x-admin.layout title="Edit city">
    <x-admin.name-slug-form noun="city" method="PUT" :item="$city"
                            :action="route('admin.cities.update', $city)"
                            :cancel="route('admin.cities.index')" />
</x-admin.layout>
