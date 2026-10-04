<?php

namespace Tests\Feature;

use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_featured_section_shows_published_featured_restaurants_only(): void
    {
        Restaurant::factory()->featured()->create(['name' => 'Star Place']);
        Restaurant::factory()->featured()->draft()->create(['name' => 'Hidden Star']);  // drafts are never public
        Restaurant::factory()->create(['name' => 'Plain Place']);                       // published but not featured

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Featured restaurants')
            ->assertSee('Star Place')
            ->assertDontSee('Hidden Star')
            ->assertDontSee('Plain Place');
    }

    public function test_featured_section_shows_at_most_six_in_name_order(): void
    {
        foreach (range(1, 8) as $n) {
            Restaurant::factory()->featured()->create(['name' => sprintf('Spot %02d', $n)]);
        }

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Spot 06', $html);
        $this->assertStringNotContainsString('Spot 07', $html);
        $this->assertSame(6, substr_count($html, '<article'));
    }

    public function test_section_is_hidden_when_nothing_is_featured(): void
    {
        Restaurant::factory()->create();

        $this->get(route('home'))->assertOk()->assertDontSee('Featured restaurants');
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

    public function test_home_page_query_count_does_not_grow_with_the_number_of_cards(): void
    {
        $countQueries = function (): int {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get(route('home'))->assertOk();

            return $queries;
        };

        Restaurant::factory()->featured()->create();
        $withOne = $countQueries();

        Restaurant::factory()->featured()->count(5)->create()->each(
            fn ($r) => $r->cuisines()->attach(Cuisine::factory()->count(2)->create())
        );
        $withSix = $countQueries();

        $this->assertSame($withOne, $withSix, 'Six cards must cost the same number of queries as one');
    }
}
