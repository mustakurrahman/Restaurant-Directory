<x-admin.layout title="Edit cuisine">
    <x-admin.name-slug-form noun="cuisine" method="PUT" :item="$cuisine"
                            :action="route('admin.cuisines.update', $cuisine)"
                            :cancel="route('admin.cuisines.index')" />
</x-admin.layout>
