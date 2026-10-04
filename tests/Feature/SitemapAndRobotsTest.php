<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SitemapAndRobotsTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    /** The addresses listed in the sitemap, after checking that it is valid XML */
    private function urls(): array
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $xml = simplexml_load_string($response->getContent());

        $this->assertNotFalse($xml, 'the sitemap is not valid XML');

        return array_map(fn ($entry) => (string) $entry->loc, iterator_to_array($xml->url, false));
    }

    // ---------- sitemap.xml ----------

    public function test_it_is_xml_with_the_right_headers(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $response->getContent());
        $this->assertStringContainsString('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', $response->getContent());
    }

    public function test_the_fixed_pages_are_listed_even_with_no_restaurants(): void
    {
        $urls = $this->urls();

        foreach (['home', 'restaurants.index', 'cities.index', 'cuisines.index', 'submit.create', 'contact.create'] as $name) {
            $this->assertContains(route($name), $urls, "{$name} is missing");
        }
    }

    public function test_published_restaurants_cities_and_cuisines_are_listed(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        $cuisine = Cuisine::factory()->create(['name' => 'Italian']);
        $restaurant = Restaurant::factory()->create(['city_id' => $city->id, 'slug' => 'chez-marie']);
        $restaurant->cuisines()->attach($cuisine);

        $urls = $this->urls();

        $this->assertContains(url('/restaurant/chez-marie'), $urls);
        $this->assertContains(url('/city/chicago'), $urls);
        $this->assertContains(url('/cuisine/italian'), $urls);
    }

    public function test_drafts_and_empty_cities_and_cuisines_are_never_listed(): void
    {
        $draftOnlyCity = City::factory()->create(['name' => 'Draftville']);
        $draftOnlyCuisine = Cuisine::factory()->create(['name' => 'Draft Food']);
        $draft = Restaurant::factory()->create(['city_id' => $draftOnlyCity->id, 'slug' => 'secret-draft', 'status' => 'draft']);
        $draft->cuisines()->attach($draftOnlyCuisine);
        City::factory()->create(['name' => 'Ghost Town']);
        Cuisine::factory()->create(['name' => 'Unused']);

        $body = $this->get('/sitemap.xml')->getContent();

        foreach (['secret-draft', 'draftville', 'draft-food', 'ghost-town', 'unused'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $body, "{$hidden} must not be public");
        }
    }

    public function test_the_admin_filtered_views_and_form_posts_are_never_listed(): void
    {
        Restaurant::factory()->create();

        foreach ($this->urls() as $url) {
            $this->assertStringNotContainsString('/admin', $url);
            $this->assertStringNotContainsString('?', $url);           // no filtered or sorted views
            $this->assertStringNotContainsString('/reviews', $url);
            $this->assertStringNotContainsString('preview-error', $url);
        }
    }

    public function test_every_address_appears_once_and_is_absolute(): void
    {
        Restaurant::factory()->count(3)->create();

        $urls = $this->urls();

        $this->assertSame($urls, array_values(array_unique($urls)));
        foreach ($urls as $url) {
            $this->assertStringStartsWith('http', $url);
        }
    }

    public function test_last_changed_dates_are_given_in_the_standard_format(): void
    {
        $city = City::factory()->create();
        Restaurant::factory()->create(['city_id' => $city->id, 'slug' => 'dated-place', 'updated_at' => '2026-03-04 10:20:30']);

        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

        $dates = [];
        foreach ($xml->url as $entry) {
            $dates[(string) $entry->loc] = (string) $entry->lastmod;
        }

        $this->assertStringStartsWith('2026-03-04T10:20:30', $dates[url('/restaurant/dated-place')]);
        $this->assertStringStartsWith('2026-03-04T10:20:30', $dates[url('/city/'.$city->slug)]);
        $this->assertStringStartsWith('2026-03-04T10:20:30', $dates[route('home')]);
        $this->assertSame('', $dates[route('contact.create')]); // static pages have no date
        foreach ($dates as $url => $date) {
            if ($date !== '') {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $date, $url);
            }
        }
    }

    public function test_a_restaurant_changing_back_to_draft_leaves_the_sitemap(): void
    {
        $restaurant = Restaurant::factory()->create(['slug' => 'flip-flop']);
        $this->assertContains(url('/restaurant/flip-flop'), $this->urls());

        $restaurant->update(['status' => 'draft']);

        $this->assertNotContains(url('/restaurant/flip-flop'), $this->urls());
    }

    public function test_the_number_of_queries_does_not_grow_with_the_directory(): void
    {
        $count = function () {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get('/sitemap.xml')->assertOk();

            return $queries;
        };

        $city = City::factory()->create();
        Restaurant::factory()->count(2)->create(['city_id' => $city->id]);
        $few = $count();

        foreach (City::factory()->count(5)->create() as $extra) {
            Restaurant::factory()->count(3)->create(['city_id' => $extra->id]);
        }

        $this->assertSame($few, $count());
    }

    // ---------- robots.txt ----------

    public function test_robots_blocks_everything_outside_production(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());
    }

    public function test_robots_in_production_allows_the_site_hides_admin_and_points_to_the_sitemap(): void
    {
        $this->app['env'] = 'production';

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString("User-agent: *\n", $body);
        $this->assertStringContainsString("Disallow: /admin\n", $body);
        $this->assertStringNotContainsString("Disallow: /\n", $body); // the site itself stays open
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
        $this->assertStringNotContainsString('Disallow: /restaurants', $body); // filtered pages are noindex, which Google can only see if it may visit
    }

    public function test_both_files_are_cacheable_so_they_must_not_set_cookies(): void
    {
        foreach (['/sitemap.xml', '/robots.txt'] as $path) {
            $response = $this->get($path)->assertOk();

            $this->assertEmpty($response->headers->getCookies(), "{$path} sets a cookie but is publicly cacheable");
            $this->assertNull($response->headers->get('Set-Cookie'));
        }
    }

    public function test_no_static_robots_file_hides_the_generated_one(): void
    {
        // A file in public/ is served before Laravel is even asked, so it must not exist
        $this->assertFileDoesNotExist(public_path('robots.txt'));
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
    }
}
