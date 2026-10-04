<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CuisineRequest;
use App\Models\Cuisine;

class CuisineController extends Controller
{
    public function index()
    {
        // withCount adds a restaurants_count column in one query (no N+1)
        $cuisines = Cuisine::withCount('restaurants')->orderBy('name')->get();

        return view('admin.cuisines.index', compact('cuisines'));
    }

    public function create()
    {
        return view('admin.cuisines.create');
    }

    public function store(CuisineRequest $request)
    {
        Cuisine::create($request->validated());

        return to_route('admin.cuisines.index')->with('status', 'Cuisine created.');
    }

    public function edit(Cuisine $cuisine)
    {
        return view('admin.cuisines.edit', compact('cuisine'));
    }

    public function update(CuisineRequest $request, Cuisine $cuisine)
    {
        $cuisine->update($request->validated());

        return to_route('admin.cuisines.index')->with('status', 'Cuisine updated.');
    }

    public function destroy(Cuisine $cuisine)
    {
        // Only the links in cuisine_restaurant are removed (database cascade); restaurants stay
        $cuisine->delete();

        return to_route('admin.cuisines.index')->with('status', 'Cuisine deleted.');
    }
}
