<?php

namespace Tests\Feature\Admin;

use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RestaurantListTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // pages render without needing compiled CSS
    }

    public function test_list_shows_restaurants_with_city_cuisines_and_status(): void
    {
        $city = City::factory()->create(['name' => 'Paris']);
        $restaurant = Restaurant::factory()->featured()->create(['name' => 'Chez Marie', 'city_id' => $city->id]);
        $restaurant->cuisines()->attach(Cuisine::factory()->create(['name' => 'French']));

        $this->get(route('admin.restaurants.index'))
            ->assertOk()
            ->assertSee('Chez Marie')
            ->assertSee('Paris')
            ->assertSee('French')
            ->assertSee('Published')
            ->assertSee('Featured')
            ->assertSee('1 restaurant');
    }

    public function test_list_includes_drafts_unlike_the_public_site(): void
    {
        Restaurant::factory()->draft()->create(['name' => 'Hidden Gem']);

        $this->get(route('admin.restaurants.index'))->assertSee('Hidden Gem')->assertSee('Draft');
    }

    public function test_search_matches_name_or_address(): void
    {
        Restaurant::factory()->create(['name' => 'Pizza Palace', 'address' => '1 Oak Road']);
        Restaurant::factory()->create(['name' => 'Sushi Spot', 'address' => '22 Pizza Lane']);
        Restaurant::factory()->create(['name' => 'Taco Town', 'address' => '3 Elm Street']);

        $this->get(route('admin.restaurants.index', ['q' => 'pizza']))
            ->assertSee('Pizza Palace')
            ->assertSee('Sushi Spot')   // matched by address
            ->assertDontSee('Taco Town');
    }

    public function test_search_treats_percent_as_a_normal_character(): void
    {
        Restaurant::factory()->create(['name' => '100% Pure']);
        Restaurant::factory()->create(['name' => 'Another Place']);

        $this->get(route('admin.restaurants.index', ['q' => '%']))
            ->assertSee('100% Pure')
            ->assertDontSee('Another Place');
    }

    public function test_filter_by_city(): void
    {
        $rome = City::factory()->create(['name' => 'Rome']);
        Restaurant::factory()->create(['name' => 'Roman Table', 'city_id' => $rome->id]);
        Restaurant::factory()->create(['name' => 'Elsewhere Cafe']);

        $this->get(route('admin.restaurants.index', ['city' => $rome->id]))
            ->assertSee('Roman Table')
            ->assertDontSee('Elsewhere Cafe');
    }

    public function test_filter_by_status_and_featured(): void
    {
        Restaurant::factory()->create(['name' => 'Live One']);
        Restaurant::factory()->draft()->create(['name' => 'Draft One']);
        Restaurant::factory()->featured()->create(['name' => 'Star One']);

        $this->get(route('admin.restaurants.index', ['status' => 'draft']))
            ->assertSee('Draft One')->assertDontSee('Live One');

        $this->get(route('admin.restaurants.index', ['status' => 'published']))
            ->assertSee('Live One')->assertSee('Star One')->assertDontSee('Draft One');

        $this->get(route('admin.restaurants.index', ['featured' => 1]))
            ->assertSee('Star One')->assertDontSee('Live One');
    }

    public function test_unknown_status_value_is_ignored(): void
    {
        Restaurant::factory()->create(['name' => 'Live One']);
        Restaurant::factory()->draft()->create(['name' => 'Draft One']);

        $this->get(route('admin.restaurants.index', ['status' => 'bogus']))
            ->assertOk()->assertSee('Live One')->assertSee('Draft One');
    }

    public function test_filters_combine(): void
    {
        $oslo = City::factory()->create(['name' => 'Oslo']);
        Restaurant::factory()->create(['name' => 'Fjord Fish', 'city_id' => $oslo->id]);
        Restaurant::factory()->draft()->create(['name' => 'Fjord Draft', 'city_id' => $oslo->id]);
        Restaurant::factory()->create(['name' => 'Fjord Elsewhere']);

        $this->get(route('admin.restaurants.index', ['q' => 'fjord', 'city' => $oslo->id, 'status' => 'published']))
            ->assertSee('Fjord Fish')
            ->assertDontSee('Fjord Draft')
            ->assertDontSee('Fjord Elsewhere');
    }

    public function test_list_is_paginated_15_per_page_and_keeps_filters(): void
    {
        // Names sort alphabetically: Item 01 ... Item 20
        foreach (range(1, 20) as $n) {
            Restaurant::factory()->create(['name' => sprintf('Item %02d', $n)]);
        }

        $this->get(route('admin.restaurants.index'))
            ->assertSee('Item 15')->assertDontSee('Item 16')
            ->assertSee('Showing 1 to 15 of 20')
            ->assertSee('page=2', false);

        $this->get(route('admin.restaurants.index', ['page' => 2]))
            ->assertSee('Item 16')->assertSee('Item 20')->assertDontSee('Item 15');

        // Filters stay in the page links
        $this->get(route('admin.restaurants.index', ['q' => 'Item']))
            ->assertSee('q=Item', false);
    }

    public function test_empty_result_shows_a_message(): void
    {
        $this->get(route('admin.restaurants.index', ['q' => 'nothing-matches-this']))
            ->assertOk()->assertSee('No restaurants match your filters.');
    }

    public function test_list_does_not_run_a_query_per_row(): void
    {
        Restaurant::factory()->count(10)->create();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->get(route('admin.restaurants.index'))->assertOk();

        // Count, page, restaurants, cuisines, cities (filter dropdown) plus framework extras: far below 10 rows' worth
        $this->assertLessThan(12, $queries);
    }

    public function test_a_search_word_that_is_not_text_is_ignored_instead_of_crashing(): void
    {
        // ?q[]=x sends a list instead of text; this used to cause a 500 error
        $this->get(route('admin.restaurants.index', ['q' => ['x']]))->assertOk();
    }
}
