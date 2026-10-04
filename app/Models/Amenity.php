<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = ['name', 'slug'];

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

    // Listed amenities, each with published_restaurants_count, in alphabetical order (a tick-box list reads best that way)
    public function scopeWithPublishedRestaurants(Builder $query): Builder
    {
        return $query
            ->listed()
            ->withCount(['restaurants as published_restaurants_count' => fn (Builder $q) => $q->published()])
            ->orderBy('name');
    }
}
