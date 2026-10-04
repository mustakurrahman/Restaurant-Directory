<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Review;
use App\Support\DirectoryCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards the speed work: the indexes, the cached filter lists (and that they never show stale numbers),
 * and the rating numbers that are fetched for one page of cards.
 */
class PerformanceTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function queryCount(callable $request): int
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $request();

        return $queries;
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)));
    }

    // ---------- Indexes ----------

    public function test_the_indexes_the_project_requires_exist(): void
    {
        foreach (['slug', 'status', 'is_featured'] as $column) {
            $this->assertTrue(Schema::hasIndex('restaurants', [$column]), "restaurants.{$column} needs an index");
        }

        // city_id: MySQL creates the index itself for a foreign key (restaurants_city_id_foreign, checked with SHOW INDEX);
        // the SQLite database used by the tests does not, so it can only be checked on MySQL.
        if (DB::getDriverName() === 'mysql') {
            $this->assertTrue(Schema::hasIndex('restaurants', ['city_id']), 'restaurants.city_id needs an index');
        }
        foreach (['cities', 'cuisines', 'amenities'] as $table) {
            $this->assertTrue(Schema::hasIndex($table, ['slug']), "{$table}.slug needs an index");
        }
    }

    public function test_the_columns_the_site_sorts_by_are_indexed(): void
    {
        $this->assertTrue(Schema::hasIndex('restaurants', ['name']));
        $this->assertTrue(Schema::hasIndex('restaurants', ['created_at']));
        $this->assertTrue(Schema::hasIndex('reviews', ['status', 'created_at']));
        $this->assertTrue(Schema::hasIndex('reviews', ['restaurant_id', 'status']));
        $this->assertTrue(Schema::hasIndex('reviews', ['created_at']));
    }

    // ---------- Cached lists ----------

    public function test_the_second_visit_does_not_count_restaurants_again(): void
    {
        $city = City::factory()->create();
        Restaurant::factory()->count(3)->create(['city_id' => $city->id]);

        $first = $this->queryCount(fn () => $this->get('/restaurants')->assertOk());
        $second = $this->queryCount(fn () => $this->get('/restaurants')->assertOk());

        $this->assertLessThan($first, $second, 'the filter lists should come from the cache the second time');
    }

    public function test_the_cached_lists_survive_a_real_cache_that_stores_text_not_objects(): void
    {
        // The default test cache keeps PHP objects in memory, which hides a whole class of bugs. The database cache
        // really serializes, exactly like the live site. (This test exists because that bug once reached the dev site.)
        config(['cache.default' => 'database']);
        $city = City::factory()->create(['name' => 'Chicago']);
        $restaurant = Restaurant::factory()->create(['city_id' => $city->id]);
        $restaurant->cuisines()->attach(Cuisine::factory()->create(['name' => 'Italian']));
        $restaurant->amenities()->attach(Amenity::factory()->create(['name' => 'Free Wi-Fi']));

        $pages = ['/' => 'Chicago', '/restaurants' => 'Chicago', '/cities' => 'Chicago', '/cuisines' => 'Italian',
            '/city/'.$city->slug => 'Restaurants in Chicago', '/cuisine/italian' => 'Italian restaurants'];

        foreach ($pages as $path => $expected) {
            $this->get($path)->assertOk();                        // fills the cache
            $html = $this->get($path)->assertOk()->getContent();  // reads it back
            $this->assertStringContainsString($expected, $html, $path);
        }

        $text = $this->text($this->get('/restaurants')->getContent());
        $this->assertStringContainsString('Chicago (1)', $text);
        $this->assertStringContainsString('Italian (1)', $text);
        $this->assertStringContainsString('Free Wi-Fi', $text);
        $this->assertStringContainsString('href="'.url('/city/'.$city->slug).'"', $this->get('/')->getContent()); // links still build from rebuilt models
    }

    public function test_a_new_published_restaurant_shows_in_the_counts_straight_away(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        Restaurant::factory()->create(['city_id' => $city->id]);
        $this->assertStringContainsString('Chicago (1)', $this->text($this->get('/restaurants')->getContent())); // fills the cache

        Restaurant::factory()->create(['city_id' => $city->id]);

        $this->assertStringContainsString('Chicago (2)', $this->text($this->get('/restaurants')->getContent()));
    }

    public function test_unpublishing_or_deleting_updates_the_counts_straight_away(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        $keep = Restaurant::factory()->create(['city_id' => $city->id]);
        $other = Restaurant::factory()->create(['city_id' => $city->id]);
        $this->get('/restaurants');

        $other->update(['status' => 'draft']);
        $this->assertStringContainsString('Chicago (1)', $this->text($this->get('/restaurants')->getContent()));

        $keep->delete();
        $html = $this->get('/restaurants')->getContent();
        $this->assertStringNotContainsString('Chicago', $this->text($html)); // no published restaurant left: the city drops out
        $this->get('/city/'.$city->slug)->assertNotFound();
    }

    public function test_renaming_a_city_cuisine_or_amenity_shows_straight_away(): void
    {
        $city = City::factory()->create(['name' => 'Old City']);
        $restaurant = Restaurant::factory()->create(['city_id' => $city->id]);
        $restaurant->cuisines()->attach($cuisine = Cuisine::factory()->create(['name' => 'Old Cuisine']));
        $restaurant->amenities()->attach($amenity = Amenity::factory()->create(['name' => 'Old Amenity']));
        $this->get('/restaurants');

        $city->update(['name' => 'New City']);
        $cuisine->update(['name' => 'New Cuisine']);
        $amenity->update(['name' => 'New Amenity']);

        $text = $this->text($this->get('/restaurants')->getContent());
        foreach (['New City', 'New Cuisine', 'New Amenity'] as $name) {
            $this->assertStringContainsString($name, $text);
        }
        $this->assertStringNotContainsString('Old City', $text);
    }

    public function test_changing_cuisines_in_the_admin_updates_the_counts_straight_away(): void
    {
        $city = City::factory()->create();
        $restaurant = Restaurant::factory()->create(['city_id' => $city->id]);
        $italian = Cuisine::factory()->create(['name' => 'Italian']);
        $this->assertStringNotContainsString('Italian', $this->text($this->get('/restaurants')->getContent())); // cached without it

        $this->put(route('admin.restaurants.update', $restaurant), [
            'name' => $restaurant->name, 'address' => $restaurant->address, 'city_id' => $city->id, 'price_range' => 2,
            'status' => 'published', 'cuisines' => [$italian->id],
        ])->assertRedirect();

        $this->assertStringContainsString('Italian (1)', $this->text($this->get('/restaurants')->getContent()));
    }

    public function test_the_cache_clears_when_the_list_helper_is_told_to(): void
    {
        $city = City::factory()->create();
        Restaurant::factory()->create(['city_id' => $city->id]);
        DirectoryCache::cities(); // fill

        DB::table('restaurants')->insert(['name' => 'Sneaky', 'slug' => 'sneaky', 'address' => 'x', 'city_id' => $city->id, 'price_range' => 1, 'is_featured' => 0, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(1, DirectoryCache::cities()->first()->published_restaurants_count); // raw inserts fire no events: still the cached number

        DirectoryCache::forget();
        $this->assertSame(2, DirectoryCache::cities()->first()->published_restaurants_count);
    }

    public function test_the_counted_lists_only_include_places_with_published_restaurants_and_biggest_first(): void
    {
        $big = City::factory()->create(['name' => 'Big']);
        $small = City::factory()->create(['name' => 'Small']);
        $draftOnly = City::factory()->create(['name' => 'Draftville']);
        City::factory()->create(['name' => 'Empty']);
        Restaurant::factory()->count(3)->create(['city_id' => $big->id]);
        Restaurant::factory()->create(['city_id' => $small->id]);
        Restaurant::factory()->create(['city_id' => $draftOnly->id, 'status' => 'draft']);

        $cities = City::withPublishedRestaurants()->get();

        $this->assertSame(['Big', 'Small'], $cities->pluck('name')->all());
        $this->assertSame([3, 1], $cities->pluck('published_restaurants_count')->map(fn ($n) => (int) $n)->all());
        $this->assertNotNull($cities->first()->last_changed);
    }

    // ---------- Rating numbers on the cards ----------

    public function test_rating_numbers_count_only_approved_reviews(): void
    {
        $city = City::factory()->create();
        $rated = Restaurant::factory()->create(['city_id' => $city->id, 'name' => 'Rated']);
        $unrated = Restaurant::factory()->create(['city_id' => $city->id, 'name' => 'Unrated']);
        Review::factory()->create(['restaurant_id' => $rated->id, 'rating' => 5, 'status' => 'approved']);
        Review::factory()->create(['restaurant_id' => $rated->id, 'rating' => 4, 'status' => 'approved']);
        Review::factory()->create(['restaurant_id' => $rated->id, 'rating' => 1, 'status' => 'pending']);
        Review::factory()->create(['restaurant_id' => $rated->id, 'rating' => 1, 'status' => 'rejected']);

        $text = $this->text($this->get('/restaurants')->getContent());

        $this->assertStringContainsString('Rated 4.5 out of 5, (2 reviews)', $text);
        $this->assertStringContainsString('No reviews yet', $text);
    }

    public function test_review_stats_for_a_whole_page_take_one_query(): void
    {
        $city = City::factory()->create();
        $restaurants = Restaurant::factory()->count(8)->create(['city_id' => $city->id]);
        foreach ($restaurants as $restaurant) {
            Review::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'approved']);
        }
        $loaded = Restaurant::query()->get();

        $queries = $this->queryCount(fn () => Restaurant::attachReviewStats($loaded));

        $this->assertSame(1, $queries);
        $this->assertSame(1, $loaded->first()->approved_reviews_count);
        Restaurant::attachReviewStats(collect()); // an empty list is fine and asks nothing
    }

    public function test_top_rated_sort_orders_by_average_then_number_of_reviews_then_name_and_unrated_come_last(): void
    {
        $city = City::factory()->create();
        $make = fn (string $name, array $ratings) => tap(Restaurant::factory()->create(['city_id' => $city->id, 'name' => $name]), function ($r) use ($ratings) {
            foreach ($ratings as $rating) {
                Review::factory()->create(['restaurant_id' => $r->id, 'rating' => $rating, 'status' => 'approved']);
            }
        });
        $make('Zulu Unrated', []);
        $make('Alpha Unrated', []);
        $make('Four Once', [4]);
        $make('Four Twice', [4, 4]);
        $make('Five Once', [5]);
        $make('Mixed', [5, 4, 3]); // average 4.0, three reviews

        $html = $this->get('/restaurants?sort=rating')->getContent();
        preg_match_all('#<h3[^>]*>\s*(?:<a[^>]*>)?\s*([^<]+?)\s*(?:</a>)?\s*</h3>#', $html, $m);

        $this->assertSame(['Five Once', 'Mixed', 'Four Twice', 'Four Once', 'Alpha Unrated', 'Zulu Unrated'], $m[1]);
        // and the cards still show the right numbers
        $this->assertStringContainsString('Rated 4.0 out of 5, (3 reviews)', $this->text($html));
    }

    public function test_top_rated_sort_pages_and_filters_still_work(): void
    {
        $city = City::factory()->create();
        Restaurant::factory()->count(13)->create(['city_id' => $city->id]);

        $this->get('/restaurants?sort=rating&page=2')->assertOk();
        $this->get('/restaurants?sort=rating&city='.$city->slug.'&price[]=1&price[]=2&price[]=3&price[]=4')->assertOk();
        $this->get('/city/'.$city->slug.'?sort=rating&page=2')->assertOk();
    }

    public function test_pages_stay_at_a_fixed_query_count_as_the_directory_grows(): void
    {
        $city = City::factory()->create();
        Restaurant::factory()->count(2)->create(['city_id' => $city->id]);
        $this->get('/restaurants?sort=rating');
        $few = $this->queryCount(fn () => $this->get('/restaurants?sort=rating')->assertOk());

        Restaurant::factory()->count(10)->create(['city_id' => $city->id]);
        $this->get('/restaurants?sort=rating'); // refill the cache the new restaurants cleared
        $many = $this->queryCount(fn () => $this->get('/restaurants?sort=rating')->assertOk());

        $this->assertSame($few, $many);
    }
}
