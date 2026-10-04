<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    // Only these columns may be filled in from a form (mass assignment protection)
    protected $fillable = ['name', 'slug'];

    // A city has many restaurants
    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }
}
