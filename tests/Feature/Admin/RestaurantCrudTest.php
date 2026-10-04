<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Cuisine;
use App\Models\OpeningHour;
use App\Models\Restaurant;
use App\Models\RestaurantImage;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantCrudTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // pages render without needing compiled CSS
        $this->city = City::factory()->create(['name' => 'Paris']);
    }

    /** The smallest set of fields that passes validation */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Chez Test',
            'address' => '1 Rue Example',
            'city_id' => $this->city->id,
            'price_range' => 3,
            'status' => 'published',
        ], $overrides);
    }

    // ---------- Add ----------

    public function test_create_form_lists_cities_cuisines_and_amenities(): void
    {
        Cuisine::factory()->create(['name' => 'French']);
        Amenity::factory()->create(['name' => 'Free Wi-Fi']);

        $this->get(route('admin.restaurants.create'))
            ->assertOk()
            ->assertSee('Add restaurant')
            ->assertSee('Paris')
            ->assertSee('French')
            ->assertSee('Free Wi-Fi')
            ->assertSee('Draft (hidden from visitors)');
    }

    public function test_store_with_only_required_fields_builds_slug_and_uses_defaults(): void
    {
        $this->post(route('admin.restaurants.store'), $this->validData())
            ->assertRedirect(route('admin.restaurants.index'))
            ->assertSessionHas('status', 'Restaurant created.');

        $this->assertDatabaseHas('restaurants', [
            'name' => 'Chez Test',
            'slug' => 'chez-test',
            'city_id' => $this->city->id,
            'price_range' => 3,
            'status' => 'published',
            'is_featured' => false,
        ]);
    }

    public function test_store_saves_every_field_and_the_category_links(): void
    {
        $french = Cuisine::factory()->create();
        $bistro = Cuisine::factory()->create();
        $wifi = Amenity::factory()->create();

        $this->post(route('admin.restaurants.store'), $this->validData([
            'slug' => 'chez-custom',
            'description' => "First paragraph.\n\nSecond paragraph.",
            'phone' => '+33 (1) 23-45-67',
            'email' => 'hello@example.com',
            'website' => 'https://example.com',
            'latitude' => '48.8566',
            'longitude' => '2.3522',
            'is_featured' => '1',
            'meta_title' => 'Chez Test | Paris',
            'meta_description' => 'A fine bistro.',
            'cuisines' => [$french->id, $bistro->id],
            'amenities' => [$wifi->id],
        ]))->assertSessionHasNoErrors();

        $restaurant = Restaurant::where('slug', 'chez-custom')->firstOrFail();
        $this->assertSame('hello@example.com', $restaurant->email);
        $this->assertSame('https://example.com', $restaurant->website);
        $this->assertEquals(48.8566, $restaurant->latitude);
        $this->assertEquals(2.3522, $restaurant->longitude);
        $this->assertTrue($restaurant->is_featured);
        $this->assertSame('Chez Test | Paris', $restaurant->meta_title);
        $this->assertEqualsCanonicalizing([$french->id, $bistro->id], $restaurant->cuisines->pluck('id')->all());
        $this->assertSame([$wifi->id], $restaurant->amenities->pluck('id')->all());
    }

    public function test_store_requires_name_address_city_price_and_status(): void
    {
        $this->post(route('admin.restaurants.store'), [])
            ->assertSessionHasErrors(['name', 'address', 'city_id', 'price_range', 'status']);

        $this->assertDatabaseCount('restaurants', 0);
    }

    public function test_store_rejects_invalid_values(): void
    {
        $bad = [
            'email' => ['email' => 'not-an-email'],
            'website' => ['website' => 'ftp://example.com'],
            'website without scheme' => ['website' => 'example.com'],
            'phone' => ['phone' => 'call me'],
            'price too high' => ['price_range' => 5],
            'price too low' => ['price_range' => 0],
            'status' => ['status' => 'archived'],
            'city' => ['city_id' => 9999],
            'slug case' => ['slug' => 'Bad Slug!'],
            'latitude range' => ['latitude' => '91', 'longitude' => '0'],
            'longitude range' => ['latitude' => '0', 'longitude' => '181'],
            'latitude not a number' => ['latitude' => 'north', 'longitude' => '2'],
            'cuisine id' => ['cuisines' => [9999]],
            'amenity id' => ['amenities' => [9999]],
        ];

        $expectedField = [
            'email' => 'email', 'website' => 'website', 'website without scheme' => 'website', 'phone' => 'phone',
            'price too high' => 'price_range', 'price too low' => 'price_range', 'status' => 'status',
            'city' => 'city_id', 'slug case' => 'slug', 'latitude range' => 'latitude', 'longitude range' => 'longitude',
            'latitude not a number' => 'latitude', 'cuisine id' => 'cuisines.0', 'amenity id' => 'amenities.0',
        ];

        foreach ($bad as $case => $override) {
            $this->post(route('admin.restaurants.store'), $this->validData($override))
                ->assertSessionHasErrors($expectedField[$case]);
        }

        $this->assertDatabaseCount('restaurants', 0);
    }

    public function test_latitude_and_longitude_must_be_given_together(): void
    {
        $this->post(route('admin.restaurants.store'), $this->validData(['latitude' => '10']))
            ->assertSessionHasErrors('longitude');

        $this->post(route('admin.restaurants.store'), $this->validData(['longitude' => '10']))
            ->assertSessionHasErrors('latitude');

        $this->post(route('admin.restaurants.store'), $this->validData(['latitude' => '10', 'longitude' => '10']))
            ->assertSessionHasNoErrors();
    }

    public function test_slug_must_be_unique_but_names_may_repeat(): void
    {
        $this->post(route('admin.restaurants.store'), $this->validData(['slug' => 'taken']))->assertSessionHasNoErrors();

        $this->post(route('admin.restaurants.store'), $this->validData(['name' => 'Other', 'slug' => 'taken']))
            ->assertSessionHasErrors('slug');

        // Same name, no slug typed: the model makes chez-test-2
        $this->post(route('admin.restaurants.store'), $this->validData())->assertSessionHasNoErrors();
        $this->post(route('admin.restaurants.store'), $this->validData())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restaurants', ['slug' => 'chez-test']);
        $this->assertDatabaseHas('restaurants', ['slug' => 'chez-test-2']);
    }

    public function test_form_keeps_what_was_typed_and_ticked_after_an_error(): void
    {
        $cuisine = Cuisine::factory()->create();
        $other = Cuisine::factory()->create();

        // followingRedirects() behaves like a browser: submit, get sent back to the form, see the page
        $response = $this->from(route('admin.restaurants.create'))
            ->followingRedirects()
            ->post(route('admin.restaurants.store'), $this->validData([
                'name' => 'Keep Me',
                'email' => 'broken',
                'cuisines' => [$cuisine->id],
            ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Add restaurant', $html); // we are back on the form
        $this->assertStringContainsString('value="Keep Me"', $html);
        $this->assertStringContainsString('The email field must be a valid email address.', $html);
        $this->assertMatchesRegularExpression('/name="cuisines\[\]" value="'.$cuisine->id.'"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="cuisines\[\]" value="'.$other->id.'"\s+checked/', $html);
    }

    // ---------- Edit ----------

    public function test_edit_form_shows_saved_values_and_ticked_categories(): void
    {
        $restaurant = Restaurant::factory()->featured()->create([
            'name' => 'Le Saved', 'city_id' => $this->city->id, 'latitude' => 48.8566, 'longitude' => 2.3522,
        ]);
        $ticked = Cuisine::factory()->create();
        $unticked = Cuisine::factory()->create();
        $restaurant->cuisines()->attach($ticked);

        $html = $this->get(route('admin.restaurants.edit', $restaurant))->assertOk()->getContent();

        $this->assertStringContainsString('value="Le Saved"', $html);
        $this->assertStringContainsString('value="48.8566"', $html); // not 48.8566000
        $this->assertMatchesRegularExpression('/name="cuisines\[\]" value="'.$ticked->id.'"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="cuisines\[\]" value="'.$unticked->id.'"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/name="is_featured" value="1"\s+checked/', $html);
    }

    public function test_update_changes_fields_and_syncs_categories(): void
    {
        $restaurant = Restaurant::factory()->featured()->create(['city_id' => $this->city->id]);
        [$keep, $drop, $add] = Cuisine::factory()->count(3)->create()->all();
        $restaurant->cuisines()->attach([$keep->id, $drop->id]);
        $restaurant->amenities()->attach(Amenity::factory()->create());

        // Cuisines: keep one, drop one, add one. Amenities: none ticked. Featured: unticked (hidden 0).
        $this->put(route('admin.restaurants.update', $restaurant), $this->validData([
            'name' => 'Renamed',
            'slug' => $restaurant->slug,
            'status' => 'draft',
            'is_featured' => '0',
            'cuisines' => [$keep->id, $add->id],
        ]))->assertRedirect(route('admin.restaurants.index'))
            ->assertSessionHas('status', 'Restaurant updated.');

        $restaurant->refresh();
        $this->assertSame('Renamed', $restaurant->name);
        $this->assertSame('draft', $restaurant->status);
        $this->assertFalse($restaurant->is_featured);
        $this->assertEqualsCanonicalizing([$keep->id, $add->id], $restaurant->cuisines->pluck('id')->all());
        $this->assertCount(0, $restaurant->amenities);
    }

    public function test_update_can_keep_its_own_slug_but_not_take_anothers(): void
    {
        Restaurant::factory()->create(['slug' => 'first']);
        $second = Restaurant::factory()->create(['slug' => 'second', 'city_id' => $this->city->id]);

        $this->put(route('admin.restaurants.update', $second), $this->validData(['slug' => 'second']))
            ->assertSessionHasNoErrors();

        $this->put(route('admin.restaurants.update', $second), $this->validData(['slug' => 'first']))
            ->assertSessionHasErrors('slug');
    }

    public function test_update_with_invalid_data_changes_nothing(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Original', 'city_id' => $this->city->id]);
        $cuisine = Cuisine::factory()->create();
        $restaurant->cuisines()->attach($cuisine);

        $this->put(route('admin.restaurants.update', $restaurant), $this->validData(['name' => 'Changed', 'email' => 'bad']))
            ->assertSessionHasErrors('email');

        $this->assertSame('Original', $restaurant->fresh()->name);
        $this->assertCount(1, $restaurant->fresh()->cuisines);
    }

    // ---------- Delete ----------

    public function test_destroy_removes_the_restaurant_and_everything_attached_to_it(): void
    {
        $restaurant = Restaurant::factory()->create();
        $restaurant->cuisines()->attach(Cuisine::factory()->create());
        $restaurant->amenities()->attach(Amenity::factory()->create());
        RestaurantImage::factory()->create(['restaurant_id' => $restaurant->id]);
        OpeningHour::factory()->create(['restaurant_id' => $restaurant->id, 'day_of_week' => 1]);
        Review::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->delete(route('admin.restaurants.destroy', $restaurant))
            ->assertRedirect(route('admin.restaurants.index'))
            ->assertSessionHas('status', 'Restaurant deleted.');

        $this->assertModelMissing($restaurant);
        foreach (['restaurant_images', 'opening_hours', 'reviews', 'cuisine_restaurant', 'amenity_restaurant'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        // The categories themselves stay
        $this->assertDatabaseCount('cuisines', 1);
        $this->assertDatabaseCount('amenities', 1);
    }

    // ---------- List page buttons ----------

    public function test_list_has_add_edit_and_delete_buttons(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Button Test']);

        $this->get(route('admin.restaurants.index'))
            ->assertOk()
            ->assertSee('Add restaurant')
            ->assertSee(route('admin.restaurants.create'), false)
            ->assertSee(route('admin.restaurants.edit', $restaurant), false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('Delete Button Test?', false);
    }
}
