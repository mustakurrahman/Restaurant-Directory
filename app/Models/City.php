<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory, HasSlug;

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

    // Listed cities, each with published_restaurants_count, biggest first
    public function scopeWithPublishedRestaurants(Builder $query): Builder
    {
        return $query
            ->listed()
            ->withCount(['restaurants as published_restaurants_count' => fn (Builder $q) => $q->published()])
            ->orderByDesc('published_restaurants_count')
            ->orderBy('name');
    }
}
