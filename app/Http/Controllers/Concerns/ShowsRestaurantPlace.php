<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\RestaurantListRequest;
use App\Services\RestaurantSearch;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared by the city and cuisine pages: "all published restaurants of one city / cuisine", sortable and paged.
 * Same rules as /restaurants because it uses the same RestaurantSearch.
 */
trait ShowsRestaurantPlace
{
    /**
     * @param  'city'|'cuisine'  $kind  also the RestaurantSearch filter name and the route prefix
     * @param  string  $pluralKind  route name prefix (cities / cuisines)
     */
    private function placePage(string $kind, string $pluralKind, Model $place, string $heading, RestaurantListRequest $request, RestaurantSearch $search, $others)
    {
        $sort = $request->filters()['sort'];

        $restaurants = $search->query([$kind => $place->slug, 'sort' => $sort])
            ->paginate(12)
            ->appends($sort === RestaurantSearch::DEFAULT_SORT ? [] : ['sort' => $sort]);

        // No published restaurants (or a page past the end) means there is nothing to show here
        abort_if($restaurants->isEmpty(), 404);

        $page = $restaurants->currentPage();
        $total = $restaurants->total();

        return view('places.show', [
            'kind' => $kind,
            'plural' => $pluralKind,
            'place' => $place,
            'heading' => $heading,
            'restaurants' => $restaurants,
            'sorts' => RestaurantSearch::SORTS,
            'sort' => $sort,
            'others' => $others,
            // Re-sorted views repeat the same restaurants, so only the default order is offered to Google
            'noindex' => $sort !== RestaurantSearch::DEFAULT_SORT,
            'canonical' => route("{$pluralKind}.show", [$place] + ($page > 1 ? ['page' => $page] : [])),
            'pageTitle' => $heading.($page > 1 ? " – page {$page}" : ''),
            'total' => $total,
        ]);
    }
}
