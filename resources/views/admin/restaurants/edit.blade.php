<x-admin.layout :title="'Edit '.$restaurant->name">
    <x-admin.restaurant-form method="PUT" :restaurant="$restaurant"
                             :action="route('admin.restaurants.update', $restaurant)"
                             :cancel="route('admin.restaurants.index')"
                             :cities="$cities" :cuisines="$cuisines" :amenities="$amenities" />

    <div class="mt-10">
        <x-admin.restaurant-photos :restaurant="$restaurant" />
    </div>
</x-admin.layout>
