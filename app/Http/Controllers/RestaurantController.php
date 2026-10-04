<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestaurantListRequest;
use App\Models\Amenity;
use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Services\RestaurantSearch;
use App\Support\OpeningHoursFormatter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RestaurantController extends Controller
{
    private const PER_PAGE = 12;

    private const REVIEWS_SHOWN = 10;

    private const TITLE_LIMIT = 70; // characters of the full browser title

    public function index(RestaurantListRequest $request, RestaurantSearch $search)
    {
        $filters = $request->filters();

        $restaurants = $search->query($filters)
            ->paginate(self::PER_PAGE)
            ->appends($this->params($filters)); // page links keep the (cleaned) filters

        // A page number past the end is a page that does not exist
        abort_if($restaurants->isEmpty() && $restaurants->currentPage() > 1, 404);

        // Choices for the filter panel (only ones that have a published restaurant)
        $cities = City::withPublishedRestaurants()->get();
        $cuisines = Cuisine::withPublishedRestaurants()->get();
        $amenities = Amenity::withPublishedRestaurants()->get();

        $page = $restaurants->currentPage();

        return view('restaurants.index', [
            'restaurants' => $restaurants,
            'filters' => $filters,
            'sorts' => RestaurantSearch::SORTS,
            'cities' => $cities,
            'cuisines' => $cuisines,
            'amenities' => $amenities,
            'chips' => $this->chips($filters, $cities, $cuisines, $amenities),
            'isFiltered' => $request->isFiltered(),
            // Search engines: plain list pages are indexable (each page number has its own canonical address);
            // filtered or re-sorted views are near-duplicates, so they are kept out of Google.
            'noindex' => $request->isFiltered() || $filters['sort'] !== RestaurantSearch::DEFAULT_SORT,
            'canonical' => route('restaurants.index', ! $request->isFiltered() && $page > 1 ? ['page' => $page] : []),
            'pageTitle' => $page > 1 ? "Restaurants – page {$page}" : 'Restaurants',
        ]);
    }

    // {restaurant:slug} in the route finds the restaurant by its slug; drafts are hidden exactly like unknown addresses
    public function show(Restaurant $restaurant)
    {
        abort_unless($restaurant->status === 'published', 404);

        // Everything the page needs, loaded up front: a fixed number of queries however many photos or reviews exist
        $restaurant->load(['city:id,name,slug', 'cuisines:id,name,slug', 'amenities:id,name', 'images', 'openingHours']);
        $restaurant->loadCount(['reviews as approved_reviews_count' => fn ($q) => $q->approved()]);
        $restaurant->loadAvg(['reviews as approved_reviews_avg_rating' => fn ($q) => $q->approved()], 'rating');

        // Only approved reviews are public. Email addresses are never selected.
        $reviews = $restaurant->reviews()->approved()
            ->select('id', 'restaurant_id', 'name', 'rating', 'comment', 'created_at')
            ->latest()->latest('id')->limit(self::REVIEWS_SHOWN)->get();

        // Photos whose file is missing are skipped; the cover comes first
        $photos = $restaurant->images->filter(fn ($image) => $image->url !== null)->values();

        $related = Restaurant::published()
            ->where('city_id', $restaurant->city_id)->whereKeyNot($restaurant->id)
            ->withReviewStats()->with(['city:id,name,slug', 'cuisines:id,name,slug'])
            ->orderByDesc('is_featured')->orderBy('name')->limit(3)->get();

        $week = OpeningHoursFormatter::week($restaurant->openingHours);
        $cuisineNames = $restaurant->cuisines->pluck('name');

        return view('restaurants.show', [
            'restaurant' => $restaurant,
            'reviews' => $reviews,
            'photos' => $photos,
            'related' => $related,
            'week' => $week,
            'today' => now()->dayOfWeekIso,
            'listedHours' => collect($week)->contains('listed', true),
            'website' => preg_match('#^https?://#i', (string) $restaurant->website) ? $restaurant->website : null, // never output javascript: and the like
            'pageTitle' => $restaurant->meta_title ?: $this->title($restaurant, $cuisineNames->first()),
            'pageDescription' => $restaurant->meta_description ?: Str::limit(Str::of($restaurant->description ?? '')->squish()->toString(), 155)
                ?: "{$restaurant->name} in {$restaurant->city->name}. See photos, opening hours, contact details and reviews.",
            'jsonLd' => $this->schema($restaurant, $reviews, $photos),
        ]);
    }

    /**
     * "Name – Cuisine in City", shortened step by step if the whole browser title (with the site name added by the
     * layout) would pass 70 characters, because Google cuts longer titles off in its results.
     * Order of what is dropped: first the cuisine, then the city. The restaurant's own name is never cut.
     */
    private function title(Restaurant $restaurant, ?string $cuisine): string
    {
        $suffixLength = Str::length(' | '.config('app.name'));
        $city = $restaurant->city->name;

        foreach (array_filter([
            $cuisine ? "{$restaurant->name} – {$cuisine} in {$city}" : null,
            "{$restaurant->name} in {$city}",
        ]) as $candidate) {
            if (Str::length($candidate) + $suffixLength <= self::TITLE_LIMIT) {
                return $candidate;
            }
        }

        return $restaurant->name;
    }

    /** The restaurant described for Google (JSON-LD). Only facts we really have are included. */
    private function schema(Restaurant $restaurant, $reviews, $photos): array
    {
        $images = $photos->pluck('url')->prepend($restaurant->cover_url)->filter()->unique()->values()->all();

        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $restaurant->name,
            'url' => route('restaurants.show', $restaurant),
            'description' => $restaurant->description ? Str::limit(Str::of($restaurant->description)->squish()->toString(), 300) : null,
            'image' => $images ?: null,
            'telephone' => $restaurant->phone,
            'priceRange' => str_repeat('$', $restaurant->price_range),
            'servesCuisine' => $restaurant->cuisines->pluck('name')->all() ?: null,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $restaurant->address,
                'addressLocality' => $restaurant->city->name,
            ],
            'geo' => $restaurant->latitude !== null && $restaurant->longitude !== null ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $restaurant->latitude,
                'longitude' => (float) $restaurant->longitude,
            ] : null,
            'openingHoursSpecification' => OpeningHoursFormatter::schema($restaurant->openingHours) ?: null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        // Ratings are only claimed when approved reviews exist
        if ($restaurant->approved_reviews_count > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $restaurant->approved_reviews_avg_rating, 1),
                'reviewCount' => (int) $restaurant->approved_reviews_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
            $data['review'] = $reviews->map(fn ($review) => [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $review->name],
                'datePublished' => $review->created_at->toDateString(),
                'reviewBody' => $review->comment,
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $review->rating, 'bestRating' => 5, 'worstRating' => 1],
            ])->all();
        }

        return $data;
    }

    /** The filters as address-bar parameters, leaving out everything that is empty or the default */
    private function params(array $filters, array $without = []): array
    {
        $params = array_filter([
            'q' => $filters['q'],
            'city' => $filters['city'],
            'cuisine' => $filters['cuisine'],
            'price' => $filters['price'],
            'amenities' => $filters['amenities'],
            'sort' => $filters['sort'] === RestaurantSearch::DEFAULT_SORT ? null : $filters['sort'],
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return Arr::except($params, $without);
    }

    /**
     * One removable "chip" per active filter: [label, address of this same list without that filter].
     *
     * @return list<array{label: string, url: string}>
     */
    private function chips(array $filters, $cities, $cuisines, $amenities): array
    {
        $url = fn (array $params) => route('restaurants.index', $params);
        $chips = [];

        if ($filters['q'] !== '') {
            $chips[] = ['label' => '“'.$filters['q'].'”', 'url' => $url($this->params($filters, ['q']))];
        }

        if ($filters['city']) {
            $chips[] = ['label' => $cities->firstWhere('slug', $filters['city'])?->name ?? $filters['city'], 'url' => $url($this->params($filters, ['city']))];
        }

        if ($filters['cuisine']) {
            $chips[] = ['label' => $cuisines->firstWhere('slug', $filters['cuisine'])?->name ?? $filters['cuisine'], 'url' => $url($this->params($filters, ['cuisine']))];
        }

        if ($filters['price']) {
            $chips[] = [
                'label' => 'Price: '.collect($filters['price'])->map(fn ($p) => str_repeat('$', $p))->join(', '),
                'url' => $url($this->params($filters, ['price'])),
            ];
        }

        foreach ($filters['amenities'] as $slug) {
            $others = array_values(array_diff($filters['amenities'], [$slug]));
            $chips[] = [
                'label' => $amenities->firstWhere('slug', $slug)?->name ?? $slug,
                'url' => $url($this->params(['amenities' => $others] + $filters)),
            ];
        }

        return $chips;
    }
}
