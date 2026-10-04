<?php

namespace Tests\Feature\Admin;

use App\Models\City;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CityCrudTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // pages render without needing compiled CSS
    }

    public function test_index_lists_cities_with_restaurant_counts(): void
    {
        $city = City::factory()->create(['name' => 'Paris']);
        Restaurant::factory()->count(2)->create(['city_id' => $city->id]);

        $this->get(route('admin.cities.index'))
            ->assertOk()
            ->assertSee('Paris')
            ->assertSee('Add city');
    }

    public function test_index_shows_empty_message_without_cities(): void
    {
        $this->get(route('admin.cities.index'))->assertOk()->assertSee('No cities yet');
    }

    public function test_create_form_loads(): void
    {
        $this->get(route('admin.cities.create'))->assertOk()->assertSee('City name');
    }

    public function test_store_creates_city_and_builds_slug_from_name(): void
    {
        $this->post(route('admin.cities.store'), ['name' => 'San Francisco'])
            ->assertRedirect(route('admin.cities.index'))
            ->assertSessionHas('status', 'City created.');

        $this->assertDatabaseHas('cities', ['name' => 'San Francisco', 'slug' => 'san-francisco']);
    }

    public function test_store_keeps_a_hand_typed_slug(): void
    {
        $this->post(route('admin.cities.store'), ['name' => 'Big Apple', 'slug' => 'nyc']);

        $this->assertDatabaseHas('cities', ['name' => 'Big Apple', 'slug' => 'nyc']);
    }

    public function test_store_rejects_missing_duplicate_and_badly_formed_input(): void
    {
        City::factory()->create(['name' => 'Rome', 'slug' => 'rome']);

        $this->post(route('admin.cities.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('admin.cities.store'), ['name' => 'Rome'])->assertSessionHasErrors('name');
        $this->post(route('admin.cities.store'), ['name' => 'Roma', 'slug' => 'rome'])->assertSessionHasErrors('slug');
        $this->post(route('admin.cities.store'), ['name' => 'Oslo', 'slug' => 'Bad Slug!'])->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('cities', 1);
    }

    public function test_edit_form_shows_the_current_values(): void
    {
        $city = City::factory()->create(['name' => 'Lisbon', 'slug' => 'lisbon']);

        $this->get(route('admin.cities.edit', $city))
            ->assertOk()
            ->assertSee('value="Lisbon"', false)
            ->assertSee('value="lisbon"', false);
    }

    public function test_update_can_keep_its_own_name_and_slug(): void
    {
        $city = City::factory()->create(['name' => 'Madrid', 'slug' => 'madrid']);

        // Same name and slug as before must not count as a duplicate of itself
        $this->put(route('admin.cities.update', $city), ['name' => 'Madrid', 'slug' => 'madrid'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.cities.index'));
    }

    public function test_update_changes_the_name(): void
    {
        $city = City::factory()->create(['name' => 'Bombay', 'slug' => 'bombay']);

        $this->put(route('admin.cities.update', $city), ['name' => 'Mumbai', 'slug' => 'mumbai'])
            ->assertSessionHas('status', 'City updated.');

        $this->assertDatabaseHas('cities', ['id' => $city->id, 'name' => 'Mumbai', 'slug' => 'mumbai']);
    }

    public function test_update_cannot_take_another_citys_slug(): void
    {
        City::factory()->create(['name' => 'Berlin', 'slug' => 'berlin']);
        $other = City::factory()->create(['name' => 'Bonn', 'slug' => 'bonn']);

        $this->put(route('admin.cities.update', $other), ['name' => 'Bonn', 'slug' => 'berlin'])
            ->assertSessionHasErrors('slug');
    }

    public function test_destroy_deletes_a_city_without_restaurants(): void
    {
        $city = City::factory()->create();

        $this->delete(route('admin.cities.destroy', $city))
            ->assertRedirect(route('admin.cities.index'))
            ->assertSessionHas('status', 'City deleted.');

        $this->assertModelMissing($city);
    }

    public function test_destroy_is_blocked_while_the_city_has_restaurants(): void
    {
        $city = City::factory()->create(['name' => 'Vienna']);
        Restaurant::factory()->count(2)->create(['city_id' => $city->id]);

        $this->from(route('admin.cities.index'))
            ->delete(route('admin.cities.destroy', $city))
            ->assertRedirect(route('admin.cities.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($city);
        $this->assertDatabaseCount('restaurants', 2);
    }

    // ---------- Description ----------

    public function test_description_is_optional_trimmed_and_saved(): void
    {
        $this->post(route('admin.cities.store'), ['name' => 'Oslo', 'description' => '  A fjord city.  '])->assertSessionHasNoErrors();
        $this->post(route('admin.cities.store'), ['name' => 'Bergen', 'description' => '   '])->assertSessionHasNoErrors();
        $this->post(route('admin.cities.store'), ['name' => 'Tromso'])->assertSessionHasNoErrors();

        $this->assertSame('A fjord city.', City::where('name', 'Oslo')->value('description'));
        $this->assertNull(City::where('name', 'Bergen')->value('description')); // blank means none
        $this->assertNull(City::where('name', 'Tromso')->value('description'));
    }

    public function test_description_has_a_length_limit_and_can_be_edited_and_cleared(): void
    {
        $this->post(route('admin.cities.store'), ['name' => 'Oslo', 'description' => str_repeat('a', 1001)])->assertSessionHasErrors('description');
        $this->assertDatabaseCount('cities', 0);

        $city = City::factory()->create(['name' => 'Oslo', 'description' => 'Old text']);
        $this->put(route('admin.cities.update', $city), ['name' => 'Oslo', 'description' => 'New text'])->assertRedirect();
        $this->assertSame('New text', $city->fresh()->description);

        $this->put(route('admin.cities.update', $city), ['name' => 'Oslo', 'description' => ''])->assertRedirect();
        $this->assertNull($city->fresh()->description);
    }

    public function test_forms_show_the_description_box_with_the_saved_text(): void
    {
        $this->get(route('admin.cities.create'))->assertOk()->assertSee('Description (optional)');

        $city = City::factory()->create(['description' => 'A fjord city.']);
        $this->get(route('admin.cities.edit', $city))->assertOk()->assertSee('A fjord city.');
    }

    public function test_description_is_escaped_in_the_list_and_the_form(): void
    {
        $city = City::factory()->create(['description' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $this->get(route('admin.cities.index'))->getContent());
        $this->assertStringNotContainsString('<script>alert(1)</script>', $this->get(route('admin.cities.edit', $city))->getContent());
    }

    public function test_cuisine_and_amenity_forms_do_not_get_a_description(): void
    {
        $this->get(route('admin.cuisines.create'))->assertOk()->assertDontSee('Description (optional)');
        $this->get(route('admin.amenities.create'))->assertOk()->assertDontSee('Description (optional)');
    }

    // ---------- Search and pagination ----------

    public function test_search_finds_by_name_or_slug_ignoring_case(): void
    {
        City::factory()->create(['name' => 'New York', 'slug' => 'new-york']);
        City::factory()->create(['name' => 'Big Apple', 'slug' => 'nyc']);
        City::factory()->create(['name' => 'London']);

        $this->get(route('admin.cities.index', ['q' => 'YORK']))->assertOk()
            ->assertSee('New York')->assertDontSee('London')->assertDontSee('Big Apple');

        $this->get(route('admin.cities.index', ['q' => 'nyc']))->assertSee('Big Apple')->assertDontSee('London');
        $this->get(route('admin.cities.index', ['q' => 'zzz']))->assertOk()->assertSee('No cities yet');
    }

    public function test_search_treats_percent_and_underscore_as_plain_characters(): void
    {
        City::factory()->create(['name' => 'Paris']);
        City::factory()->create(['name' => '100% Town']);

        $this->get(route('admin.cities.index', ['q' => '%']))->assertSee('100% Town')->assertDontSee('Paris');
        $this->get(route('admin.cities.index', ['q' => '_']))->assertDontSee('Paris');
    }

    public function test_search_shows_the_count_and_a_clear_button_and_ignores_junk(): void
    {
        City::factory()->create(['name' => 'Paris']);

        $html = $this->get(route('admin.cities.index', ['q' => 'par']))->getContent();
        $this->assertStringContainsString('1 city matching', preg_replace('/\s+/', ' ', strip_tags($html)));
        $this->assertStringContainsString('Clear', $html);

        $this->get(route('admin.cities.index', ['q' => ['x']]))->assertOk(); // an array instead of text is ignored, not an error
        $this->assertStringNotContainsString('Clear', $this->get(route('admin.cities.index'))->getContent());
    }

    public function test_list_is_paged_alphabetically_and_page_links_keep_the_search(): void
    {
        foreach (range(1, 17) as $n) {
            City::factory()->create(['name' => sprintf('Town %02d', $n)]);
        }
        City::factory()->create(['name' => 'Other']);

        $page1 = $this->get(route('admin.cities.index'))->getContent();
        $this->assertStringContainsString('Other', $page1);
        $this->assertStringContainsString('Town 14', $page1);
        $this->assertStringNotContainsString('Town 15', $page1);   // 15 per page
        $this->assertStringContainsString('Showing 1 to 15 of 18', preg_replace('/\s+/', ' ', strip_tags($page1)));

        $this->assertStringContainsString('Town 17', $this->get(route('admin.cities.index', ['page' => 2]))->getContent());

        $searched = $this->get(route('admin.cities.index', ['q' => 'town']))->getContent();
        $this->assertStringContainsString('q=town', $searched);
        $this->assertStringContainsString('page=2', $searched);
    }

    public function test_deleting_the_last_city_on_the_last_page_steps_back(): void
    {
        foreach (range(1, 16) as $n) {
            City::factory()->create(['name' => sprintf('Town %02d', $n)]);
        }
        City::where('name', 'Town 16')->delete();

        $this->get(route('admin.cities.index', ['page' => 2]))->assertRedirect(route('admin.cities.index', ['page' => 1]));
    }

    public function test_the_list_runs_a_fixed_number_of_queries(): void
    {
        $count = function () {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get(route('admin.cities.index'))->assertOk();

            return $queries;
        };

        City::factory()->count(2)->create();
        $few = $count();
        City::factory()->count(10)->create();

        $this->assertSame($few, $count());
    }
}
