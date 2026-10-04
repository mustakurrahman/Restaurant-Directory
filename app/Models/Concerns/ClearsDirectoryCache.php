<?php

namespace App\Models\Concerns;

use App\Support\DirectoryCache;

// Add to any model whose changes alter the public lists of cities, cuisines and amenities
trait ClearsDirectoryCache
{
    protected static function bootClearsDirectoryCache(): void
    {
        static::saved(fn () => DirectoryCache::forget());
        static::deleted(fn () => DirectoryCache::forget());
    }
}
