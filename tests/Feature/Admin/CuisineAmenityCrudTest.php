<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\Cuisine;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Cuisines and amenities behave identically, so every test runs once for each
class CuisineAmenityCrudTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // pages render without needing compiled CSS
    }

    /** @return array<string, array{string, class-string, string, string, string}> */
    public static function resources(): array
    {
        // route prefix, model, table, noun (as shown to the admin), relation on Restaurant
        return [
            'cuisines' => ['admin.cuisines', Cuisine::class, 'cuisines', 'cuisine', 'cuisines'],
            'amenities' => ['admin.amenities', Amenity::class, 'amenities', 'amenity', 'amenities'],
        ];
    }

    #[DataProvider('resources')]
    public function test_index_lists_items_and_has_an_add_button(string $prefix, string $model, string $table, string $noun): void
    {
        $model::factory()->create(['name' => 'Sample Item']);

        $this->get(route("{$prefix}.index"))
            ->assertOk()
            ->assertSee('Sample Item')
            ->assertSee("Add {$noun}");
    }

    #[DataProvider('resources')]
    public function test_index_shows_empty_message(string $prefix, string $model, string $table, string $noun): void
    {
        $this->get(route("{$prefix}.index"))->assertOk()->assertSee('yet. Click');
    }

    #[DataProvider('resources')]
    public function test_create_form_loads(string $prefix, string $model, string $table, string $noun): void
    {
        $this->get(route("{$prefix}.create"))->assertOk()->assertSee(ucfirst($noun).' name');
    }

    #[DataProvider('resources')]
    public function test_store_creates_item_and_builds_slug(string $prefix, string $model, string $table, string $noun): void
    {
        $this->post(route("{$prefix}.store"), ['name' => 'Free Parking'])
            ->assertRedirect(route("{$prefix}.index"))
            ->assertSessionHas('status', ucfirst($noun).' created.');

        $this->assertDatabaseHas($table, ['name' => 'Free Parking', 'slug' => 'free-parking']);
    }

    #[DataProvider('resources')]
    public function test_store_rejects_missing_duplicate_and_badly_formed_input(string $prefix, string $model, string $table, string $noun): void
    {
        $model::factory()->create(['name' => 'Taken', 'slug' => 'taken']);

        $this->post(route("{$prefix}.store"), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route("{$prefix}.store"), ['name' => 'Taken'])->assertSessionHasErrors('name');
        $this->post(route("{$prefix}.store"), ['name' => 'Other', 'slug' => 'taken'])->assertSessionHasErrors('slug');
        $this->post(route("{$prefix}.store"), ['name' => 'Other', 'slug' => 'Bad Slug!'])->assertSessionHasErrors('slug');

        $this->assertDatabaseCount($table, 1);
    }

    #[DataProvider('resources')]
    public function test_edit_form_shows_current_values(string $prefix, string $model, string $table, string $noun): void
    {
        $item = $model::factory()->create(['name' => 'Garden', 'slug' => 'garden']);

        $this->get(route("{$prefix}.edit", $item))
            ->assertOk()
            ->assertSee('value="Garden"', false)
            ->assertSee('value="garden"', false);
    }

    #[DataProvider('resources')]
    public function test_update_can_keep_its_own_name_and_slug(string $prefix, string $model, string $table, string $noun): void
    {
        $item = $model::factory()->create(['name' => 'Keep Me', 'slug' => 'keep-me']);

        $this->put(route("{$prefix}.update", $item), ['name' => 'Keep Me', 'slug' => 'keep-me'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route("{$prefix}.index"));
    }

    #[DataProvider('resources')]
    public function test_update_changes_the_name_and_cannot_take_another_slug(string $prefix, string $model, string $table, string $noun): void
    {
        $model::factory()->create(['name' => 'First', 'slug' => 'first']);
        $item = $model::factory()->create(['name' => 'Second', 'slug' => 'second']);

        $this->put(route("{$prefix}.update", $item), ['name' => 'Second', 'slug' => 'first'])
            ->assertSessionHasErrors('slug');

        $this->put(route("{$prefix}.update", $item), ['name' => 'Renamed', 'slug' => 'renamed'])
            ->assertSessionHas('status', ucfirst($noun).' updated.');

        $this->assertDatabaseHas($table, ['id' => $item->id, 'name' => 'Renamed', 'slug' => 'renamed']);
    }

    #[DataProvider('resources')]
    public function test_destroy_deletes_an_unused_item(string $prefix, string $model, string $table, string $noun): void
    {
        $item = $model::factory()->create();

        $this->delete(route("{$prefix}.destroy", $item))
            ->assertRedirect(route("{$prefix}.index"))
            ->assertSessionHas('status', ucfirst($noun).' deleted.');

        $this->assertModelMissing($item);
    }

    #[DataProvider('resources')]
    public function test_destroy_works_when_restaurants_use_it_and_keeps_the_restaurants(string $prefix, string $model, string $table, string $noun, string $relation): void
    {
        $item = $model::factory()->create(['name' => 'Popular']);
        $restaurants = Restaurant::factory()->count(2)->create();
        foreach ($restaurants as $restaurant) {
            $restaurant->{$relation}()->attach($item);
        }

        // The confirmation popup tells the admin how many restaurants are affected
        $this->get(route("{$prefix}.index"))->assertSee('removed from 2 restaurant(s)', false);

        $this->delete(route("{$prefix}.destroy", $item))->assertSessionHas('status');

        $this->assertModelMissing($item);
        $this->assertDatabaseCount('restaurants', 2); // restaurants are kept
        $this->assertDatabaseCount("{$noun}_restaurant", 0); // only the links are gone
    }
}
