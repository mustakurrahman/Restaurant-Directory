<x-admin.layout title="Add city">
    <x-admin.name-slug-form noun="city" with-description
                            :action="route('admin.cities.store')"
                            :cancel="route('admin.cities.index')" />
</x-admin.layout>
