{{--
    Describes a page of restaurants to Google (JSON-LD ItemList). Needs a paginator:
    <x-restaurant-itemlist :restaurants="$restaurants" />
    Draws nothing until the restaurant pages exist, because every entry must point to a real address.
--}}
@props(['restaurants'])

@if (Route::has('restaurants.show') && $restaurants->isNotEmpty())
    @push('jsonld')
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $restaurants->values()->map(fn ($restaurant, $index) => [
                '@type' => 'ListItem',
                'position' => $restaurants->firstItem() + $index,
                'url' => route('restaurants.show', $restaurant),
                'name' => $restaurant->name,
            ])->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
@endif
