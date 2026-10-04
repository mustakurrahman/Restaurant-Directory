<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Support\PublicImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use HasFactory, HasSlug;

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

    protected static function booted(): void
    {
        // The database already removed the image rows (cascade); this removes the files from disk
        static::deleted(fn (Restaurant $restaurant) => PublicImage::deleteFolderFor($restaurant->id));
    }

    // $restaurant->cover_url: browser address of the cover photo, or null if there is none on disk
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn () => PublicImage::url($this->cover_image));
    }

    // BelongsTo: each restaurant sits in exactly one city (restaurants.city_id)
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    // BelongsToMany: a restaurant can serve many cuisines, and a cuisine appears in many restaurants
    public function cuisines(): BelongsToMany
    {
        return $this->belongsToMany(Cuisine::class);
    }

    // BelongsToMany: same idea for features like Wi-Fi or parking
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    // HasMany: a restaurant owns many gallery photos, shown in the owner's chosen order
    public function images(): HasMany
    {
        return $this->hasMany(RestaurantImage::class)->orderBy('sort_order');
    }

    // HasMany: a restaurant owns up to seven opening-hour rows (one per weekday), Monday first
    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class)->orderBy('day_of_week');
    }

    // HasMany: a restaurant receives many reviews; on public pages use ->reviews()->approved()
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // Reusable keyword search on name and address: Restaurant::search('pizza')->get()
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        // Escape % and _ so a visitor typing them searches for the characters, not "match anything".
        // "!" is declared as the escape character in the query, which behaves the same on MySQL and SQLite.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
            ->orWhereRaw("address LIKE ? ESCAPE '!'", [$like]));
    }

    // Reusable filter: Restaurant::published()->get() hides drafts from the public
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
