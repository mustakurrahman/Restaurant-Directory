<x-admin.layout title="Add restaurant">
    <x-admin.restaurant-form :restaurant="$restaurant"
                             :action="route('admin.restaurants.store')"
                             :cancel="route('admin.restaurants.index')"
                             :cities="$cities" :cuisines="$cuisines" :amenities="$amenities" />

    <p class="mt-6 max-w-3xl text-sm text-cream/60">
        Photos are added after saving: open the restaurant from the list and click Edit.
    </p>
</x-admin.layout>
