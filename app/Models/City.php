<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory, HasSlug;

    // Only these columns may be filled in from a form (mass assignment protection)
    protected $fillable = ['name', 'slug'];

    // HasMany: one city contains many restaurants (the other side of Restaurant::city)
    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }
}
