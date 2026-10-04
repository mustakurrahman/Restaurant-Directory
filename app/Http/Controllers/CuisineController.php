<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ShowsRestaurantPlace;
use App\Http\Requests\RestaurantListRequest;
use App\Models\Cuisine;
use App\Services\RestaurantSearch;

class CuisineController extends Controller
{
    use ShowsRestaurantPlace;

    public function index()
    {
        return view('places.index', [
            'kind' => 'cuisine',
            'plural' => 'cuisines',
            'places' => Cuisine::withPublishedRestaurants()->get(),
            'pageTitle' => 'Restaurants by cuisine',
            'intro' => 'Craving something specific? Pick a cuisine to see the restaurants that serve it.',
        ]);
    }

    public function show(Cuisine $cuisine, RestaurantListRequest $request, RestaurantSearch $search)
    {
        $others = Cuisine::withPublishedRestaurants()->whereKeyNot($cuisine->id)->limit(12)->get();

        return $this->placePage('cuisine', 'cuisines', $cuisine, "{$cuisine->name} restaurants", $request, $search, $others);
    }
}
