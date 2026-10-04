<?php

namespace App\Models;

use App\Models\Concerns\ClearsDirectoryCache;
use App\Models\Concerns\HasSlug;
use App\Support\Like;
use App\Support\PublicImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Restaurant extends Model
{
    use ClearsDirectoryCache, HasFactory, HasSlug;

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

    // $restaurant->placeholder_url: one of 4 on-brand pictures, always the same one for the same restaurant
    protected function placeholderUrl(): Attribute
    {
        return Attribute::get(fn () => asset('images/placeholders/restaurant-'.((($this->id ?? 0) % 4) + 1).'.svg'));
    }

    // $restaurant->image_url: the picture to show on cards and pages: the cover, or the placeholder
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->cover_url ?? $this->placeholder_url);
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

    /**
     * Saves the weekly hours form. $days is [1..7 => ['is_closed' => bool, 'opens_at' => ?string, 'closes_at' => ?string]].
     * Closed day: times are dropped. Empty day (not closed, no times): "not listed", so its row is removed.
     * All seven days are saved together or not at all.
     */
    public function saveOpeningHours(array $days): void
    {
        DB::transaction(function () use ($days) {
            foreach ($days as $day => $hours) {
                $closed = (bool) ($hours['is_closed'] ?? false);
                $opens = $closed ? null : ($hours['opens_at'] ?? null);
                $closes = $closed ? null : ($hours['closes_at'] ?? null);

                if (! $closed && blank($opens) && blank($closes)) {
                    OpeningHour::where('restaurant_id', $this->id)->where('day_of_week', $day)->delete();

                    continue;
                }

                // One row per restaurant per day (the table has a unique key on both)
                OpeningHour::updateOrCreate(
                    ['restaurant_id' => $this->id, 'day_of_week' => $day],
                    ['opens_at' => $opens, 'closes_at' => $closes, 'is_closed' => $closed],
                );
            }
        });
    }

    // Reusable keyword search on name and address: Restaurant::search('pizza')->get()
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = Like::contains($term);

        return $query->where(fn (Builder $q) => $q
            ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
            ->orWhereRaw("address LIKE ? ESCAPE '!'", [$like]));
    }

    /**
     * Adds approved_reviews_count and approved_reviews_avg_rating to a list of restaurants already in memory, using
     * ONE query for the whole list. Same two attributes (and same meaning) as scopeWithReviewStats below.
     *
     * @param  \Illuminate\Support\Collection<int, Restaurant>  $restaurants
     */
    public static function attachReviewStats($restaurants): void
    {
        if ($restaurants->isEmpty()) {
            return;
        }

        $stats = Review::query()->approved()
            ->whereIn('restaurant_id', $restaurants->modelKeys())
            ->groupBy('restaurant_id')
            ->selectRaw('restaurant_id, count(*) as total, avg(rating) as average')
            ->get()->keyBy('restaurant_id');

        foreach ($restaurants as $restaurant) {
            $row = $stats->get($restaurant->id);
            $restaurant->setAttribute('approved_reviews_count', $row ? (int) $row->total : 0);
            $restaurant->setAttribute('approved_reviews_avg_rating', $row ? (float) $row->average : null);
        }
    }

    // Restaurant::featured()->get(): the ones the owner marked for the homepage
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Adds approved_reviews_count and approved_reviews_avg_rating to each restaurant in ONE query,
     * so a list of cards does not need a query per card. Pending and rejected reviews never count.
     */
    public function scopeWithReviewStats(Builder $query): Builder
    {
        return $query
            ->withCount(['reviews as approved_reviews_count' => fn (Builder $q) => $q->approved()])
            ->withAvg(['reviews as approved_reviews_avg_rating' => fn (Builder $q) => $q->approved()], 'rating');
    }

    // Reusable filter: Restaurant::published()->get() hides drafts from the public
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
