<?php

namespace Tests\Feature\Admin;

use App\Models\City;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
