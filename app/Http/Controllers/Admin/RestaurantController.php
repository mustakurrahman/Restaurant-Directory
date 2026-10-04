<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestaurantRequest;
use App\Models\Amenity;
use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function create(Request $request)
    {
        // Sensible starting values for a brand-new restaurant
        $restaurant = new Restaurant(['status' => 'draft', 'price_range' => 2]);
        $options = $this->formOptions();
        $extra = ['submission' => null, 'prefillCuisines' => [], 'notes' => []];

        // "Create restaurant" button on the Submissions page: start from what the visitor told us.
        // An unknown id simply gives an empty form.
        $submission = Submission::find($request->integer('submission'));

        if ($submission) {
            $byName = fn ($list, ?string $name) => $name ? $list->first(fn ($item) => mb_strtolower($item->name) === mb_strtolower($name)) : null;
            $city = $byName($options['cities'], $submission->city);
            $cuisine = $byName($options['cuisines'], $submission->cuisine);

            $restaurant->fill([
                'name' => $submission->restaurant_name, 'address' => $submission->address, 'description' => $submission->description,
                'phone' => $submission->phone, 'website' => $submission->website, 'city_id' => $city?->id,
            ]);

            // Tell the owner what could not be matched, so nothing is silently lost
            $extra = [
                'submission' => $submission,
                'prefillCuisines' => $cuisine ? [$cuisine->id] : [],
                'notes' => array_values(array_filter([
                    $city ? null : "The city \"{$submission->city}\" is not in your list. Add it under Cities first, or pick another city.",
                    $submission->cuisine && ! $cuisine ? "The cuisine \"{$submission->cuisine}\" is not in your list. Add it under Cuisines if you want it." : null,
                ])),
            ];
        }

        return view('admin.restaurants.create', ['restaurant' => $restaurant] + $options + $extra);
    }

    public function store(RestaurantRequest $request)
    {
        $this->save(new Restaurant, $request);

        // Created from a visitor's suggestion: that suggestion is now dealt with
        Submission::whereKey($request->integer('from_submission'))->where('status', 'pending')->update(['status' => 'approved']);

        return to_route('admin.restaurants.index')->with('status', 'Restaurant created.');
    }

    public function edit(Restaurant $restaurant)
    {
        $restaurant->load(['cuisines', 'amenities', 'images', 'openingHours']);

        return view('admin.restaurants.edit', ['restaurant' => $restaurant] + $this->formOptions());
    }

    public function update(RestaurantRequest $request, Restaurant $restaurant)
    {
        $this->save($restaurant, $request);

        return to_route('admin.restaurants.index')->with('status', 'Restaurant updated.');
    }

    public function destroy(Restaurant $restaurant)
    {
        // The database removes its photos, hours, reviews and category links with it (cascade)
        $restaurant->delete();

        return to_route('admin.restaurants.index')->with('status', 'Restaurant deleted.');
    }

    // The lists the form needs for its dropdown and tick boxes
    private function formOptions(): array
    {
        return [
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'cuisines' => Cuisine::orderBy('name')->get(['id', 'name']),
            'amenities' => Amenity::orderBy('name')->get(['id', 'name']),
        ];
    }

    // Saves the restaurant and its category links together: all succeed or none do
    private function save(Restaurant $restaurant, RestaurantRequest $request): Restaurant
    {
        $data = $request->safe()->except(['cuisines', 'amenities']);

        DB::transaction(function () use ($restaurant, $data, $request) {
            $restaurant->fill($data)->save();

            // sync() makes the links exactly match the ticked boxes (adds new, removes unticked)
            $restaurant->cuisines()->sync($request->validated('cuisines', []));
            $restaurant->amenities()->sync($request->validated('amenities', []));
        });

        return $restaurant;
    }
}
