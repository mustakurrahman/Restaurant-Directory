<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RestaurantListingTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Creates a restaurant; cuisines/amenities are given as names and created on demand */
    private function restaurant(array $attributes = [], array $cuisines = [], array $amenities = []): Restaurant
    {
        $restaurant = Restaurant::factory()->create($attributes);

        foreach ($cuisines as $name) {
            $restaurant->cuisines()->attach(Cuisine::firstOrCreate(['name' => $name]));
        }
        foreach ($amenities as $name) {
            $restaurant->amenities()->attach(Amenity::firstOrCreate(['name' => $name]));
        }

        return $restaurant;
    }

    /** The page for the given address-bar parameters */
    private function page(array $params = []): string
    {
        return $this->get(route('restaurants.index', $params))->assertOk()->getContent();
    }

    /** Restaurant names in the order the cards appear */
    private function names(string $html): array
    {
        preg_match_all('#<h3[^>]*>\s*(?:<a[^>]*>)?\s*([^<]+?)\s*(?:</a>)?\s*</h3>#', $html, $matches);

        return array_map(fn ($name) => html_entity_decode($name, ENT_QUOTES), $matches[1]);
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)));
    }

    private function meta(string $html, string $pattern): ?string
    {
        return preg_match($pattern, $html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : null;
    }

    // ---------- Basics ----------

    public function test_page_lists_published_restaurants_and_never_drafts(): void
    {
        $this->restaurant(['name' => 'Open Place']);
        $this->restaurant(['name' => 'Hidden Draft', 'status' => 'draft']);

        $html = $this->page();

        $this->assertSame(['Open Place'], $this->names($html));
        $this->assertStringNotContainsString('Hidden Draft', $html);
        $this->assertStringContainsString('Showing 1–1 of 1 restaurant', $this->text($html));
    }

    public function test_default_order_is_featured_first_then_alphabetical(): void
    {
        $this->restaurant(['name' => 'Zebra Grill', 'is_featured' => true]);
        $this->restaurant(['name' => 'Apple Bistro']);
        $this->restaurant(['name' => 'Mango Table']);

        $this->assertSame(['Zebra Grill', 'Apple Bistro', 'Mango Table'], $this->names($this->page()));
    }

    public function test_page_has_heading_breadcrumb_and_the_menu_link_is_current(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('<h1 class="mt-4 text-4xl font-bold">Restaurants</h1>', $html);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('restaurants.index'), '#').'"\s+aria-current="page"#', $html);
    }

    public function test_empty_site_says_so(): void
    {
        $text = $this->text($this->page());

        $this->assertStringContainsString('No restaurants yet', $text);
        $this->assertStringContainsString('No restaurants found', $text);
    }

    // ---------- Sorting ----------

    public function test_every_sort_order_works(): void
    {
        $a = $this->restaurant(['name' => 'Alpha', 'price_range' => 4, 'created_at' => now()->subDays(3)]);
        $b = $this->restaurant(['name' => 'Bravo', 'price_range' => 1, 'created_at' => now()->subDay()]);
        $c = $this->restaurant(['name' => 'Charlie', 'price_range' => 2, 'created_at' => now()->subDays(2)]);

        // Ratings: Charlie 5.0 (1 review), Bravo 4.0 (2 reviews), Alpha none
        Review::factory()->create(['restaurant_id' => $c->id, 'rating' => 5, 'status' => 'approved']);
        Review::factory()->create(['restaurant_id' => $b->id, 'rating' => 4, 'status' => 'approved']);
        Review::factory()->create(['restaurant_id' => $b->id, 'rating' => 4, 'status' => 'approved']);
        Review::factory()->create(['restaurant_id' => $a->id, 'rating' => 1, 'status' => 'pending']); // not public: ignored

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->names($this->page(['sort' => 'name'])));
        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $this->names($this->page(['sort' => 'newest'])));
        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $this->names($this->page(['sort' => 'price_low'])));
        $this->assertSame(['Alpha', 'Charlie', 'Bravo'], $this->names($this->page(['sort' => 'price_high'])));
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->names($this->page(['sort' => 'rating'])));
    }

    public function test_the_sort_menu_belongs_to_the_filter_form_and_shows_the_current_choice(): void
    {
        $html = $this->page(['sort' => 'newest']);

        $this->assertStringContainsString('name="sort" form="filters-form" data-autosubmit', $html);
        $this->assertMatchesRegularExpression('#<option value="newest"\s+selected>#', $html);
        $this->assertDoesNotMatchRegularExpression('#<option value="recommended"\s+selected>#', $html);
    }

    public function test_unknown_sort_falls_back_to_recommended(): void
    {
        $this->restaurant(['name' => 'Zebra', 'is_featured' => true]);
        $this->restaurant(['name' => 'Apple']);

        $html = $this->page(['sort' => 'drop table']);

        $this->assertSame(['Zebra', 'Apple'], $this->names($html));
        $this->assertMatchesRegularExpression('#<option value="recommended"\s+selected>#', $html);
    }

    // ---------- Filters ----------

    public function test_keyword_search_matches_name_or_address_and_treats_percent_literally(): void
    {
        $this->restaurant(['name' => 'Pizza Palace', 'address' => '1 Oak Road']);
        $this->restaurant(['name' => 'Sushi Spot', 'address' => '22 Pizza Lane']);
        $this->restaurant(['name' => 'Taco Town', 'address' => '3 Elm Street']);
        $this->restaurant(['name' => '100% Pure', 'address' => '4 Fir Ave']);

        $this->assertEqualsCanonicalizing(['Pizza Palace', 'Sushi Spot'], $this->names($this->page(['q' => 'pizza'])));
        $this->assertSame(['100% Pure'], $this->names($this->page(['q' => '%'])));
        $this->assertSame(['Taco Town'], $this->names($this->page(['q' => '  taco   town  '])), 'extra spaces are tidied');
    }

    public function test_city_filter_uses_the_slug(): void
    {
        $rome = City::factory()->create(['name' => 'Rome']);
        $this->restaurant(['name' => 'Roman Table', 'city_id' => $rome->id]);
        $this->restaurant(['name' => 'Elsewhere']);

        $this->assertSame(['Roman Table'], $this->names($this->page(['city' => 'rome'])));
    }

    public function test_cuisine_filter_uses_the_slug(): void
    {
        $this->restaurant(['name' => 'Pasta House'], ['Italian']);
        $this->restaurant(['name' => 'Sushi Bar'], ['Japanese']);
        $this->restaurant(['name' => 'Fusion Place'], ['Italian', 'Japanese']);

        $this->assertEqualsCanonicalizing(['Pasta House', 'Fusion Place'], $this->names($this->page(['cuisine' => 'italian'])));
    }

    public function test_price_filter_accepts_one_or_several_values_and_ignores_junk(): void
    {
        $this->restaurant(['name' => 'Cheap', 'price_range' => 1]);
        $this->restaurant(['name' => 'Mid', 'price_range' => 2]);
        $this->restaurant(['name' => 'Fancy', 'price_range' => 3]);
        $this->restaurant(['name' => 'Posh', 'price_range' => 4]);

        $this->assertSame(['Mid'], $this->names($this->page(['price' => 2])));
        $this->assertEqualsCanonicalizing(['Cheap', 'Fancy'], $this->names($this->page(['price' => [1, 3]])));
        // Junk values are dropped; what is left (nothing) means "no price filter"
        $this->assertCount(4, $this->names($this->page(['price' => ['abc', '9', '0', '-1', '2.5']])));
        $this->assertSame(['Mid'], $this->names($this->page(['price' => ['abc', '2', '9']])));
    }

    public function test_a_restaurant_needs_every_ticked_amenity(): void
    {
        $this->restaurant(['name' => 'Has Both'], [], ['Wi-Fi', 'Parking']);
        $this->restaurant(['name' => 'Only Wifi'], [], ['Wi-Fi']);
        $this->restaurant(['name' => 'Neither']);

        $this->assertEqualsCanonicalizing(['Has Both', 'Only Wifi'], $this->names($this->page(['amenities' => ['wi-fi']])));
        $this->assertSame(['Has Both'], $this->names($this->page(['amenities' => ['wi-fi', 'parking']])));
        $this->assertSame([], $this->names($this->page(['amenities' => ['wi-fi', 'no-such-amenity']])));
    }

    public function test_filters_combine(): void
    {
        $oslo = City::factory()->create(['name' => 'Oslo']);
        $this->restaurant(['name' => 'Match', 'city_id' => $oslo->id, 'price_range' => 3], ['Seafood'], ['Parking']);
        $this->restaurant(['name' => 'Wrong Price', 'city_id' => $oslo->id, 'price_range' => 1], ['Seafood'], ['Parking']);
        $this->restaurant(['name' => 'Wrong Cuisine', 'city_id' => $oslo->id, 'price_range' => 3], ['Thai'], ['Parking']);
        $this->restaurant(['name' => 'Wrong City', 'price_range' => 3], ['Seafood'], ['Parking']);
        $this->restaurant(['name' => 'Draft', 'city_id' => $oslo->id, 'price_range' => 3, 'status' => 'draft'], ['Seafood'], ['Parking']);

        $html = $this->page(['q' => 'match', 'city' => 'oslo', 'cuisine' => 'seafood', 'price' => [3], 'amenities' => ['parking']]);

        $this->assertSame(['Match'], $this->names($html));
    }

    public function test_an_unknown_city_or_cuisine_shows_the_empty_state_not_an_error(): void
    {
        $this->restaurant(['name' => 'Anywhere']);

        $text = $this->text($this->page(['city' => 'atlantis']));

        $this->assertStringContainsString('No restaurants match your filters', $text);
        $this->assertStringContainsString('Clear all filters', $text);
        $this->assertSame([], $this->names($this->page(['cuisine' => 'nonexistent'])));
    }

    public function test_rubbish_in_the_address_bar_never_causes_an_error(): void
    {
        $this->restaurant(['name' => 'Fine Place']);

        $rubbish = [
            'q' => ['a', 'b'],                  // an array instead of text
            'city' => ['x'],
            'cuisine' => 'Not A Slug!',
            'price' => 'banana',
            'amenities' => 'wifi',               // text instead of a list
            'sort' => ['name'],
            'page' => 'last',
        ];

        $this->get(route('restaurants.index', $rubbish))->assertOk();
        $this->get('/restaurants?amenities[][]=x&price[a][b]=1&city=%00')->assertOk();
        $this->get('/restaurants?q='.str_repeat('x', 5000))->assertOk();
    }

    public function test_the_filter_form_shows_the_current_choices(): void
    {
        $paris = City::factory()->create(['name' => 'Paris']);
        $this->restaurant(['city_id' => $paris->id, 'price_range' => 3], ['French'], ['Parking', 'Wi-Fi']);

        $html = $this->page(['q' => 'bistro', 'city' => 'paris', 'cuisine' => 'french', 'price' => [3], 'amenities' => ['parking']]);

        $this->assertStringContainsString('name="q" type="search" value="bistro"', $html);
        $this->assertMatchesRegularExpression('#<option value="paris"\s+selected>#', $html);
        $this->assertMatchesRegularExpression('#<option value="french"\s+selected>#', $html);
        $this->assertMatchesRegularExpression('#name="price\[\]" value="3"\s+checked#', $html);
        $this->assertDoesNotMatchRegularExpression('#name="price\[\]" value="2"\s+checked#', $html);
        $this->assertMatchesRegularExpression('#name="amenities\[\]" value="parking"\s+checked#', $html);
        $this->assertDoesNotMatchRegularExpression('#name="amenities\[\]" value="wi-fi"\s+checked#', $html);
    }

    public function test_filter_choices_only_list_things_with_published_restaurants_and_count_only_published(): void
    {
        $live = City::factory()->create(['name' => 'Liveton']);
        $dead = City::factory()->create(['name' => 'Draftville']);
        $this->restaurant(['city_id' => $live->id], ['Fusion'], ['Rooftop']);
        $this->restaurant(['city_id' => $live->id, 'status' => 'draft'], ['Fusion']);
        $this->restaurant(['city_id' => $dead->id, 'status' => 'draft'], ['Ghostly'], ['Haunted']);

        $text = $this->text($this->page());

        $this->assertStringContainsString('Liveton (1)', $text);   // the draft is not counted
        $this->assertStringContainsString('Fusion (1)', $text);
        $this->assertStringContainsString('Rooftop', $text);
        $this->assertStringNotContainsString('Draftville', $text);
        $this->assertStringNotContainsString('Ghostly', $text);
        $this->assertStringNotContainsString('Haunted', $text);
    }

    // ---------- Active filter chips and the phone panel ----------

    public function test_active_filters_show_as_removable_chips(): void
    {
        $paris = City::factory()->create(['name' => 'Paris']);
        $this->restaurant(['city_id' => $paris->id, 'price_range' => 2], ['French'], ['Parking', 'Wi-Fi']);

        $html = $this->page(['q' => 'bistro', 'city' => 'paris', 'price' => [2, 3], 'amenities' => ['parking', 'wi-fi']]);

        $text = $this->text($html);
        foreach (['“bistro”', 'Paris', 'Price: $$, $$$', 'Parking', 'Wi-Fi'] as $label) {
            $this->assertStringContainsString($label, $text);
        }

        // Removing the city keeps everything else
        preg_match('#<a href="([^"]+)"[^>]*>\s*Paris\s*<span#', $html, $m);
        $removeCity = html_entity_decode($m[1]);
        $this->assertStringNotContainsString('city=', $removeCity);
        $this->assertStringContainsString('q=bistro', $removeCity);
        $this->assertStringContainsString('price%5B0%5D=2', $removeCity);

        // Removing one amenity keeps the other
        preg_match('#<a href="([^"]+)"[^>]*>\s*Parking\s*<span#', $html, $m);
        $removeParking = html_entity_decode($m[1]);
        $this->assertStringContainsString('amenities%5B0%5D=wi-fi', $removeParking);
        $this->assertStringNotContainsString('parking', $removeParking);
    }

    public function test_filter_panel_is_open_when_filtering_and_closed_otherwise(): void
    {
        $this->restaurant();

        $closed = $this->page();
        $open = $this->page(['q' => 'x']);

        $this->assertMatchesRegularExpression('#<div id="filters-panel" class="hidden #', $closed);
        $this->assertStringContainsString('aria-expanded="false"', $closed);
        $this->assertDoesNotMatchRegularExpression('#<div id="filters-panel" class="hidden #', $open);
        $this->assertStringContainsString('aria-expanded="true"', $open);
    }

    public function test_sorting_alone_does_not_count_as_filtering(): void
    {
        $this->restaurant();

        $text = $this->text($this->page(['sort' => 'name']));

        $this->assertStringContainsString('Showing 1–1 of 1 restaurant', $text); // not "1 restaurant match"
        $this->assertStringNotContainsString('Clear all', $text);
    }

    // ---------- Pagination ----------

    public function test_twelve_per_page_and_the_second_page_has_the_rest(): void
    {
        foreach (range(1, 14) as $n) {
            $this->restaurant(['name' => sprintf('Spot %02d', $n)]);
        }

        $first = $this->page();
        $this->assertCount(12, $this->names($first));
        $this->assertStringContainsString('Showing 1–12 of 14 restaurants', $this->text($first));

        $second = $this->page(['page' => 2]);
        $this->assertSame(['Spot 13', 'Spot 14'], $this->names($second));
        $this->assertStringContainsString('Showing 13–14 of 14 restaurants', $this->text($second));
    }

    public function test_page_links_keep_the_cleaned_filters_and_sort_but_drop_junk(): void
    {
        foreach (range(1, 14) as $n) {
            $this->restaurant(['name' => sprintf('Spot %02d', $n)]);
        }

        $html = $this->page(['q' => 'spot', 'sort' => 'name', 'junk' => 'x']);

        preg_match('#<a href="([^"]*page=2[^"]*)"#', $html, $m);
        $next = html_entity_decode($m[1]);

        $this->assertStringContainsString('q=spot', $next);
        $this->assertStringContainsString('sort=name', $next);
        $this->assertStringNotContainsString('junk', $next);
    }

    public function test_a_page_past_the_end_is_a_404(): void
    {
        $this->restaurant();

        $this->get(route('restaurants.index', ['page' => 99]))->assertNotFound();
    }

    // ---------- Search engines ----------

    public function test_the_plain_list_is_indexable_with_its_own_canonical(): void
    {
        foreach (range(1, 14) as $n) {
            $this->restaurant();
        }

        $one = $this->page();
        $this->assertStringContainsString('content="index, follow"', $one);
        $this->assertSame(route('restaurants.index'), $this->meta($one, '#<link rel="canonical" href="([^"]+)">#'));
        $this->assertStringContainsString('<title>Restaurants | '.config('app.name').'</title>', $one);

        $two = $this->page(['page' => 2]);
        $this->assertStringContainsString('content="index, follow"', $two);
        $this->assertSame(route('restaurants.index', ['page' => 2]), $this->meta($two, '#<link rel="canonical" href="([^"]+)">#'));
        $this->assertStringContainsString('<title>Restaurants – page 2 | '.config('app.name').'</title>', $two);
    }

    public function test_filtered_and_re_sorted_views_are_kept_out_of_google(): void
    {
        $this->restaurant();

        foreach ([['q' => 'a'], ['city' => 'x'], ['cuisine' => 'x'], ['price' => [2]], ['amenities' => ['x']], ['sort' => 'newest']] as $params) {
            $html = $this->page($params);

            $this->assertStringContainsString('content="noindex, follow"', $html, json_encode($params));
            // They all point at the one official list page
            $this->assertSame(route('restaurants.index'), $this->meta($html, '#<link rel="canonical" href="([^"]+)">#'), json_encode($params));
        }
    }

    public function test_description_mentions_the_number_of_restaurants(): void
    {
        $this->restaurant();
        $this->restaurant();

        $this->assertStringContainsString('Browse 2 restaurants.', $this->meta($this->page(), '#<meta name="description" content="([^"]+)">#'));
    }

    public function test_breadcrumb_data_for_google_is_valid(): void
    {
        $html = $this->page();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);
        $found = collect($blocks[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', 'BreadcrumbList');

        $this->assertNotNull($found, 'BreadcrumbList missing');
        $this->assertSame('Home', $found['itemListElement'][0]['name']);
        $this->assertSame(route('home'), $found['itemListElement'][0]['item']);
        $this->assertSame('Restaurants', $found['itemListElement'][1]['name']);
        $this->assertSame(2, $found['itemListElement'][1]['position']);
    }

    public function test_the_list_is_described_to_google_with_links_to_the_restaurant_pages(): void
    {
        $this->restaurant(['name' => 'Listed One', 'slug' => 'listed-one']);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $this->page(), $blocks);
        $list = collect($blocks[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', 'ItemList');

        $this->assertSame(url('/restaurant/listed-one'), $list['itemListElement'][0]['url']);
        $this->assertSame('Listed One', $list['itemListElement'][0]['name']);
        $this->assertSame(1, $list['itemListElement'][0]['position']);
    }

    // ---------- Cards and speed ----------

    public function test_cards_show_approved_rating_stats(): void
    {
        $restaurant = $this->restaurant(['name' => 'Rated Place']);
        Review::factory()->create(['restaurant_id' => $restaurant->id, 'rating' => 4, 'status' => 'approved']);
        Review::factory()->create(['restaurant_id' => $restaurant->id, 'rating' => 5, 'status' => 'approved']);
        Review::factory()->create(['restaurant_id' => $restaurant->id, 'rating' => 1, 'status' => 'pending']);

        $text = $this->text($this->page());

        $this->assertStringContainsString('★ 4.5', $text);
        $this->assertStringContainsString('(2 reviews)', $text);
    }

    public function test_query_count_stays_the_same_however_much_data_there_is(): void
    {
        $countQueries = function (): int {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get(route('restaurants.index', ['q' => 'spot', 'price' => [1, 2, 3, 4], 'sort' => 'rating']))->assertOk();

            return $queries;
        };

        $make = function (int $n) {
            $restaurant = $this->restaurant(['name' => "Spot {$n}"], ["Cuisine {$n}", 'Shared'], ["Amenity {$n}"]);
            Review::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'approved']);
        };

        // A full first page, so every query type runs in both measurements
        foreach (range(1, 12) as $n) {
            $make($n);
        }
        $baseline = $countQueries();

        foreach (range(13, 40) as $n) {
            $make($n);
        }
        $bigger = $countQueries();

        $this->assertSame($baseline, $bigger, 'More restaurants must not mean more queries');
        $this->assertLessThan(15, $bigger);
    }
}
