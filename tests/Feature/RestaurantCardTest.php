<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantCardTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public'); // uploads go to a temporary disk that is wiped after each test
    }

    /** Loads a restaurant the way the pages do: city, cuisines and review stats in one go */
    private function loaded(Restaurant $restaurant): Restaurant
    {
        return Restaurant::withReviewStats()->with(['city:id,name', 'cuisines:id,name'])->findOrFail($restaurant->id);
    }

    private function card(Restaurant $restaurant): string
    {
        return Blade::render('<x-restaurant-card :restaurant="$restaurant" />', ['restaurant' => $restaurant]);
    }

    private function review(Restaurant $restaurant, int $rating, string $status): void
    {
        Review::factory()->create(['restaurant_id' => $restaurant->id, 'rating' => $rating, 'status' => $status]);
    }

    // ---------- What the card shows ----------

    public function test_card_shows_name_city_cuisines_and_price(): void
    {
        $restaurant = Restaurant::factory()->create([
            'name' => 'Chez Marie', 'price_range' => 3, 'city_id' => City::factory()->create(['name' => 'Paris'])->id,
        ]);
        $restaurant->cuisines()->attach([
            Cuisine::factory()->create(['name' => 'French'])->id,
            Cuisine::factory()->create(['name' => 'Seafood'])->id,
        ]);

        $html = $this->card($this->loaded($restaurant));

        $this->assertStringContainsString('Chez Marie', $html);
        $this->assertStringContainsString('Paris', $html);
        $this->assertStringContainsString('French · Seafood', $html);
        $this->assertStringContainsString('aria-label="Price range 3 of 4"', $html);
        $this->assertStringContainsString('<span class="text-gold-500">$$$</span><span class="text-cream/25">$</span>', $html);
    }

    public function test_card_shows_at_most_three_cuisines(): void
    {
        $restaurant = Restaurant::factory()->create();
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta'] as $name) {
            $restaurant->cuisines()->attach(Cuisine::factory()->create(['name' => $name]));
        }

        $html = $this->card($this->loaded($restaurant));

        $this->assertStringContainsString('Alpha · Bravo · Charlie', $html);
        $this->assertStringNotContainsString('Delta', $html);
    }

    public function test_featured_badge_only_appears_on_featured_restaurants(): void
    {
        $featured = Restaurant::factory()->featured()->create();
        $normal = Restaurant::factory()->create();

        $this->assertStringContainsString('Featured', $this->card($this->loaded($featured)));
        $this->assertStringNotContainsString('Featured', $this->card($this->loaded($normal)));
    }

    public function test_names_are_escaped(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => '<script>alert(1)</script> Grill']);

        $html = $this->card($this->loaded($restaurant));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; Grill', $html);
    }

    // ---------- Ratings ----------

    public function test_rating_counts_approved_reviews_only(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->review($restaurant, 5, 'approved');
        $this->review($restaurant, 4, 'approved');
        $this->review($restaurant, 1, 'pending');   // not public yet
        $this->review($restaurant, 1, 'rejected');  // never public

        $html = $this->card($this->loaded($restaurant));

        $this->assertStringContainsString('★ 4.5', $html);
        $this->assertStringContainsString('(2 reviews)', $html);
        $this->assertStringContainsString('Rated 4.5 out of 5', $html);
        $this->assertStringNotContainsString('No reviews yet', $html);
    }

    public function test_a_single_review_is_not_pluralised(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->review($restaurant, 3, 'approved');

        $this->assertStringContainsString('(1 review)', $this->card($this->loaded($restaurant)));
    }

    public function test_restaurant_without_approved_reviews_says_so(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->review($restaurant, 5, 'pending');

        $html = $this->card($this->loaded($restaurant));

        $this->assertStringContainsString('No reviews yet', $html);
        $this->assertStringNotContainsString('★', $html);
    }

    public function test_rating_line_is_left_out_when_stats_were_not_loaded(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->review($restaurant, 5, 'approved');

        // Loaded WITHOUT withReviewStats(): better to say nothing than to claim "No reviews yet"
        $html = $this->card(Restaurant::with(['city:id,name', 'cuisines:id,name'])->findOrFail($restaurant->id));

        $this->assertStringNotContainsString('No reviews yet', $html);
        $this->assertStringNotContainsString('★', $html);
    }

    // ---------- Pictures ----------

    public function test_restaurant_without_a_photo_gets_a_placeholder_with_empty_alt_text(): void
    {
        $restaurant = Restaurant::factory()->create(['cover_image' => null]);

        $html = $this->card($this->loaded($restaurant));

        $this->assertMatchesRegularExpression('#src="[^"]*/images/placeholders/restaurant-[1-4]\.svg"#', $html);
        $this->assertStringContainsString('alt=""', $html);
    }

    public function test_sample_data_paths_without_a_file_also_get_the_placeholder(): void
    {
        $restaurant = Restaurant::factory()->create(['cover_image' => 'placeholders/restaurant-3.jpg']); // no such file

        $this->assertStringContainsString('/images/placeholders/restaurant-', $this->card($this->loaded($restaurant)));
    }

    public function test_the_same_restaurant_always_gets_the_same_placeholder_and_neighbours_differ(): void
    {
        $urls = [];
        foreach (range(1, 5) as $id) {
            $restaurant = Restaurant::factory()->create(['id' => $id]);
            $urls[$id] = $restaurant->placeholder_url;
            $this->assertSame($urls[$id], Restaurant::find($id)->placeholder_url); // stable
        }

        $this->assertCount(4, array_unique(array_slice($urls, 0, 4))); // ids 1-4 use all four pictures
        $this->assertSame($urls[1], $urls[5]);                          // then it repeats
    }

    public function test_uploaded_cover_is_used_with_descriptive_alt_text(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Photo Place']);
        $path = "restaurants/{$restaurant->id}/cover.jpg";
        Storage::disk('public')->put($path, 'bytes');
        $restaurant->update(['cover_image' => $path]);

        $html = $this->card($this->loaded($restaurant));

        $this->assertStringContainsString('/storage/'.$path, $html);
        $this->assertStringContainsString('alt="Photo of Photo Place"', $html);
        $this->assertStringNotContainsString('/images/placeholders/', $html);
    }

    // ---------- Link ----------

    public function test_card_links_to_the_restaurant_page(): void
    {
        $restaurant = Restaurant::factory()->create(['slug' => 'linked-place']);

        $html = $this->card($this->loaded($restaurant));
        $this->assertStringContainsString('href="'.url('/restaurant/linked-place').'"', $html);
        $this->assertStringContainsString("after:absolute after:inset-0", $html); // whole card clickable
    }

    // ---------- Speed ----------

    public function test_rendering_many_cards_runs_no_extra_queries(): void
    {
        foreach (range(1, 10) as $i) {
            $restaurant = Restaurant::factory()->create();
            $restaurant->cuisines()->attach(Cuisine::factory()->count(2)->create());
            $this->review($restaurant, 5, 'approved');
        }

        // Load everything the way a page does, THEN count queries while drawing the cards
        $restaurants = Restaurant::withReviewStats()->with(['city:id,name', 'cuisines:id,name'])->get();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        foreach ($restaurants as $restaurant) {
            $this->card($restaurant);
        }

        $this->assertSame(10, $restaurants->count());
        $this->assertSame(0, $queries, 'Cards must not query the database themselves (N+1)');
    }
}
