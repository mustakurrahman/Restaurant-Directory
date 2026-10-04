<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    // Reusable filter: Restaurant::published()->get() hides drafts from the public
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
