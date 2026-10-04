<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ShowsRestaurantPlace;
use App\Http\Requests\RestaurantListRequest;
use App\Models\City;
use App\Services\RestaurantSearch;

class CityController extends Controller
{
    use ShowsRestaurantPlace;

    public function index()
    {
        return view('places.index', [
            'kind' => 'city',
            'plural' => 'cities',
            'places' => City::withPublishedRestaurants()->get(),
            'pageTitle' => 'Restaurants by city',
            'intro' => 'Pick a city to see every restaurant we list there.',
        ]);
    }

    // {city:slug} in the route finds the city by its slug, so the address reads /city/chicago
    public function show(City $city, RestaurantListRequest $request, RestaurantSearch $search)
    {
        $others = City::withPublishedRestaurants()->whereKeyNot($city->id)->limit(8)->get();

        return $this->placePage('city', 'cities', $city, "Restaurants in {$city->name}", $request, $search, $others);
    }
}
