<?php

namespace App\Support;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Cuisine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The lists of cities, cuisines and amenities (each with its number of published restaurants) that appear on almost
 * every public page: the filter panel, the homepage tiles, "other cities". They only change when the owner edits
 * something, so they are kept for a few minutes instead of being counted again for every visitor.
 *
 * They are cleared at once whenever a restaurant, city, cuisine or amenity is saved or deleted (see the
 * ClearsDirectoryCache trait), and after the admin changes a restaurant's cuisine or amenity ticks.
 * The time limit is only a safety net.
 *
 * What is stored is plain data (arrays), never model objects: Laravel's real cache stores (database, file, Redis)
 * refuse to rebuild objects for safety, which would turn every cached model into a broken placeholder.
 * The models are rebuilt from the arrays on the way out.
 */
class DirectoryCache
{
    private const SECONDS = 600;

    private const KEYS = ['directory.cities', 'directory.cuisines', 'directory.amenities'];

    /** @return Collection<int, City> */
    public static function cities(): Collection
    {
        return self::remember('directory.cities', City::class, fn () => City::withPublishedRestaurants()->get());
    }

    /** @return Collection<int, Cuisine> */
    public static function cuisines(): Collection
    {
        return self::remember('directory.cuisines', Cuisine::class, fn () => Cuisine::withPublishedRestaurants()->get());
    }

    /** @return Collection<int, Amenity> */
    public static function amenities(): Collection
    {
        return self::remember('directory.amenities', Amenity::class, fn () => Amenity::withPublishedRestaurants()->get());
    }

    public static function forget(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private static function remember(string $key, string $model, \Closure $load): Collection
    {
        $rows = Cache::remember($key, self::SECONDS, fn () => $load()->map(fn ($item) => $item->getAttributes())->all());

        return $model::hydrate($rows); // models again, with the same attributes (including the counts)
    }
}
