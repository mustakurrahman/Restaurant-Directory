<x-admin.layout title="Add restaurant">
    <x-admin.restaurant-form :restaurant="$restaurant"
                             :action="route('admin.restaurants.store')"
                             :cancel="route('admin.restaurants.index')"
                             :cities="$cities" :cuisines="$cuisines" :amenities="$amenities" />
</x-admin.layout>
