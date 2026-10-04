<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AmenityRequest;
use App\Models\Amenity;

class AmenityController extends Controller
{
    public function index()
    {
        $amenities = Amenity::withCount('restaurants')->orderBy('name')->get();

        return view('admin.amenities.index', compact('amenities'));
    }

    public function create()
    {
        return view('admin.amenities.create');
    }

    public function store(AmenityRequest $request)
    {
        Amenity::create($request->validated());

        return to_route('admin.amenities.index')->with('status', 'Amenity created.');
    }

    public function edit(Amenity $amenity)
    {
        return view('admin.amenities.edit', compact('amenity'));
    }

    public function update(AmenityRequest $request, Amenity $amenity)
    {
        $amenity->update($request->validated());

        return to_route('admin.amenities.index')->with('status', 'Amenity updated.');
    }

    public function destroy(Amenity $amenity)
    {
        // Only the links in amenity_restaurant are removed (database cascade); restaurants stay
        $amenity->delete();

        return to_route('admin.amenities.index')->with('status', 'Amenity deleted.');
    }
}
