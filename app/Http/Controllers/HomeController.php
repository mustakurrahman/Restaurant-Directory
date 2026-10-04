<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Support\DirectoryCache;

class HomeController extends Controller
{
    public function __invoke()
    {
        // City, cuisines and review stats are loaded once for all cards (no query per card)
        $cards = fn () => Restaurant::published()->withReviewStats()->with(['city:id,name', 'cuisines:id,name']);

        $featured = $cards()->featured()->orderBy('name')->limit(6)->get();

        // Newest published restaurants that are not already shown above; id breaks ties between equal dates
        $latest = $cards()
            ->whereNotIn('id', $featured->pluck('id'))
            ->latest()
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('home', [
            'featured' => $featured,
            'latest' => $latest,
            'cities' => DirectoryCache::cities()->take(8),
            'cuisines' => DirectoryCache::cuisines()->take(12),
            // Headline numbers count published restaurants only
            'stats' => [
                'restaurants' => Restaurant::published()->count(),
                'cities' => DirectoryCache::cities()->count(),
                'cuisines' => DirectoryCache::cuisines()->count(),
            ],
        ]);
    }
}
