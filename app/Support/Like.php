<?php

namespace App\Support;

// Safe text for SQL "LIKE" searches
class Like
{
    /**
     * "%term%" with the wildcard characters in the visitor's text made harmless, so typing % or _ searches for
     * those characters instead of matching everything. "!" is the escape character: always pair the pattern with
     * "ESCAPE '!'" in the query, which behaves the same on MySQL and SQLite.
     */
    public static function contains(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }
}
