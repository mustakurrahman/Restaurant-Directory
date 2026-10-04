<?php

namespace App\Models;

use App\Models\Concerns\ClearsDirectoryCache;
use App\Models\Concerns\CountsPublishedRestaurants;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\SearchesNameAndSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class Amenity extends Model
{
    use ClearsDirectoryCache, CountsPublishedRestaurants, HasFactory, HasSlug, SearchesNameAndSlug;

    protected $fillable = ['name', 'slug', 'icon'];

    // BelongsToMany: one amenity is offered by many restaurants (other side of Restaurant::amenities)
    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class);
    }

    // Amenity::listed(): only amenities that at least one PUBLISHED restaurant offers
    public function scopeListed(Builder $query): Builder
    {
        return $query->whereHas('restaurants', fn (Builder $q) => $q->published());
    }

    // Listed amenities, each with published_restaurants_count and last_changed, in alphabetical order (a tick-box list reads best that way)
    public function scopeWithPublishedRestaurants(Builder $query): Builder
    {
        return $query
            ->joinPublishedCounts()
            ->orderBy('amenities.name');
    }

    protected static function publishedRestaurantCounts(): QueryBuilder
    {
        return DB::table('amenity_restaurant')
            ->join('restaurants', 'restaurants.id', '=', 'amenity_restaurant.restaurant_id')
            ->where('restaurants.status', 'published')
            ->groupBy('amenity_restaurant.amenity_id')
            ->selectRaw('amenity_restaurant.amenity_id as owner_id, count(*) as published_restaurants_count, max(restaurants.updated_at) as last_changed');
    }
}
