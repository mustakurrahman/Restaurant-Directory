@php
    // Pages that arrive in later sprints. Each part below switches on by itself once its route exists.
    $searchUrl = Route::has('restaurants.index') ? route('restaurants.index') : null;
    $submitUrl = Route::has('submit.create') ? route('submit.create') : null;
    $tile = 'block rounded-2xl border border-white/10 bg-ink-900 p-5 transition';
    $tileHover = 'hover:-translate-y-0.5 hover:border-gold-500/50';
@endphp

<x-layout title="Discover great restaurants"
          description="Discover and compare the best restaurants by city, cuisine and price. Read reviews, see opening hours and find your next great meal.">

    {{-- Hero --}}
    <section class="relative isolate overflow-hidden">
        {{-- Soft golden glow behind the headline --}}
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,rgba(245,158,11,0.16),transparent_60%)]"></div>

        <div class="mx-auto max-w-6xl px-4 py-20 text-center sm:px-6 sm:py-28">
            <p class="text-sm font-medium uppercase tracking-widest text-gold-500">Restaurants Directory</p>
            <h1 class="mx-auto mt-4 max-w-3xl text-4xl font-bold leading-tight sm:text-6xl">Discover great restaurants</h1>
            <p class="mx-auto mt-5 max-w-xl text-lg text-cream/70">
                Search, filter and read about the best places to eat in your city.
            </p>

            {{-- Search: only shown once the listing page exists (Sprint 5), so it never leads nowhere --}}
            @if ($searchUrl)
                <form method="GET" action="{{ $searchUrl }}" role="search" class="mx-auto mt-10 flex max-w-xl flex-col gap-3 sm:flex-row">
                    <label for="hero-search" class="sr-only">Search restaurants</label>
                    <input id="hero-search" type="search" name="q" placeholder="Search by name or address"
                           class="w-full rounded-lg border border-white/20 bg-ink-900 px-5 py-3 text-cream placeholder:text-cream/40 focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                    <x-button type="submit" class="px-8 py-3">Search</x-button>
                </form>
            @endif
        </div>
    </section>

    {{-- The directory at a glance (published restaurants only) --}}
    @if ($stats['restaurants'] > 0)
        <section aria-label="The directory at a glance" class="mx-auto max-w-6xl px-4 sm:px-6">
            <dl class="grid grid-cols-3 divide-x divide-white/10 rounded-2xl border border-white/10 bg-ink-900 text-center">
                @foreach ([['restaurant', $stats['restaurants']], ['city', $stats['cities']], ['cuisine', $stats['cuisines']]] as [$word, $number])
                    {{-- dt (the label) comes first in the code for screen readers; flex-col-reverse shows the number on top --}}
                    <div class="flex flex-col-reverse px-2 py-6">
                        <dt class="mt-1 text-sm text-cream/60">{{ ucfirst(Str::plural($word, $number)) }}</dt>
                        <dd class="font-display text-3xl font-semibold text-gold-500 sm:text-4xl">{{ number_format($number) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    {{-- Featured --}}
    @if ($featured->isNotEmpty())
        <section class="mx-auto mt-16 max-w-6xl px-4 sm:px-6" aria-labelledby="featured-heading">
            <x-section-heading id="featured-heading" title="Featured restaurants" :href="$searchUrl" link="All restaurants" />

            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $restaurant)
                    <x-restaurant-card :restaurant="$restaurant" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Browse by city --}}
    @if ($cities->isNotEmpty())
        <section class="mx-auto mt-16 max-w-6xl px-4 sm:px-6" aria-labelledby="cities-heading">
            <x-section-heading id="cities-heading" title="Browse by city"
                               :href="Route::has('cities.index') ? route('cities.index') : null" link="All cities" />

            <ul class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($cities as $city)
                    @php $cityUrl = Route::has('cities.show') ? route('cities.show', $city) : null; @endphp
                    <li>
                        <x-maybe-link :href="$cityUrl" class="{{ $tile }} {{ $cityUrl ? $tileHover : '' }}">
                            <span class="block font-display text-xl font-semibold">{{ $city->name }}</span>
                            <span class="mt-1 block text-sm text-cream/60">
                                {{ $city->published_restaurants_count }} {{ Str::plural('restaurant', $city->published_restaurants_count) }}
                            </span>
                        </x-maybe-link>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Browse by cuisine --}}
    @if ($cuisines->isNotEmpty())
        <section class="mx-auto mt-16 max-w-6xl px-4 sm:px-6" aria-labelledby="cuisines-heading">
            <x-section-heading id="cuisines-heading" title="Browse by cuisine"
                               :href="Route::has('cuisines.index') ? route('cuisines.index') : null" link="All cuisines" />

            <ul class="mt-8 flex flex-wrap gap-3">
                @foreach ($cuisines as $cuisine)
                    @php $cuisineUrl = Route::has('cuisines.show') ? route('cuisines.show', $cuisine) : null; @endphp
                    <li>
                        <x-maybe-link :href="$cuisineUrl"
                                      class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-ink-900 px-4 py-2 text-sm font-medium transition {{ $cuisineUrl ? 'hover:border-gold-500 hover:text-gold-500' : '' }}">
                            {{ $cuisine->name }}
                            <span class="text-cream/50">{{ $cuisine->published_restaurants_count }}</span>
                        </x-maybe-link>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Newly added --}}
    @if ($latest->isNotEmpty())
        <section class="mx-auto mt-16 max-w-6xl px-4 sm:px-6" aria-labelledby="latest-heading">
            <x-section-heading id="latest-heading" title="Newly added" :href="$searchUrl" link="All restaurants" />

            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latest as $restaurant)
                    <x-restaurant-card :restaurant="$restaurant" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Call to action: only when the submit page exists --}}
    @if ($submitUrl)
        <section class="mx-auto mt-16 max-w-6xl px-4 sm:px-6" aria-labelledby="submit-heading">
            <div class="rounded-2xl border border-gold-500/30 bg-gold-500/5 px-6 py-12 text-center sm:px-12">
                <h2 id="submit-heading" class="text-3xl font-semibold">Own or love a restaurant?</h2>
                <p class="mx-auto mt-3 max-w-xl text-cream/70">
                    Tell us about it and we will review it for the directory. It only takes a minute.
                </p>
                <x-button :href="$submitUrl" class="mt-6 px-8 py-3">Submit a restaurant</x-button>
            </div>
        </section>
    @endif

    {{-- Information for Google (JSON-LD): this is the site's main page --}}
    @push('jsonld')
        @php
            $siteData = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => config('app.name'), 'url' => url('/')];

            if ($searchUrl) {
                // Tells Google the site has its own search box
                $siteData['potentialAction'] = [
                    '@type' => 'SearchAction',
                    'target' => $searchUrl.'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ];
            }
        @endphp
        <script type="application/ld+json">{!! json_encode($siteData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush

</x-layout>
