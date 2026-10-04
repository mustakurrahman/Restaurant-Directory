{{-- TEMPORARY simple home page: the full homepage (hero search, browse by city and cuisine) is the next task --}}
<x-layout title="Discover great restaurants"
          description="Discover and compare the best restaurants by city, cuisine and price. Read reviews, see opening hours and find your next great meal.">

    <section class="mx-auto max-w-6xl px-4 py-20 text-center sm:px-6 sm:py-28">
        <p class="text-sm font-medium uppercase tracking-widest text-gold-500">Restaurants Directory</p>
        <h1 class="mx-auto mt-4 max-w-3xl text-4xl font-bold sm:text-6xl">Discover great restaurants</h1>
        <p class="mx-auto mt-5 max-w-xl text-lg text-cream/70">
            Search, filter and read about the best places to eat in your city.
        </p>
    </section>

    @if ($featured->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pb-8 sm:px-6" aria-labelledby="featured-heading">
            <h2 id="featured-heading" class="text-3xl font-semibold">Featured restaurants</h2>

            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $restaurant)
                    <x-restaurant-card :restaurant="$restaurant" />
                @endforeach
            </div>
        </section>
    @endif

</x-layout>
