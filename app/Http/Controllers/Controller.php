<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class Controller
{
    /**
     * The search word from ?q=..., cleaned: plain text only (an array such as ?q[]=x becomes empty), no extra spaces,
     * at most 100 characters. The clean value is also put back into the request (or removed when empty), so the search
     * box refills with it and the page links carry only a real search word.
     */
    protected function searchTerm(Request $request, string $key = 'q'): string
    {
        $value = $request->query($key);
        $term = is_string($value) ? Str::of($value)->squish()->limit(100, '')->toString() : '';

        $term === '' ? $request->query->remove($key) : $request->query->set($key, $term);

        return $term;
    }
}
