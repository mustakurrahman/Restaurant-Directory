<?php

namespace App\Http\Requests;

use App\Services\RestaurantSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Turns whatever is in the address bar into safe filters.
 * It never rejects anything: a public page that answers a typo with an error is worse than one that ignores it.
 */
class RestaurantListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return []; // cleaning happens in filters(), not by refusing the request
    }

    /**
     * @return array{q: string, city: ?string, cuisine: ?string, price: list<int>, amenities: list<string>, sort: string}
     */
    public function filters(): array
    {
        $sort = $this->query('sort');

        return [
            'q' => $this->text('q', 100) ?? '',
            'city' => $this->slug('city'),
            'cuisine' => $this->slug('cuisine'),
            // price=3 and price[]=2&price[]=3 both work; anything outside 1-4 is dropped
            'price' => collect((array) $this->query('price', []))
                ->filter(fn ($value) => is_scalar($value) && ctype_digit((string) $value))
                ->map(fn ($value) => (int) $value)
                ->filter(fn (int $value) => $value >= 1 && $value <= 4)
                ->unique()->sort()->values()->all(),
            'amenities' => collect((array) $this->query('amenities', []))
                ->map(fn ($value) => is_string($value) ? $this->cleanSlug($value) : null)
                ->filter()->unique()->take(12)->values()->all(),
            'sort' => is_string($sort) && array_key_exists($sort, RestaurantSearch::SORTS) ? $sort : RestaurantSearch::DEFAULT_SORT,
        ];
    }

    // True when the visitor narrowed the list (sorting alone does not count)
    public function isFiltered(): bool
    {
        $filters = $this->filters();

        return $filters['q'] !== '' || $filters['city'] || $filters['cuisine'] || $filters['price'] || $filters['amenities'];
    }

    private function text(string $key, int $max): ?string
    {
        $value = $this->query($key);

        return is_string($value) ? (Str::of($value)->squish()->limit($max, '')->toString() ?: null) : null;
    }

    private function slug(string $key): ?string
    {
        $value = $this->query($key);

        return is_string($value) ? $this->cleanSlug($value) : null;
    }

    // Slugs are lowercase letters, numbers and hyphens; anything else cannot match a real slug, so it is dropped
    private function cleanSlug(string $value): ?string
    {
        $value = trim(Str::lower($value));

        return $value !== '' && strlen($value) <= 255 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) ? $value : null;
    }
}
