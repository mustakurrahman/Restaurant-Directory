<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\Cuisine;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Cuisines and amenities behave identically (they share Admin\NameSlugController with cities), so every test runs
// once for each. Cities have their own file because of their description and because restaurants belong to a city.
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

    // ---------- The basics ----------

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
    public function test_unknown_ids_are_404(string $prefix): void
    {
        $this->get(route("{$prefix}.edit", 9999))->assertNotFound();
        $this->put(route("{$prefix}.update", 9999), ['name' => 'x'])->assertNotFound();
        $this->delete(route("{$prefix}.destroy", 9999))->assertNotFound();
    }

    // ---------- Deleting ----------

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
    public function test_destroy_is_blocked_with_a_friendly_message_while_restaurants_use_it(string $prefix, string $model, string $table, string $noun, string $relation): void
    {
        $item = $model::factory()->create(['name' => 'Popular']);
        foreach (['Zeta Grill', 'Alpha Bistro'] as $name) {
            Restaurant::factory()->create(['name' => $name])->{$relation}()->attach($item);
        }

        $this->from(route("{$prefix}.index"))->delete(route("{$prefix}.destroy", $item))
            ->assertRedirect(route("{$prefix}.index"))
            ->assertSessionMissing('status');

        $message = session('error');
        $this->assertStringContainsString('“Popular” can’t be deleted because 2 restaurants still use it', $message);
        $this->assertStringContainsString('(Alpha Bistro, Zeta Grill)', $message);                // some of them are named, A-Z
        $this->assertStringContainsString('Untick it on those restaurants first', $message);      // and the way out is explained

        $this->assertModelExists($item);
        $this->assertDatabaseCount('restaurants', 2);
        $this->assertDatabaseCount("{$noun}_restaurant", 2);                                      // nothing was unlinked

        // the admin sees the message on the page
        $this->followingRedirects()->from(route("{$prefix}.index"))->delete(route("{$prefix}.destroy", $item))
            ->assertSee('can’t be deleted because 2 restaurants still use it');
    }

    #[DataProvider('resources')]
    public function test_the_message_uses_the_singular_and_shortens_long_lists(string $prefix, string $model, string $table, string $noun, string $relation): void
    {
        $one = $model::factory()->create(['name' => 'Lonely']);
        Restaurant::factory()->create(['name' => 'Only One'])->{$relation}()->attach($one);
        $this->delete(route("{$prefix}.destroy", $one));
        $this->assertStringContainsString('because 1 restaurant still uses it (Only One)', session('error'));

        $many = $model::factory()->create(['name' => 'Everywhere']);
        foreach (range(1, 5) as $n) {
            Restaurant::factory()->create(['name' => "Place {$n}"])->{$relation}()->attach($many);
        }
        $this->delete(route("{$prefix}.destroy", $many));
        $this->assertStringContainsString('because 5 restaurants still use it (Place 1, Place 2, Place 3 and 2 more)', session('error'));
    }

    #[DataProvider('resources')]
    public function test_draft_restaurants_also_block_the_delete(string $prefix, string $model, string $table, string $noun, string $relation): void
    {
        $item = $model::factory()->create();
        Restaurant::factory()->create(['status' => 'draft'])->{$relation}()->attach($item);

        $this->delete(route("{$prefix}.destroy", $item))->assertSessionHas('error');

        $this->assertModelExists($item);
    }

    #[DataProvider('resources')]
    public function test_once_no_restaurant_uses_it_any_more_it_can_be_deleted(string $prefix, string $model, string $table, string $noun, string $relation): void
    {
        $item = $model::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $restaurant->{$relation}()->attach($item);

        $this->delete(route("{$prefix}.destroy", $item))->assertSessionHas('error');

        $restaurant->{$relation}()->detach($item); // "untick it on the restaurant"
        $this->delete(route("{$prefix}.destroy", $item))->assertSessionHas('status', ucfirst($noun).' deleted.');

        $this->assertModelMissing($item);
        $this->assertModelExists($restaurant);
    }

    #[DataProvider('resources')]
    public function test_the_message_is_escaped(string $prefix, string $model, string $table, string $noun, string $relation): void
    {
        $item = $model::factory()->create(['name' => '<b>Bold</b>']);
        Restaurant::factory()->create(['name' => '<script>alert(1)</script>'])->{$relation}()->attach($item);

        $html = $this->followingRedirects()->from(route("{$prefix}.index"))->delete(route("{$prefix}.destroy", $item))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>Bold</b>', $html);
    }

    // ---------- Search and pagination (same as cities) ----------

    #[DataProvider('resources')]
    public function test_search_finds_by_name_or_slug_ignoring_case(string $prefix, string $model): void
    {
        $model::factory()->create(['name' => 'Wood Fired', 'slug' => 'pizza-oven']);
        $model::factory()->create(['name' => 'Rooftop']);

        $this->get(route("{$prefix}.index", ['q' => 'WOOD']))->assertOk()->assertSee('Wood Fired')->assertDontSee('Rooftop');
        $this->get(route("{$prefix}.index", ['q' => 'pizza-oven']))->assertSee('Wood Fired')->assertDontSee('Rooftop');
        $this->get(route("{$prefix}.index", ['q' => 'zzz']))->assertOk()->assertSee('yet. Click');
    }

    #[DataProvider('resources')]
    public function test_search_treats_percent_as_a_plain_character_and_ignores_junk(string $prefix, string $model): void
    {
        $model::factory()->create(['name' => '100% Good']);
        $model::factory()->create(['name' => 'Plain']);

        $this->get(route("{$prefix}.index", ['q' => '%']))->assertSee('100% Good')->assertDontSee('Plain');
        $this->get(route("{$prefix}.index", ['q' => ['x']]))->assertOk();
    }

    #[DataProvider('resources')]
    public function test_the_list_is_paged_alphabetically_and_page_links_keep_the_search(string $prefix, string $model): void
    {
        foreach (range(1, 17) as $n) {
            $model::factory()->create(['name' => sprintf('Item %02d', $n)]);
        }

        $page1 = $this->get(route("{$prefix}.index"))->getContent();
        $this->assertStringContainsString('Item 15', $page1);
        $this->assertStringNotContainsString('Item 16', $page1);                       // 15 per page
        $this->assertStringContainsString('Item 17', $this->get(route("{$prefix}.index", ['page' => 2]))->getContent());

        $searched = $this->get(route("{$prefix}.index", ['q' => 'item']))->getContent();
        $this->assertStringContainsString('q=item', $searched);
        $this->assertStringContainsString('page=2', $searched);
    }

    #[DataProvider('resources')]
    public function test_deleting_the_last_item_on_the_last_page_steps_back(string $prefix, string $model): void
    {
        foreach (range(1, 16) as $n) {
            $model::factory()->create(['name' => sprintf('Item %02d', $n)]);
        }
        $model::where('name', 'Item 16')->delete();

        $this->get(route("{$prefix}.index", ['page' => 2]))->assertRedirect(route("{$prefix}.index", ['page' => 1]));
    }

    #[DataProvider('resources')]
    public function test_the_list_runs_a_fixed_number_of_queries(string $prefix, string $model): void
    {
        $count = function () use ($prefix) {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get(route("{$prefix}.index"))->assertOk();

            return $queries;
        };

        $model::factory()->count(2)->create();
        $few = $count();
        $model::factory()->count(10)->create();

        $this->assertSame($few, $count());
    }

    // ---------- The optional icon (amenities only) ----------

    public function test_an_amenity_icon_is_optional_trimmed_lowercased_and_saved(): void
    {
        $this->post(route('admin.amenities.store'), ['name' => 'Free Wi-Fi', 'icon' => '  WiFi  '])->assertSessionHasNoErrors();
        $this->post(route('admin.amenities.store'), ['name' => 'Parking', 'icon' => '   '])->assertSessionHasNoErrors();
        $this->post(route('admin.amenities.store'), ['name' => 'Terrace'])->assertSessionHasNoErrors();

        $this->assertSame('wifi', Amenity::where('name', 'Free Wi-Fi')->value('icon'));
        $this->assertNull(Amenity::where('name', 'Parking')->value('icon'));   // blank means none
        $this->assertNull(Amenity::where('name', 'Terrace')->value('icon'));
    }

    public function test_an_amenity_icon_must_be_a_simple_lowercase_name(): void
    {
        foreach (['has space', 'under_score', '<script>', '-leading', 'trailing-', 'double--hyphen', str_repeat('a', 51)] as $bad) {
            $this->post(route('admin.amenities.store'), ['name' => 'Test', 'icon' => $bad])->assertSessionHasErrors('icon');
        }
        $this->assertDatabaseCount('amenities', 0);

        $this->post(route('admin.amenities.store'), ['name' => 'Outdoor', 'icon' => 'outdoor-seating'])->assertSessionHasNoErrors();
    }

    public function test_an_amenity_icon_can_be_edited_and_cleared_and_is_shown(): void
    {
        $amenity = Amenity::factory()->create(['name' => 'Bar', 'icon' => 'cocktail']);

        $this->get(route('admin.amenities.index'))->assertSee('cocktail');
        $this->get(route('admin.amenities.edit', $amenity))->assertSee('value="cocktail"', false)->assertSee('Icon name (optional)');

        $this->put(route('admin.amenities.update', $amenity), ['name' => 'Bar', 'icon' => 'wine'])->assertRedirect();
        $this->assertSame('wine', $amenity->fresh()->icon);

        $this->put(route('admin.amenities.update', $amenity), ['name' => 'Bar', 'icon' => ''])->assertRedirect();
        $this->assertNull($amenity->fresh()->icon);
    }

    public function test_only_amenities_get_the_icon_box_and_only_cities_the_description_box(): void
    {
        $this->get(route('admin.amenities.create'))->assertSee('Icon name (optional)')->assertDontSee('Description (optional)');
        $this->get(route('admin.cuisines.create'))->assertDontSee('Icon name (optional)')->assertDontSee('Description (optional)');
        $this->get(route('admin.cities.create'))->assertSee('Description (optional)')->assertDontSee('Icon name (optional)');
    }

    public function test_a_cuisine_ignores_an_icon_sent_by_hand(): void
    {
        $this->post(route('admin.cuisines.store'), ['name' => 'Thai', 'icon' => 'hacked'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cuisines', ['name' => 'Thai']); // saved, without the unknown field (cuisines have no icon)
    }
}
