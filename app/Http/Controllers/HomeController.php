<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;

class HomeController extends Controller
{
    public function __invoke()
    {
        // Only published and featured; city, cuisines and review stats are loaded once for all cards
        $featured = Restaurant::published()
            ->featured()
            ->withReviewStats()
            ->with(['city:id,name', 'cuisines:id,name'])
            ->orderBy('name')
            ->limit(6)
            ->get();

        return view('home', compact('featured'));
    }
}
