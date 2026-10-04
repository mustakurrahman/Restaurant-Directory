<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestaurantListRequest;
use App\Models\Amenity;
use App\Models\City;
use App\Models\Cuisine;
use App\Services\RestaurantSearch;
use Illuminate\Support\Arr;

class RestaurantController extends Controller
{
    private const PER_PAGE = 12;

    public function index(RestaurantListRequest $request, RestaurantSearch $search)
    {
        $filters = $request->filters();

        $restaurants = $search->query($filters)
            ->paginate(self::PER_PAGE)
            ->appends($this->params($filters)); // page links keep the (cleaned) filters

        // A page number past the end is a page that does not exist
        abort_if($restaurants->isEmpty() && $restaurants->currentPage() > 1, 404);

        // Choices for the filter panel (only ones that have a published restaurant)
        $cities = City::withPublishedRestaurants()->get();
        $cuisines = Cuisine::withPublishedRestaurants()->get();
        $amenities = Amenity::withPublishedRestaurants()->get();

        $page = $restaurants->currentPage();

        return view('restaurants.index', [
            'restaurants' => $restaurants,
            'filters' => $filters,
            'sorts' => RestaurantSearch::SORTS,
            'cities' => $cities,
            'cuisines' => $cuisines,
            'amenities' => $amenities,
            'chips' => $this->chips($filters, $cities, $cuisines, $amenities),
            'isFiltered' => $request->isFiltered(),
            // Search engines: plain list pages are indexable (each page number has its own canonical address);
            // filtered or re-sorted views are near-duplicates, so they are kept out of Google.
            'noindex' => $request->isFiltered() || $filters['sort'] !== RestaurantSearch::DEFAULT_SORT,
            'canonical' => route('restaurants.index', ! $request->isFiltered() && $page > 1 ? ['page' => $page] : []),
            'pageTitle' => $page > 1 ? "Restaurants – page {$page}" : 'Restaurants',
        ]);
    }

    /** The filters as address-bar parameters, leaving out everything that is empty or the default */
    private function params(array $filters, array $without = []): array
    {
        $params = array_filter([
            'q' => $filters['q'],
            'city' => $filters['city'],
            'cuisine' => $filters['cuisine'],
            'price' => $filters['price'],
            'amenities' => $filters['amenities'],
            'sort' => $filters['sort'] === RestaurantSearch::DEFAULT_SORT ? null : $filters['sort'],
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return Arr::except($params, $without);
    }

    /**
     * One removable "chip" per active filter: [label, address of this same list without that filter].
     *
     * @return list<array{label: string, url: string}>
     */
    private function chips(array $filters, $cities, $cuisines, $amenities): array
    {
        $url = fn (array $params) => route('restaurants.index', $params);
        $chips = [];

        if ($filters['q'] !== '') {
            $chips[] = ['label' => '“'.$filters['q'].'”', 'url' => $url($this->params($filters, ['q']))];
        }

        if ($filters['city']) {
            $chips[] = ['label' => $cities->firstWhere('slug', $filters['city'])?->name ?? $filters['city'], 'url' => $url($this->params($filters, ['city']))];
        }

        if ($filters['cuisine']) {
            $chips[] = ['label' => $cuisines->firstWhere('slug', $filters['cuisine'])?->name ?? $filters['cuisine'], 'url' => $url($this->params($filters, ['cuisine']))];
        }

        if ($filters['price']) {
            $chips[] = [
                'label' => 'Price: '.collect($filters['price'])->map(fn ($p) => str_repeat('$', $p))->join(', '),
                'url' => $url($this->params($filters, ['price'])),
            ];
        }

        foreach ($filters['amenities'] as $slug) {
            $others = array_values(array_diff($filters['amenities'], [$slug]));
            $chips[] = [
                'label' => $amenities->firstWhere('slug', $slug)?->name ?? $slug,
                'url' => $url($this->params(['amenities' => $others] + $filters)),
            ];
        }

        return $chips;
    }
}
