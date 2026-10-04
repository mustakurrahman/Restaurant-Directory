{{--
    Restaurant tile for the homepage and listings: <x-restaurant-card :restaurant="$restaurant" />

    It never queries the database itself, so load everything for the whole list up front:
        Restaurant::published()->withReviewStats()->with(['city:id,name', 'cuisines:id,name'])->get()
    (without withReviewStats() the rating line is simply left out)
--}}
@props(['restaurant'])

@php
    // The link switches on by itself once the restaurant page exists (Sprint 5); until then the card is not a dead link
    $url = Route::has('restaurants.show') ? route('restaurants.show', $restaurant) : null;

    $hasReviewStats = array_key_exists('approved_reviews_count', $restaurant->getAttributes());
    $reviewCount = (int) ($restaurant->approved_reviews_count ?? 0);
    $rating = $restaurant->approved_reviews_avg_rating;

    $isPlaceholder = $restaurant->cover_url === null;
    $cuisines = $restaurant->cuisines->take(3)->pluck('name')->join(' · ');
@endphp

<article {{ $attributes->class(['group relative overflow-hidden rounded-2xl border border-white/10 bg-ink-900 transition duration-300 hover:-translate-y-0.5 hover:border-gold-500/50']) }}>
    <div class="relative aspect-[4/3] overflow-hidden bg-ink-950">
        {{-- A placeholder is decoration, so it gets an empty alt; a real photo is described by the restaurant's name --}}
        <img src="{{ $restaurant->image_url }}" alt="{{ $isPlaceholder ? '' : 'Photo of '.$restaurant->name }}"
             width="800" height="600" loading="lazy"
             class="size-full object-cover transition duration-500 group-hover:scale-105">

        @if ($restaurant->is_featured)
            <x-badge variant="gold" class="absolute left-3 top-3 bg-ink-950/85 px-3 py-1">Featured</x-badge>
        @endif
    </div>

    <div class="p-5">
        <div class="flex items-center justify-between gap-3 text-xs">
            <p class="truncate font-medium uppercase tracking-wider text-cream/60">{{ $restaurant->city->name }}</p>
            <p class="shrink-0 font-medium" aria-label="Price range {{ $restaurant->price_range }} of 4">
                <span class="text-gold-500">{{ str_repeat('$', $restaurant->price_range) }}</span><span class="text-cream/25">{{ str_repeat('$', 4 - $restaurant->price_range) }}</span>
            </p>
        </div>

        <h3 class="mt-2 text-xl font-semibold leading-snug">
            @if ($url)
                {{-- The invisible ::after layer stretches this link over the whole card, so the entire tile is clickable --}}
                <a href="{{ $url }}" class="after:absolute after:inset-0 after:content-[''] hover:text-gold-500 focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-gold-500 focus-visible:after:rounded-2xl">{{ $restaurant->name }}</a>
            @else
                {{ $restaurant->name }}
            @endif
        </h3>

        @if ($cuisines)
            <p class="mt-1 text-sm text-cream/70">{{ $cuisines }}</p>
        @endif

        @if ($hasReviewStats)
            <p class="mt-3 text-sm">
                @if ($reviewCount > 0)
                    <span class="font-semibold text-gold-500" aria-hidden="true">★ {{ number_format((float) $rating, 1) }}</span>
                    <span class="sr-only">Rated {{ number_format((float) $rating, 1) }} out of 5,</span>
                    <span class="text-cream/60">({{ $reviewCount }} {{ Str::plural('review', $reviewCount) }})</span>
                @else
                    <span class="text-cream/50">No reviews yet</span>
                @endif
            </p>
        @endif
    </div>
</article>
