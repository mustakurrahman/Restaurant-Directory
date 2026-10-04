<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Models\City;

class CityController extends Controller
{
    public function index()
    {
        // withCount adds a restaurants_count column in one query (no N+1)
        $cities = City::withCount('restaurants')->orderBy('name')->get();

        return view('admin.cities.index', compact('cities'));
    }

    public function create()
    {
        return view('admin.cities.create');
    }

    public function store(CityRequest $request)
    {
        City::create($request->validated());

        return to_route('admin.cities.index')->with('status', 'City created.');
    }

    public function edit(City $city)
    {
        return view('admin.cities.edit', compact('city'));
    }

    public function update(CityRequest $request, City $city)
    {
        $city->update($request->validated());

        return to_route('admin.cities.index')->with('status', 'City updated.');
    }

    public function destroy(City $city)
    {
        // The database also blocks this; checking first lets us show a friendly message
        $count = $city->restaurants()->count();

        if ($count > 0) {
            return back()->with('error', "{$city->name} still has {$count} restaurant(s). Move or delete them first.");
        }

        $city->delete();

        return to_route('admin.cities.index')->with('status', 'City deleted.');
    }
}
