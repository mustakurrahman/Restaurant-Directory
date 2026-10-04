<x-admin.layout title="Add cuisine">
    <x-admin.name-slug-form noun="cuisine"
                            :action="route('admin.cuisines.store')"
                            :cancel="route('admin.cuisines.index')" />
</x-admin.layout>
