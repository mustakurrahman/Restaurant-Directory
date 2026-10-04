<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'address', 'city_id',
        'phone', 'email', 'website', 'price_range',
        'latitude', 'longitude', 'cover_image',
        'is_featured', 'status', 'meta_title', 'meta_description',
    ];

    // Convert database values to proper PHP types
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'price_range' => 'integer',
        ];
    }

    // Each restaurant belongs to one city
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    // Many-to-many through the cuisine_restaurant table
    public function cuisines(): BelongsToMany
    {
        return $this->belongsToMany(Cuisine::class);
    }

    // Many-to-many through the amenity_restaurant table
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    // Gallery photos, in the order the owner chose
    public function images(): HasMany
    {
        return $this->hasMany(RestaurantImage::class)->orderBy('sort_order');
    }

    // Weekly hours, Monday first
    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class)->orderBy('day_of_week');
    }

    // Reusable filter: Restaurant::published()->get() hides drafts from the public
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
