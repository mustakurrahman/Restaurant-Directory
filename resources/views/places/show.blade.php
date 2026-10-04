{{-- One city or cuisine with its restaurants. Used by /city/{slug} and /cuisine/{slug} --}}
@php
    $name = $place->name;
    $description = $kind === 'city'
        ? "Browse {$total} ".Str::plural('restaurant', $total)." in {$name}. Compare cuisines, prices and reviews, check opening hours and find your next great meal."
        : "Browse {$total} {$name} ".Str::plural('restaurant', $total).". Compare cities, prices and reviews, check opening hours and find your next great meal.";
    $listUrl = route('restaurants.index', [$kind => $place->slug]); // the full filter tool, pre-set to this place
@endphp

<x-layout :title="$pageTitle" :description="$description" :canonical="$canonical" :noindex="$noindex">

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <x-breadcrumbs :items="[['Home', route('home')], [ucfirst($plural), route($plural.'.index')], [$name, null]]" />

        <h1 class="mt-4 text-4xl font-bold">{{ $heading }}</h1>
        <p class="mt-2 max-w-2xl text-cream/70">
            {{ $total }} {{ Str::plural('restaurant', $total) }} {{ $kind === 'city' ? "in {$name}" : "serving {$name} food" }}.
            Want to narrow it down by price or amenities?
            <a href="{{ $listUrl }}" class="text-gold-500 underline hover:text-gold-600">Use the filters</a>.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-cream/70" role="status">
                Showing {{ $restaurants->firstItem() }}–{{ $restaurants->lastItem() }} of {{ $total }}
            </p>

            <form method="GET" action="{{ route("{$plural}.show", $place) }}" class="flex items-center gap-2">
                <label for="sort" class="text-sm text-cream/70">Sort by</label>
                <select id="sort" name="sort" data-autosubmit
                        class="rounded-lg border border-white/20 bg-ink-900 px-3 py-2 text-sm text-cream focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                    @foreach ($sorts as $key => $label)
                        <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="text-sm text-gold-500 underline">Apply</button></noscript>
            </form>
        </div>

        <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($restaurants as $restaurant)
                <x-restaurant-card :restaurant="$restaurant" />
            @endforeach
        </div>

        <div class="mt-10">
            {{ $restaurants->links() }}
        </div>

        {{-- Keep visitors (and Google) moving to the neighbouring pages --}}
        @if ($others->isNotEmpty())
            <section class="mt-16" aria-labelledby="others-heading">
                <h2 id="others-heading" class="text-2xl font-semibold">{{ $kind === 'city' ? 'Other cities' : 'Other cuisines' }}</h2>
                <ul class="mt-5 flex flex-wrap gap-3">
                    @foreach ($others as $other)
                        <li>
                            <a href="{{ route("{$plural}.show", $other) }}"
                               class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-ink-900 px-4 py-2 text-sm font-medium transition hover:border-gold-500 hover:text-gold-500">
                                {{ $other->name }}
                                <span class="text-cream/50">{{ $other->published_restaurants_count }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    <x-restaurant-itemlist :restaurants="$restaurants" />
</x-layout>
