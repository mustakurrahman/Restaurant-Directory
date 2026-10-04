<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CityCuisinePagesTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function names(string $html): array
    {
        preg_match_all('#<h3[^>]*>\s*(?:<a[^>]*>)?\s*([^<]+?)\s*(?:</a>)?\s*</h3>#', $html, $matches);

        return array_map(fn ($name) => html_entity_decode($name, ENT_QUOTES), $matches[1]);
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)));
    }

    private function inCity(City $city, array $attributes = [], ?Cuisine $cuisine = null): Restaurant
    {
        $restaurant = Restaurant::factory()->create($attributes + ['city_id' => $city->id]);
        if ($cuisine) {
            $restaurant->cuisines()->attach($cuisine);
        }

        return $restaurant;
    }

    // ---------- City page ----------

    public function test_city_page_lists_only_that_citys_published_restaurants(): void
    {
        $chicago = City::factory()->create(['name' => 'Chicago']);
        $london = City::factory()->create(['name' => 'London']);
        $this->inCity($chicago, ['name' => 'Deep Dish Hall']);
        $this->inCity($chicago, ['name' => 'Hidden Draft', 'status' => 'draft']);
        $this->inCity($london, ['name' => 'Pie Shop']);

        $html = $this->get('/city/chicago')->assertOk()->getContent();

        $this->assertSame(['Deep Dish Hall'], $this->names($html));
        $this->assertStringNotContainsString('Hidden Draft', $html);
        $this->assertStringContainsString('Restaurants in Chicago', $this->text($html));
        $this->assertStringContainsString('<title>Restaurants in Chicago | '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('1 restaurant in Chicago', $this->text($html));
    }

    public function test_city_page_has_seo_tags_and_breadcrumbs(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        $this->inCity($city);

        $html = $this->get('/city/chicago')->assertOk()->getContent();

        $this->assertStringContainsString('rel="canonical" href="'.url('/city/chicago').'"', $html);
        $this->assertStringContainsString('content="index, follow"', $html);
        $this->assertMatchesRegularExpression('/name="description" content="Browse 1 restaurant in Chicago\./', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('href="'.route('cities.index').'"', $html);
    }

    public function test_city_without_published_restaurants_or_unknown_city_is_404(): void
    {
        $empty = City::factory()->create(['name' => 'Ghost Town']);
        $onlyDraft = City::factory()->create(['name' => 'Draftville']);
        $this->inCity($onlyDraft, ['status' => 'draft']);

        $this->get('/city/'.$empty->slug)->assertNotFound();
        $this->get('/city/draftville')->assertNotFound();
        $this->get('/city/atlantis')->assertNotFound();
    }

    public function test_city_page_paginates_and_a_page_past_the_end_is_404(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        foreach (range(1, 13) as $n) {
            $this->inCity($city, ['name' => sprintf('Place %02d', $n)]);
        }

        $page1 = $this->get('/city/chicago?sort=name')->assertOk()->getContent();
        $this->assertCount(12, $this->names($page1));

        $page2 = $this->get('/city/chicago?page=2')->assertOk()->getContent();
        $this->assertCount(1, $this->names($page2));
        $this->assertStringContainsString('rel="canonical" href="'.url('/city/chicago?page=2').'"', $page2);
        $this->assertStringContainsString('Restaurants in Chicago – page 2', $this->text($page2));

        $this->get('/city/chicago?page=3')->assertNotFound();
    }

    public function test_city_sort_works_and_a_sorted_view_is_noindex_pointing_at_the_plain_page(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        $this->inCity($city, ['name' => 'Cheap Eats', 'price_range' => 1]);
        $this->inCity($city, ['name' => 'Posh Place', 'price_range' => 4, 'is_featured' => true]);

        $this->assertSame(['Posh Place', 'Cheap Eats'], $this->names($this->get('/city/chicago')->getContent()));

        $html = $this->get('/city/chicago?sort=price_low')->assertOk()->getContent();
        $this->assertSame(['Cheap Eats', 'Posh Place'], $this->names($html));
        $this->assertStringContainsString('content="noindex, follow"', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/city/chicago').'"', $html);

        // junk sort is ignored, not an error
        $this->get('/city/chicago?sort[]=x')->assertOk();
        $this->get('/city/chicago?sort=bogus')->assertOk();
    }

    public function test_city_page_links_to_other_cities_but_not_unlisted_ones(): void
    {
        $chicago = City::factory()->create(['name' => 'Chicago']);
        $london = City::factory()->create(['name' => 'London']);
        City::factory()->create(['name' => 'Ghost Town']);
        $this->inCity($chicago);
        $this->inCity($london);

        $html = $this->get('/city/chicago')->getContent();

        $this->assertStringContainsString('href="'.url('/city/london').'"', $html);
        $this->assertStringNotContainsString('ghost-town', $html);
    }

    public function test_city_page_does_not_run_a_query_per_restaurant(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        $italian = Cuisine::factory()->create();
        foreach (range(1, 3) as $ignored) {
            $this->inCity($city, [], $italian);
        }
        $this->get('/city/chicago'); // warm up

        $count = function () {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get('/city/chicago')->assertOk();

            return $queries;
        };

        $few = $count();
        foreach (range(1, 6) as $ignored) {
            $this->inCity($city, [], $italian);
        }
        $this->get('/city/chicago'); // adding restaurants clears the cached city list; let it fill again first

        $this->assertSame($few, $count());
    }

    // ---------- Cuisine page ----------

    public function test_cuisine_page_lists_only_that_cuisines_published_restaurants(): void
    {
        $city = City::factory()->create();
        $italian = Cuisine::factory()->create(['name' => 'Italian']);
        $thai = Cuisine::factory()->create(['name' => 'Thai']);
        $this->inCity($city, ['name' => 'Trattoria'], $italian);
        $this->inCity($city, ['name' => 'Draft Trattoria', 'status' => 'draft'], $italian);
        $this->inCity($city, ['name' => 'Pad Thai House'], $thai);

        $html = $this->get('/cuisine/italian')->assertOk()->getContent();

        $this->assertSame(['Trattoria'], $this->names($html));
        $this->assertStringContainsString('<title>Italian restaurants | '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/cuisine/italian').'"', $html);
        $this->assertStringContainsString('1 restaurant serving Italian food', $this->text($html));
        $this->assertStringContainsString('href="'.url('/cuisine/thai').'"', $html); // other cuisines
    }

    public function test_cuisine_without_published_restaurants_or_unknown_is_404(): void
    {
        Cuisine::factory()->create(['name' => 'Unused']);
        $draftOnly = Cuisine::factory()->create(['name' => 'Draft Only']);
        $this->inCity(City::factory()->create(), ['status' => 'draft'], $draftOnly);

        $this->get('/cuisine/unused')->assertNotFound();
        $this->get('/cuisine/draft-only')->assertNotFound();
        $this->get('/cuisine/nope')->assertNotFound();
    }

    // ---------- Index pages ----------

    public function test_cities_index_lists_listed_cities_with_counts_and_links(): void
    {
        $big = City::factory()->create(['name' => 'Chicago']);
        $small = City::factory()->create(['name' => 'London']);
        City::factory()->create(['name' => 'Ghost Town']);
        $this->inCity($big);
        $this->inCity($big);
        $this->inCity($small);
        $this->inCity($small, ['status' => 'draft']);

        $html = $this->get('/cities')->assertOk()->getContent();
        $text = $this->text($html);

        $this->assertStringContainsString('<title>Restaurants by city | '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('Chicago 2 restaurants', $text);
        $this->assertStringContainsString('London 1 restaurant', $text); // the draft is not counted
        $this->assertStringNotContainsString('Ghost Town', $html);
        $this->assertStringContainsString('href="'.url('/city/chicago').'"', $html);
        $this->assertTrue(strpos($html, 'Chicago') < strpos($html, 'London'), 'biggest city first');
    }

    public function test_cuisines_index_lists_listed_cuisines_with_links(): void
    {
        $italian = Cuisine::factory()->create(['name' => 'Italian']);
        Cuisine::factory()->create(['name' => 'Unused']);
        $this->inCity(City::factory()->create(), [], $italian);

        $html = $this->get('/cuisines')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.url('/cuisine/italian').'"', $html);
        $this->assertStringNotContainsString('Unused', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
    }

    public function test_index_pages_with_nothing_to_show_still_work(): void
    {
        $this->get('/cities')->assertOk()->assertSee('No cities yet');
        $this->get('/cuisines')->assertOk()->assertSee('No cuisines yet');
    }

    // ---------- Links elsewhere on the site ----------

    public function test_homepage_tiles_and_menu_now_link_to_these_pages(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        $italian = Cuisine::factory()->create(['name' => 'Italian']);
        $this->inCity($city, [], $italian);

        $html = $this->get('/')->assertOk()->getContent();

        foreach (['/city/chicago', '/cuisine/italian', '/cities', '/cuisines'] as $path) {
            $this->assertStringContainsString('href="'.url($path).'"', $html, "home page should link to {$path}");
        }
    }

    public function test_the_menu_marks_the_section_as_current(): void
    {
        $city = City::factory()->create();
        $this->inCity($city);

        $html = $this->get('/city/'.$city->slug)->getContent();

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('cities.index'), '#').'"[^>]*aria-current="page"|aria-current="page"[^>]*href="'.preg_quote(route('cities.index'), '#').'"#', $html);
    }
}
