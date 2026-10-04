<?php

namespace App\Services;

use App\Models\City;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;

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

    public function query(array $filters = []): Builder
    {
        $query = Restaurant::query()
            ->published() // drafts are never public
            ->withReviewStats()
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
            'rating' => $query->orderByDesc('approved_reviews_avg_rating')->orderByDesc('approved_reviews_count')->orderBy('name'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            'price_low' => $query->orderBy('price_range')->orderBy('name')->orderBy('id'),
            'price_high' => $query->orderByDesc('price_range')->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('is_featured')->orderBy('name')->orderBy('id'), // recommended
        };
    }
}
