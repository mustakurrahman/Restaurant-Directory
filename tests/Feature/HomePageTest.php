<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Pretends the pages of Sprints 5 and 6 exist, so we can check the links that switch on */
    private function registerFuturePages(): void
    {
        Route::get('/restaurants', fn () => 'x')->name('restaurants.index');
        Route::get('/cities', fn () => 'x')->name('cities.index');
        Route::get('/city/{city:slug}', fn () => 'x')->name('cities.show');
        Route::get('/cuisines', fn () => 'x')->name('cuisines.index');
        Route::get('/cuisine/{cuisine:slug}', fn () => 'x')->name('cuisines.show');
        Route::get('/submit-restaurant', fn () => 'x')->name('submit.create');
        app('router')->getRoutes()->refreshNameLookups();
    }

    private function home(): string
    {
        return $this->get(route('home'))->assertOk()->getContent();
    }

    /** The visible text of a page, without tags and extra spaces */
    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    // ---------- Featured ----------

    public function test_featured_section_shows_published_featured_restaurants_only(): void
    {
        Restaurant::factory()->featured()->create(['name' => 'Star Place']);
        Restaurant::factory()->featured()->draft()->create(['name' => 'Hidden Star']);  // drafts are never public
        Restaurant::factory()->create(['name' => 'Plain Place']);                       // published but not featured

        $html = $this->home();

        $featuredSection = explode('id="featured-heading"', $html)[1];
        $featuredSection = explode('</section>', $featuredSection)[0];
        $this->assertStringContainsString('Star Place', $featuredSection);
        $this->assertStringNotContainsString('Hidden Star', $html);
        $this->assertStringNotContainsString('Plain Place', $featuredSection); // it may appear under "Newly added" instead
    }

    public function test_featured_section_shows_at_most_six_in_name_order(): void
    {
        foreach (range(1, 8) as $n) {
            Restaurant::factory()->featured()->create(['name' => sprintf('Spot %02d', $n)]);
        }

        $featuredSection = explode('</section>', explode('id="featured-heading"', $this->home())[1])[0];

        $this->assertStringContainsString('Spot 06', $featuredSection);
        $this->assertStringNotContainsString('Spot 07', $featuredSection);
        $this->assertSame(6, substr_count($featuredSection, '<article'));
    }

    public function test_cards_show_cuisines_and_approved_review_stats(): void
    {
        $restaurant = Restaurant::factory()->featured()->create();
        $restaurant->cuisines()->attach(Cuisine::factory()->create(['name' => 'Peruvian']));
        Review::factory()->create(['restaurant_id' => $restaurant->id, 'rating' => 5, 'status' => 'approved']);
        Review::factory()->create(['restaurant_id' => $restaurant->id, 'rating' => 1, 'status' => 'pending']);

        $this->get(route('home'))
            ->assertSee('Peruvian')
            ->assertSee('(1 review)')
            ->assertSee('★ 5.0');
    }

    // ---------- Numbers strip ----------

    public function test_numbers_strip_counts_published_restaurants_and_their_cities_and_cuisines(): void
    {
        $paris = City::factory()->create();
        $draftsOnly = City::factory()->create();   // has only a draft: must not be counted
        $french = Cuisine::factory()->create();
        $unused = Cuisine::factory()->create();    // no restaurants: must not be counted

        Restaurant::factory()->count(2)->create(['city_id' => $paris->id])->each(fn ($r) => $r->cuisines()->attach($french));
        Restaurant::factory()->draft()->create(['city_id' => $draftsOnly->id]);

        // In the code each label comes before its number (for screen readers); CSS shows the number on top
        $this->assertStringContainsString('Restaurants 2 City 1 Cuisine 1', $this->text($this->home()));
    }

    public function test_numbers_strip_uses_singular_and_plural_words(): void
    {
        Restaurant::factory()->create();
        $this->assertStringContainsString('Restaurant 1 City 1 Cuisines 0', $this->text($this->home()));

        Restaurant::factory()->create(); // the factory makes a new city for each restaurant
        $this->assertStringContainsString('Restaurants 2 Cities 2 Cuisines 0', $this->text($this->home()));
    }

    // ---------- Browse by city / cuisine ----------

    public function test_cities_are_listed_with_published_counts_biggest_first(): void
    {
        $small = City::factory()->create(['name' => 'Smallville']);
        $big = City::factory()->create(['name' => 'Bigtown']);
        $drafts = City::factory()->create(['name' => 'Draftburg']);
        Restaurant::factory()->count(1)->create(['city_id' => $small->id]);
        Restaurant::factory()->count(3)->create(['city_id' => $big->id]);
        Restaurant::factory()->draft()->count(5)->create(['city_id' => $big->id]); // drafts do not add to the count
        Restaurant::factory()->draft()->create(['city_id' => $drafts->id]);

        $section = explode('</section>', explode('id="cities-heading"', $this->home())[1])[0];
        $text = $this->text($section);

        $this->assertStringContainsString('Bigtown 3 restaurants', $text);
        $this->assertStringContainsString('Smallville 1 restaurant', $text);
        $this->assertStringNotContainsString('Draftburg', $text);
        $this->assertLessThan(strpos($text, 'Smallville'), strpos($text, 'Bigtown')); // biggest first
    }

    public function test_at_most_eight_cities_and_twelve_cuisines_are_shown(): void
    {
        foreach (range(1, 10) as $n) {
            $city = City::factory()->create(['name' => sprintf('City %02d', $n)]);
            $restaurant = Restaurant::factory()->create(['city_id' => $city->id]);
            $restaurant->cuisines()->attach(Cuisine::factory()->create(['name' => sprintf('Cuisine %02d', $n)]));
        }
        $restaurant = Restaurant::first();
        foreach (range(11, 15) as $n) {
            $restaurant->cuisines()->attach(Cuisine::factory()->create(['name' => sprintf('Cuisine %02d', $n)]));
        }

        $html = $this->home();
        $cities = explode('</section>', explode('id="cities-heading"', $html)[1])[0];
        $cuisines = explode('</section>', explode('id="cuisines-heading"', $html)[1])[0];

        $this->assertSame(8, substr_count($cities, '<li>'));
        $this->assertSame(12, substr_count($cuisines, '<li>'));
    }

    public function test_cuisines_are_listed_with_published_counts(): void
    {
        $italian = Cuisine::factory()->create(['name' => 'Italian']);
        $thai = Cuisine::factory()->create(['name' => 'Thai']);
        $draftOnly = Cuisine::factory()->create(['name' => 'Ghostly']);
        Restaurant::factory()->count(2)->create()->each(fn ($r) => $r->cuisines()->attach($italian));
        Restaurant::factory()->create()->cuisines()->attach($thai);
        Restaurant::factory()->draft()->create()->cuisines()->attach($draftOnly);

        $section = explode('</section>', explode('id="cuisines-heading"', $this->home())[1])[0];
        $text = $this->text($section);

        $this->assertStringContainsString('Italian 2', $text);
        $this->assertStringContainsString('Thai 1', $text);
        $this->assertStringNotContainsString('Ghostly', $text);
    }

    // ---------- Newly added ----------

    public function test_newly_added_skips_featured_and_drafts_and_shows_newest_first(): void
    {
        Restaurant::factory()->create(['name' => 'Oldest Open', 'created_at' => now()->subDays(5)]);
        Restaurant::factory()->create(['name' => 'Middle Open', 'created_at' => now()->subDays(2)]);
        Restaurant::factory()->create(['name' => 'Newest Open', 'created_at' => now()->subDay()]);
        Restaurant::factory()->featured()->create(['name' => 'Featured New', 'created_at' => now()]);
        Restaurant::factory()->draft()->create(['name' => 'Secret Newest', 'created_at' => now()]);

        $html = $this->home();
        $section = explode('</section>', explode('id="latest-heading"', $html)[1])[0];

        $this->assertStringNotContainsString('Featured New', $section); // already shown above
        $this->assertStringNotContainsString('Secret Newest', $html);
        $this->assertLessThan(strpos($section, 'Middle Open'), strpos($section, 'Newest Open'));
        $this->assertLessThan(strpos($section, 'Oldest Open'), strpos($section, 'Middle Open'));
    }

    public function test_newly_added_shows_at_most_six(): void
    {
        Restaurant::factory()->count(9)->create();

        $section = explode('</section>', explode('id="latest-heading"', $this->home())[1])[0];

        $this->assertSame(6, substr_count($section, '<article'));
    }

    // ---------- Links that switch on later ----------

    public function test_nothing_links_to_pages_that_do_not_exist_yet(): void
    {
        $city = City::factory()->create();
        Restaurant::factory()->featured()->create(['city_id' => $city->id])->cuisines()->attach(Cuisine::factory()->create());

        $html = $this->home();

        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringNotContainsString('role="search"', $html);        // no search box without a search page
        $this->assertStringNotContainsString('Submit a restaurant', $html);  // no call to action without the form
        $this->assertStringNotContainsString('All cities', $html);
        $this->assertStringContainsString($city->name, $html);               // the tile is still there, just not a link
    }

    public function test_links_and_search_switch_on_when_the_pages_exist(): void
    {
        $registered = $this->registerFuturePages();
        $city = City::factory()->create(['name' => 'Linzburg', 'slug' => 'linzburg']);
        $cuisine = Cuisine::factory()->create(['name' => 'Alpine', 'slug' => 'alpine']);
        Restaurant::factory()->featured()->create(['city_id' => $city->id])->cuisines()->attach($cuisine);

        $html = $this->home();

        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('action="'.url('/restaurants').'"', $html);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('href="'.url('/city/linzburg').'"', $html);
        $this->assertStringContainsString('href="'.url('/cuisine/alpine').'"', $html);
        $this->assertStringContainsString('href="'.url('/cities').'"', $html);
        $this->assertStringContainsString('href="'.url('/cuisines').'"', $html);
        $this->assertStringContainsString('All restaurants', $html);
        $this->assertStringContainsString('Submit a restaurant', $html);
    }

    // ---------- Google data ----------

    public function test_page_describes_the_website_to_google(): void
    {
        $data = $this->jsonLd($this->home());

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('WebSite', $data['@type']);
        $this->assertSame(config('app.name'), $data['name']);
        $this->assertArrayNotHasKey('potentialAction', $data); // no search page yet, so no search promise
    }

    public function test_page_announces_its_search_box_to_google_once_search_exists(): void
    {
        $this->registerFuturePages();

        $data = $this->jsonLd($this->home());

        $this->assertSame('SearchAction', $data['potentialAction']['@type']);
        $this->assertSame(url('/restaurants').'?q={search_term_string}', $data['potentialAction']['target']);
    }

    private function jsonLd(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
        $this->assertNotEmpty($match, 'JSON-LD block not found');
        $data = json_decode($match[1], true);
        $this->assertIsArray($data, 'JSON-LD must be valid JSON');

        return $data;
    }

    // ---------- Empty site and speed ----------

    public function test_an_empty_site_still_renders_without_sections(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('Discover great restaurants', $html);
        foreach (['featured-heading', 'cities-heading', 'cuisines-heading', 'latest-heading', 'at a glance'] as $missing) {
            $this->assertStringNotContainsString($missing, $html);
        }
    }

    public function test_home_page_query_count_does_not_grow_with_the_amount_of_data(): void
    {
        $countQueries = function (): int {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get(route('home'))->assertOk();

            return $queries;
        };

        $makeRestaurant = function (bool $featured) {
            $restaurant = Restaurant::factory()->create(['is_featured' => $featured]);
            $restaurant->cuisines()->attach(Cuisine::factory()->count(2)->create());
            Review::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'approved']);
        };

        // Start with every section filled: Laravel skips loading related data for an empty list,
        // which would make the first count unfairly small
        $makeRestaurant(true);
        $makeRestaurant(false);
        $baseline = $countQueries();

        foreach (range(1, 12) as $i) {
            $makeRestaurant($i % 2 === 0);
        }
        $withMany = $countQueries();

        $this->assertSame($baseline, $withMany, 'More restaurants, cities and cuisines must not mean more queries');
    }

    // ---------- Reusable scopes (also used by the /cities and /cuisines pages later) ----------

    public function test_listed_scopes_ignore_cities_and_cuisines_that_only_have_drafts(): void
    {
        $live = City::factory()->create();
        $dead = City::factory()->create();
        $liveCuisine = Cuisine::factory()->create();
        $deadCuisine = Cuisine::factory()->create();
        Restaurant::factory()->create(['city_id' => $live->id])->cuisines()->attach($liveCuisine);
        Restaurant::factory()->draft()->create(['city_id' => $dead->id])->cuisines()->attach($deadCuisine);

        $this->assertSame([$live->id], City::listed()->pluck('id')->all());
        $this->assertSame([$liveCuisine->id], Cuisine::listed()->pluck('id')->all());
        $this->assertSame(1, City::listed()->count()); // counting must work too
    }
}
