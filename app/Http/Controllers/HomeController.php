<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;

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
            'cities' => City::withPublishedRestaurants()->limit(8)->get(),
            'cuisines' => Cuisine::withPublishedRestaurants()->limit(12)->get(),
            // Headline numbers count published restaurants only
            'stats' => [
                'restaurants' => Restaurant::published()->count(),
                'cities' => City::listed()->count(),
                'cuisines' => Cuisine::listed()->count(),
            ],
        ]);
    }
}
