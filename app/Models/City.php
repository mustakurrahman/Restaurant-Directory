<?php

namespace App\Models;

use App\Models\Concerns\ClearsDirectoryCache;
use App\Models\Concerns\CountsPublishedRestaurants;
use App\Models\Concerns\HasSlug;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class City extends Model
{
    use ClearsDirectoryCache, CountsPublishedRestaurants, HasFactory, HasSlug;

    // Only these columns may be filled in from a form (mass assignment protection)
    protected $fillable = ['name', 'slug', 'description'];

    // HasMany: one city contains many restaurants (the other side of Restaurant::city)
    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }

    // City::search('york'): matches the name or the slug (used by the admin list)
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = Like::contains($term);

        return $query->where(fn (Builder $q) => $q
            ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
            ->orWhereRaw("slug LIKE ? ESCAPE '!'", [$like]));
    }

    // City::listed(): only cities with at least one PUBLISHED restaurant. Drafts do not make a city public.
    public function scopeListed(Builder $query): Builder
    {
        return $query->whereHas('restaurants', fn (Builder $q) => $q->published());
    }

    // Listed cities, each with published_restaurants_count and last_changed, biggest first
    public function scopeWithPublishedRestaurants(Builder $query): Builder
    {
        return $query
            ->joinPublishedCounts()
            ->orderByDesc('published_counts.published_restaurants_count')
            ->orderBy('cities.name');
    }

    protected static function publishedRestaurantCounts(): QueryBuilder
    {
        return DB::table('restaurants')
            ->where('status', 'published')
            ->groupBy('city_id')
            ->selectRaw('city_id as owner_id, count(*) as published_restaurants_count, max(updated_at) as last_changed');
    }
}
