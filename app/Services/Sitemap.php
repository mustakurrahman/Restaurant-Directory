<?php

namespace App\Services;

use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use XMLWriter;

/**
 * Builds /sitemap.xml: the list of every public page, so search engines find them all.
 * Only pages that really exist and are public appear: published restaurants, and cities and cuisines that have at
 * least one published restaurant. Drafts, the admin and filtered or sorted views are never listed.
 *
 * One sitemap file may hold 50,000 addresses. If the directory ever gets that big, split it into several files
 * (a "sitemap index"); that is on the Version 2 list.
 */
class Sitemap
{
    public function xml(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($this->entries() as [$url, $lastModified]) {
            $xml->startElement('url');
            $xml->writeElement('loc', $url); // XMLWriter escapes &, < and > for us
            if ($lastModified) {
                $xml->writeElement('lastmod', $lastModified->toAtomString());
            }
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @return \Generator<int, array{0: string, 1: ?CarbonInterface}> */
    private function entries(): \Generator
    {
        $published = fn (Builder $query) => $query->published();

        // The pages that never change shape. "Last changed" for the list pages is the newest restaurant edit.
        $latest = Restaurant::published()->max('updated_at');
        $latest = $latest ? \Illuminate\Support\Carbon::parse($latest) : null;

        yield [route('home'), $latest];

        foreach (['restaurants.index', 'cities.index', 'cuisines.index'] as $name) {
            if (Route::has($name)) {
                yield [route($name), $latest];
            }
        }

        foreach (['submit.create', 'contact.create'] as $name) {
            if (Route::has($name)) {
                yield [route($name), null];
            }
        }

        // withMax = the newest edit among that city's published restaurants, in the same query
        foreach (City::listed()->withMax(['restaurants as last_changed' => $published], 'updated_at')->orderBy('slug')->cursor() as $city) {
            yield [route('cities.show', $city), $city->last_changed ? \Illuminate\Support\Carbon::parse($city->last_changed) : null];
        }

        foreach (Cuisine::listed()->withMax(['restaurants as last_changed' => $published], 'updated_at')->orderBy('slug')->cursor() as $cuisine) {
            yield [route('cuisines.show', $cuisine), $cuisine->last_changed ? \Illuminate\Support\Carbon::parse($cuisine->last_changed) : null];
        }

        // cursor(): one row at a time, so even a large directory does not fill the memory
        foreach (Restaurant::published()->select('id', 'slug', 'updated_at')->orderBy('slug')->cursor() as $restaurant) {
            yield [route('restaurants.show', $restaurant), $restaurant->updated_at];
        }
    }
}
