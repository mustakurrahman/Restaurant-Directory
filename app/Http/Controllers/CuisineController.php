<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ShowsRestaurantPlace;
use App\Http\Requests\RestaurantListRequest;
use App\Models\Cuisine;
use App\Services\RestaurantSearch;
use App\Support\DirectoryCache;

class CuisineController extends Controller
{
    use ShowsRestaurantPlace;

    public function index()
    {
        return view('places.index', [
            'kind' => 'cuisine',
            'plural' => 'cuisines',
            'places' => DirectoryCache::cuisines(),
            'pageTitle' => 'Restaurants by cuisine',
            'intro' => 'Craving something specific? Pick a cuisine to see the restaurants that serve it.',
        ]);
    }

    public function show(Cuisine $cuisine, RestaurantListRequest $request, RestaurantSearch $search)
    {
        $others = DirectoryCache::cuisines()->reject(fn ($other) => $other->id === $cuisine->id)->take(12)->values();

        return $this->placePage('cuisine', 'cuisines', $cuisine, "{$cuisine->name} restaurants", $request, $search, $others);
    }
}
