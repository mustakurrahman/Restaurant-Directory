<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $restaurants = Restaurant::query()
            // Eager load so the table does not run one extra query per row (N+1)
            ->with(['city:id,name', 'cuisines:id,name'])
            ->search($request->string('q')->trim()->toString())
            ->when($request->integer('city'), fn ($query, $cityId) => $query->where('city_id', $cityId))
            ->when(in_array($status, ['draft', 'published'], true), fn ($query) => $query->where('status', $status))
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString(); // keeps the filters when clicking page 2

        return view('admin.restaurants.index', [
            'restaurants' => $restaurants,
            'cities' => City::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
