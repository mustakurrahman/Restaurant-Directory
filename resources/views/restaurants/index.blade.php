<x-layout :title="$pageTitle"
          :description="'Browse '.$restaurants->total().' '.Str::plural('restaurant', $restaurants->total()).'. Filter by city, cuisine, price and amenities, read reviews and find your next great meal.'"
          :canonical="$canonical" :noindex="$noindex">

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <x-breadcrumbs :items="[['Home', route('home')], ['Restaurants', null]]" />

        <h1 class="mt-4 text-4xl font-bold">Restaurants</h1>
        <p class="mt-2 max-w-2xl text-cream/70">Search and filter to find the right place, whether it's a quick lunch or a special night out.</p>

        <div class="mt-8 lg:grid lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-10">

            {{-- Filters. On phones they sit behind a button (open by default when filters are active) --}}
            <aside aria-label="Filters">
                <button type="button" data-toggle="filters-panel" aria-controls="filters-panel" aria-expanded="{{ $isFiltered ? 'true' : 'false' }}"
                        class="flex w-full items-center justify-between rounded-lg border border-white/20 bg-ink-900 px-4 py-3 text-sm font-medium hover:border-gold-500 lg:hidden">
                    <span>Filters</span>
                    @if (count($chips))
                        <span class="rounded-full bg-gold-500 px-2 py-0.5 text-xs font-semibold text-ink-950">{{ count($chips) }}</span>
                    @endif
                </button>

                <div id="filters-panel" class="{{ $isFiltered ? '' : 'hidden' }} mt-3 lg:mt-0 lg:block lg:sticky lg:top-24">
                    <form id="filters-form" method="GET" action="{{ route('restaurants.index') }}" class="space-y-6 rounded-2xl border border-white/10 bg-ink-900 p-5">
                        <x-input name="q" label="Search" type="search" :value="$filters['q']" placeholder="Name or address" />

                        <x-select name="city" label="City">
                            <option value="">All cities</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->slug }}" @selected($filters['city'] === $city->slug)>{{ $city->name }} ({{ $city->published_restaurants_count }})</option>
                            @endforeach
                        </x-select>

                        <x-select name="cuisine" label="Cuisine">
                            <option value="">All cuisines</option>
                            @foreach ($cuisines as $cuisine)
                                <option value="{{ $cuisine->slug }}" @selected($filters['cuisine'] === $cuisine->slug)>{{ $cuisine->name }} ({{ $cuisine->published_restaurants_count }})</option>
                            @endforeach
                        </x-select>

                        <fieldset>
                            <legend class="mb-2 text-sm font-medium">Price</legend>
                            <div class="space-y-2">
                                @foreach ([1 => '$ · Budget', 2 => '$$ · Moderate', 3 => '$$$ · Upscale', 4 => '$$$$ · Fine dining'] as $value => $text)
                                    <x-checkbox name="price[]" :value="$value" :label="$text" :checked="in_array($value, $filters['price'], true)" />
                                @endforeach
                            </div>
                        </fieldset>

                        @if ($amenities->isNotEmpty())
                            <fieldset>
                                <legend class="mb-2 text-sm font-medium">Amenities <span class="font-normal text-cream/50">(must have all)</span></legend>
                                <div class="space-y-2">
                                    @foreach ($amenities as $amenity)
                                        <x-checkbox name="amenities[]" :value="$amenity->slug" :label="$amenity->name"
                                                    :checked="in_array($amenity->slug, $filters['amenities'], true)" />
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        <div class="flex flex-wrap gap-3">
                            <x-button type="submit">Apply filters</x-button>
                            @if ($isFiltered)
                                <x-button variant="outline" :href="route('restaurants.index')">Clear all</x-button>
                            @endif
                        </div>
                    </form>
                </div>
            </aside>

            {{-- Results --}}
            <section class="mt-8 lg:mt-0" aria-label="Results">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm text-cream/70" role="status">
                        @if ($restaurants->total() === 0)
                            No restaurants found
                        @elseif ($isFiltered)
                            {{ $restaurants->total() }} {{ Str::plural('restaurant', $restaurants->total()) }} match
                        @else
                            Showing {{ $restaurants->firstItem() }}–{{ $restaurants->lastItem() }} of {{ $restaurants->total() }} {{ Str::plural('restaurant', $restaurants->total()) }}
                        @endif
                    </p>

                    {{-- Belongs to the filter form above (form="..."), so choosing a sort keeps every filter --}}
                    <div class="flex items-center gap-2">
                        <label for="sort" class="text-sm text-cream/70">Sort by</label>
                        <select id="sort" name="sort" form="filters-form" data-autosubmit
                                class="rounded-lg border border-white/20 bg-ink-900 px-3 py-2 text-sm text-cream focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                            @foreach ($sorts as $key => $label)
                                <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" form="filters-form" class="text-sm text-gold-500 underline">Apply</button></noscript>
                    </div>
                </div>

                @if (count($chips))
                    <ul class="mt-4 flex flex-wrap items-center gap-2" aria-label="Active filters">
                        @foreach ($chips as $chip)
                            <li>
                                <a href="{{ $chip['url'] }}"
                                   class="inline-flex items-center gap-1.5 rounded-full border border-gold-500/40 bg-gold-500/10 px-3 py-1 text-sm text-gold-500 hover:bg-gold-500/20">
                                    {{ $chip['label'] }}
                                    <span aria-hidden="true">×</span>
                                    <span class="sr-only">(remove this filter)</span>
                                </a>
                            </li>
                        @endforeach
                        <li><a href="{{ route('restaurants.index') }}" class="text-sm text-cream/60 underline hover:text-gold-500">Clear all</a></li>
                    </ul>
                @endif

                @if ($restaurants->isEmpty())
                    <x-card class="mt-6 py-12 text-center">
                        <p class="font-display text-2xl font-semibold">
                            {{ $isFiltered ? 'No restaurants match your filters' : 'No restaurants yet' }}
                        </p>
                        <p class="mx-auto mt-2 max-w-md text-cream/70">
                            {{ $isFiltered ? 'Try removing a filter, or search for something broader.' : 'Please check back soon.' }}
                        </p>
                        @if ($isFiltered)
                            <x-button :href="route('restaurants.index')" class="mt-6">Clear all filters</x-button>
                        @endif
                    </x-card>
                @else
                    <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($restaurants as $restaurant)
                            <x-restaurant-card :restaurant="$restaurant" />
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $restaurants->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- The list of restaurants on this page, described to Google (links only once the restaurant pages exist) --}}
    @if (Route::has('restaurants.show') && $restaurants->isNotEmpty())
        @push('jsonld')
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => $restaurants->values()->map(fn ($restaurant, $index) => [
                    '@type' => 'ListItem',
                    'position' => $restaurants->firstItem() + $index,
                    'url' => route('restaurants.show', $restaurant),
                    'name' => $restaurant->name,
                ])->all(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endpush
    @endif
</x-layout>
