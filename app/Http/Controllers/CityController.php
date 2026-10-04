<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ShowsRestaurantPlace;
use App\Http\Requests\RestaurantListRequest;
use App\Models\City;
use App\Services\RestaurantSearch;
use App\Support\DirectoryCache;

class CityController extends Controller
{
    use ShowsRestaurantPlace;

    public function index()
    {
        return view('places.index', [
            'kind' => 'city',
            'plural' => 'cities',
            'places' => DirectoryCache::cities(),
            'pageTitle' => 'Restaurants by city',
            'intro' => 'Pick a city to see every restaurant we list there.',
        ]);
    }

    // {city:slug} in the route finds the city by its slug, so the address reads /city/chicago
    public function show(City $city, RestaurantListRequest $request, RestaurantSearch $search)
    {
        $others = DirectoryCache::cities()->reject(fn ($other) => $other->id === $city->id)->take(8)->values();

        return $this->placePage('city', 'cities', $city, "Restaurants in {$city->name}", $request, $search, $others);
    }
}
