<?php

namespace App\Services;

use App\Models\City;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Builds the public restaurant list from visitor filters.
 * One place for the rules, so /restaurants, /city/{slug} and /cuisine/{slug} behave the same way.
 *
 * Filters (all optional): q (text), city (slug), cuisine (slug), price (list of 1-4),
 * amenities (list of slugs, a restaurant must have ALL of them), sort (a key of SORTS).
 */
class RestaurantSearch
{
    public const DEFAULT_SORT = 'recommended';

    /** key => label shown in the "Sort by" menu */
    public const SORTS = [
        'recommended' => 'Recommended',
        'rating' => 'Top rated',
        'newest' => 'Newest',
        'name' => 'Name A–Z',
        'price_low' => 'Price: low to high',
        'price_high' => 'Price: high to low',
    ];

    /**
     * One page of results. The rating numbers for the cards are fetched AFTER the page has been cut to its few
     * restaurants, in one extra query. Working them out inside the main query meant computing them for every
     * restaurant before sorting (slow on big directories and on deep pages).
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? self::DEFAULT_SORT;

        // Sorting by rating joins in the review averages. Counting the results does not need them, so the total is
        // counted without that join (otherwise the averages would be worked out twice per page view).
        $total = $sort === 'rating' ? $this->query(['sort' => self::DEFAULT_SORT] + $filters)->count() : null;

        $page = $this->query($filters)->paginate($perPage, ['*'], 'page', null, $total);

        Restaurant::attachReviewStats($page->getCollection());

        return $page;
    }

    /** The matching restaurants (without rating numbers; use paginate() for pages of cards). */
    public function query(array $filters = []): Builder
    {
        $query = Restaurant::query()
            ->published() // drafts are never public
            // Loaded once for the whole list, so the cards do not query per restaurant (N+1)
            ->with(['city:id,name,slug', 'cuisines:id,name,slug'])
            ->search($filters['q'] ?? null);

        if (! empty($filters['city'])) {
            $query->whereIn('city_id', City::where('slug', $filters['city'])->select('id'));
        }

        if (! empty($filters['cuisine'])) {
            $query->whereHas('cuisines', fn (Builder $q) => $q->where('cuisines.slug', $filters['cuisine']));
        }

        if (! empty($filters['price'])) {
            $query->whereIn('price_range', $filters['price']);
        }

        // One condition per ticked amenity, so the restaurant needs every one of them
        foreach ($filters['amenities'] ?? [] as $slug) {
            $query->whereHas('amenities', fn (Builder $q) => $q->where('amenities.slug', $slug));
        }

        return $this->sort($query, $filters['sort'] ?? self::DEFAULT_SORT);
    }

    private function sort(Builder $query, string $sort): Builder
    {
        // name and id are the last tie-breakers so the order (and the pages) never shuffle
        return match ($sort) {
            'rating' => $this->sortByRating($query),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            'price_low' => $query->orderBy('price_range')->orderBy('name')->orderBy('id'),
            'price_high' => $query->orderByDesc('price_range')->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('is_featured')->orderBy('name')->orderBy('id'), // recommended
        };
    }

    /**
     * Best rated first. The averages are worked out in ONE grouped query that is joined in. Sorting by the per-row
     * "count and average" lookups instead meant computing them for every restaurant (about 0.4 s with 5000 restaurants).
     * Restaurants without approved reviews have no row there, so they come last.
     */
    private function sortByRating(Builder $query): Builder
    {
        $stats = DB::table('reviews')
            ->where('status', 'approved')
            ->groupBy('restaurant_id')
            ->selectRaw('restaurant_id, count(*) as rating_count, avg(rating) as rating_average');

        return $query
            ->leftJoinSub($stats, 'rating_stats', 'rating_stats.restaurant_id', '=', 'restaurants.id')
            ->orderByDesc('rating_stats.rating_average')
            ->orderByDesc('rating_stats.rating_count')
            ->orderBy('restaurants.name')
            ->orderBy('restaurants.id');
    }
}
