<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cuisine extends Model
{
    use HasFactory, HasSlug;

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

    // Listed cuisines, each with published_restaurants_count, biggest first
    public function scopeWithPublishedRestaurants(Builder $query): Builder
    {
        return $query
            ->listed()
            ->withCount(['restaurants as published_restaurants_count' => fn (Builder $q) => $q->published()])
            ->orderByDesc('published_restaurants_count')
            ->orderBy('name');
    }
}
