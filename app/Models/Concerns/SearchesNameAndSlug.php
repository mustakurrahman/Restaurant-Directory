<?php

namespace App\Models\Concerns;

use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;

// Model::search('york'): matches the name or the slug. Used by the admin lists of cities, cuisines and amenities.
trait SearchesNameAndSlug
{
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = Like::contains($term);

        return $query->where(fn (Builder $q) => $q
            ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
            ->orWhereRaw("slug LIKE ? ESCAPE '!'", [$like]));
    }
}
