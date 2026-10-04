<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * For City, Cuisine and Amenity: lists only the ones that have at least one PUBLISHED restaurant, each with
 * `published_restaurants_count` and `last_changed` (newest edit among those restaurants).
 *
 * The numbers come from ONE grouped query that is joined in, instead of one counting query per row. With thousands
 * of restaurants the per-row version took 100-250 ms on every listing page; this takes a few milliseconds.
 * The inner join also does the "listed" filtering: a row with no published restaurant has no counts, so it drops out.
 */
trait CountsPublishedRestaurants
{
    /**
     * One row per owner (city, cuisine or amenity) with columns:
     * owner_id, published_restaurants_count, last_changed
     */
    abstract protected static function publishedRestaurantCounts(): QueryBuilder;

    protected function scopeJoinPublishedCounts(Builder $query): Builder
    {
        return $query
            ->joinSub(static::publishedRestaurantCounts(), 'published_counts', 'published_counts.owner_id', '=', $this->qualifyColumn('id'))
            ->select($this->qualifyColumn('*'), 'published_counts.published_restaurants_count', 'published_counts.last_changed');
    }
}
