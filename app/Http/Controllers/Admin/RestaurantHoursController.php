<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningHoursRequest;
use App\Models\Restaurant;

class RestaurantHoursController extends Controller
{
    public function update(OpeningHoursRequest $request, Restaurant $restaurant)
    {
        $restaurant->saveOpeningHours($request->validated('hours'));

        // #hours scrolls the page down to the opening hours section
        return redirect(route('admin.restaurants.edit', $restaurant).'#hours')->with('status', 'Opening hours saved.');
    }
}
