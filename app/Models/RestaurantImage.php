<?php

namespace App\Models;

use App\Support\PublicImage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantImage extends Model
{
    use HasFactory;

    protected $fillable = ['restaurant_id', 'path', 'alt_text', 'sort_order'];

    protected static function booted(): void
    {
        // Delete the file from disk once the database row is gone
        static::deleted(fn (RestaurantImage $image) => PublicImage::delete($image->path));
    }

    // $image->url: browser address of the photo, or null if the file does not exist
    protected function url(): Attribute
    {
        return Attribute::get(fn () => PublicImage::url($this->path));
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
