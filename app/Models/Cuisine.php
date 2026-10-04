<?php

namespace App\Models;

use App\Models\Concerns\ClearsDirectoryCache;
use App\Models\Concerns\CountsPublishedRestaurants;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class Cuisine extends Model
{
    use ClearsDirectoryCache, CountsPublishedRestaurants, HasFactory, HasSlug;

    protected $fillable = ['name', 'slug'];

    // BelongsToMany: one cuisine is served by many restaurants (other side of Restaurant::cuisines)
    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class);
    }

    // Cuisine::listed(): only cuisines with at least one PUBLISHED restaurant
    public function scopeListed(Builder $query): Builder
    {
        return $query->whereHas('restaurants', fn (Builder $q) => $q->published());
    }

    // Listed cuisines, each with published_restaurants_count and last_changed, biggest first
    public function scopeWithPublishedRestaurants(Builder $query): Builder
    {
        return $query
            ->joinPublishedCounts()
            ->orderByDesc('published_counts.published_restaurants_count')
            ->orderBy('cuisines.name');
    }

    protected static function publishedRestaurantCounts(): QueryBuilder
    {
        return DB::table('cuisine_restaurant')
            ->join('restaurants', 'restaurants.id', '=', 'cuisine_restaurant.restaurant_id')
            ->where('restaurants.status', 'published')
            ->groupBy('cuisine_restaurant.cuisine_id')
            ->selectRaw('cuisine_restaurant.cuisine_id as owner_id, count(*) as published_restaurants_count, max(restaurants.updated_at) as last_changed');
    }
}
