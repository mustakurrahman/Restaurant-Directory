@php
    $hasCover = $restaurant->cover_url !== null;
    $count = (int) $restaurant->approved_reviews_count;
    $average = $count > 0 ? number_format((float) $restaurant->approved_reviews_avg_rating, 1) : null;
    $paragraphs = preg_split('/\R{2,}/', trim((string) $restaurant->description), -1, PREG_SPLIT_NO_EMPTY);
    $phoneLink = $restaurant->phone ? preg_replace('/[^0-9+]/', '', $restaurant->phone) : null;
    $mapsUrl = $restaurant->latitude !== null && $restaurant->longitude !== null
        ? 'https://www.google.com/maps/search/?api=1&query='.$restaurant->latitude.','.$restaurant->longitude
        : 'https://www.google.com/maps/search/?api=1&query='.urlencode($restaurant->address.', '.$restaurant->city->name);
@endphp

<x-layout :title="$pageTitle" :description="$pageDescription" :image="$hasCover ? $restaurant->cover_url : null"
          :canonical="route('restaurants.show', $restaurant)" type="restaurant.restaurant">

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <x-breadcrumbs :items="[
            ['Home', route('home')],
            ['Restaurants', route('restaurants.index')],
            [$restaurant->city->name, route('cities.show', $restaurant->city)],
            [$restaurant->name, null],
        ]" />

        {{-- Title block --}}
        <header class="mt-4">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-4xl font-bold sm:text-5xl">{{ $restaurant->name }}</h1>
                @if ($restaurant->is_featured)
                    <x-badge variant="gold" class="px-3 py-1">Featured</x-badge>
                @endif
            </div>

            <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-cream/70">
                <a href="{{ route('cities.show', $restaurant->city) }}" class="hover:text-gold-500">{{ $restaurant->city->name }}</a>
                <span aria-hidden="true">·</span>
                <span aria-label="Price range {{ $restaurant->price_range }} of 4">
                    <span class="text-gold-500">{{ str_repeat('$', $restaurant->price_range) }}</span><span class="text-cream/25">{{ str_repeat('$', 4 - $restaurant->price_range) }}</span>
                </span>
                @foreach ($restaurant->cuisines as $cuisine)
                    <span aria-hidden="true">·</span>
                    <a href="{{ route('cuisines.show', $cuisine) }}" class="hover:text-gold-500">{{ $cuisine->name }}</a>
                @endforeach
            </p>

            <p class="mt-2 text-sm">
                @if ($average)
                    <span class="font-semibold text-gold-500" aria-hidden="true">★ {{ $average }}</span>
                    <span class="sr-only">Rated {{ $average }} out of 5,</span>
                    <a href="#reviews" class="text-cream/60 hover:text-gold-500">({{ $count }} {{ Str::plural('review', $count) }})</a>
                @else
                    <span class="text-cream/50">No reviews yet</span>
                @endif
            </p>
        </header>

        <div class="mt-8 lg:grid lg:grid-cols-[minmax(0,1fr)_21rem] lg:gap-10">
            <div class="min-w-0">
                {{-- Main picture. The width and height reserve space so the page does not jump while loading --}}
                <div class="overflow-hidden rounded-2xl border border-white/10 bg-ink-900">
                    <img src="{{ $restaurant->image_url }}" alt="{{ $hasCover ? 'Photo of '.$restaurant->name : '' }}"
                         width="1200" height="800" class="aspect-[3/2] w-full object-cover">
                </div>

                {{-- About --}}
                <section class="mt-10" aria-labelledby="about-heading">
                    <h2 id="about-heading" class="text-2xl font-semibold">About {{ $restaurant->name }}</h2>
                    @forelse ($paragraphs as $paragraph)
                        <p class="mt-4 leading-relaxed text-cream/80">{!! nl2br(e($paragraph)) !!}</p>
                    @empty
                        <p class="mt-4 text-cream/60">There is no description for this restaurant yet.</p>
                    @endforelse
                </section>

                {{-- Gallery --}}
                @if ($photos->isNotEmpty())
                    <section class="mt-12" aria-labelledby="gallery-heading">
                        <h2 id="gallery-heading" class="text-2xl font-semibold">Photos</h2>
                        <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($photos as $photo)
                                <li>
                                    {{-- Opens the full-size picture; works without JavaScript --}}
                                    <a href="{{ $photo->url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-xl border border-white/10">
                                        <img src="{{ $photo->url }}" alt="{{ $photo->alt_text ?: $restaurant->name.' photo '.$loop->iteration }}"
                                             width="600" height="450" loading="lazy"
                                             class="aspect-[4/3] w-full object-cover transition duration-300 hover:scale-105">
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Reviews (only approved ones are ever loaded) --}}
                <section id="reviews" class="mt-12 scroll-mt-24" aria-labelledby="reviews-heading">
                    <h2 id="reviews-heading" class="text-2xl font-semibold">Reviews</h2>

                    @if ($count === 0)
                        <x-card class="mt-4"><p class="text-cream/70">No reviews yet.</p></x-card>
                    @else
                        <p class="mt-2 text-cream/70">
                            <span class="font-semibold text-gold-500">★ {{ $average }}</span> from {{ $count }} {{ Str::plural('review', $count) }}
                            @if ($count > $reviews->count())
                                · showing the latest {{ $reviews->count() }}
                            @endif
                        </p>

                        <ul class="mt-4 space-y-4">
                            @foreach ($reviews as $review)
                                <li>
                                    <x-card>
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-semibold">{{ $review->name }}</p>
                                            <p class="text-sm text-cream/50"><time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('M j, Y') }}</time></p>
                                        </div>
                                        <p class="mt-1 text-gold-500" aria-hidden="true">{{ str_repeat('★', $review->rating) }}<span class="text-cream/25">{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                                        <p class="sr-only">Rated {{ $review->rating }} out of 5</p>
                                        <p class="mt-3 leading-relaxed text-cream/80">{!! nl2br(e($review->comment)) !!}</p>
                                    </x-card>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            {{-- Sidebar: how to reach the restaurant --}}
            <aside class="mt-12 space-y-6 lg:mt-0" aria-label="Restaurant details">
                <x-card>
                    <h2 class="text-xl font-semibold">Contact</h2>
                    <dl class="mt-4 space-y-4 text-sm">
                        <div>
                            <dt class="text-cream/50">Address</dt>
                            <dd class="mt-0.5">{{ $restaurant->address }}, {{ $restaurant->city->name }}</dd>
                            <dd class="mt-1"><a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="text-gold-500 hover:text-gold-600 hover:underline">Get directions<span class="sr-only"> (opens in a new tab)</span></a></dd>
                        </div>
                        @if ($restaurant->phone)
                            <div>
                                <dt class="text-cream/50">Phone</dt>
                                <dd class="mt-0.5"><a href="tel:{{ $phoneLink }}" class="hover:text-gold-500">{{ $restaurant->phone }}</a></dd>
                            </div>
                        @endif
                        @if ($restaurant->email)
                            <div>
                                <dt class="text-cream/50">Email</dt>
                                <dd class="mt-0.5 break-all"><a href="mailto:{{ $restaurant->email }}" class="hover:text-gold-500">{{ $restaurant->email }}</a></dd>
                            </div>
                        @endif
                        @if ($website)
                            <div>
                                <dt class="text-cream/50">Website</dt>
                                {{-- nofollow: we do not vouch for outside sites; noopener: the other site cannot control this tab --}}
                                <dd class="mt-0.5 break-all"><a href="{{ $website }}" target="_blank" rel="nofollow noopener" class="hover:text-gold-500">{{ preg_replace('#^https?://(www\.)?#i', '', rtrim($website, '/')) }}<span class="sr-only"> (opens in a new tab)</span></a></dd>
                            </div>
                        @endif
                    </dl>
                </x-card>

                @if ($listedHours)
                    <x-card>
                        <h2 class="text-xl font-semibold">Opening hours</h2>
                        <dl class="mt-4 space-y-2 text-sm">
                            @foreach ($week as $day)
                                <div @class(['flex justify-between gap-4 rounded-md px-2 py-1', 'bg-gold-500/10 font-semibold text-gold-500' => $day['number'] === $today])>
                                    <dt>{{ $day['name'] }}@if ($day['number'] === $today) <span class="font-normal">(today)</span>@endif</dt>
                                    <dd @class(['text-right', 'text-cream/50' => ! $day['listed'] || $day['closed']])>{{ $day['text'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-card>
                @endif

                @if ($restaurant->amenities->isNotEmpty())
                    <x-card>
                        <h2 class="text-xl font-semibold">Amenities</h2>
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @foreach ($restaurant->amenities as $amenity)
                                <li><x-badge class="px-3 py-1">{{ $amenity->name }}</x-badge></li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            </aside>
        </div>

        {{-- More in the same city: gives visitors (and Google) a next step --}}
        @if ($related->isNotEmpty())
            <section class="mt-16" aria-labelledby="related-heading">
                <x-section-heading id="related-heading" :title="'More restaurants in '.$restaurant->city->name"
                                   :href="route('cities.show', $restaurant->city)" link="See all" />
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $other)
                        <x-restaurant-card :restaurant="$other" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @push('jsonld')
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
</x-layout>
