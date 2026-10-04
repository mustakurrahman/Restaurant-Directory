<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

// Fills in `slug` from `name` when it is empty (e.g. "Joe's Café" -> "joes-cafe")
trait HasSlug
{
    // Laravel calls boot{TraitName}() automatically when the model loads
    protected static function bootHasSlug(): void
    {
        // Runs on both create and update, but only acts when the slug is blank,
        // so existing URLs never change just because a name was edited
        static::saving(function ($model) {
            if (blank($model->slug)) {
                $model->slug = static::uniqueSlugFor($model->name, $model->getKey());
            }
        });
    }

    protected static function uniqueSlugFor(string $name, $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'item'; // fallback if the name has no usable letters
        $slug = $base;
        $counter = 2;

        // Add -2, -3 ... until no other row uses this slug
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
