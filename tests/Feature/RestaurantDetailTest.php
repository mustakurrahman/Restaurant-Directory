<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Cuisine;
use App\Models\OpeningHour;
use App\Models\Restaurant;
use App\Models\RestaurantImage;
use App\Models\Review;
use App\Support\OpeningHoursFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantDetailTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    private function make(array $attributes = []): Restaurant
    {
        return Restaurant::factory()->create($attributes + ['slug' => 'chez-marie', 'name' => 'Chez Marie']);
    }

    private function page(Restaurant $restaurant): string
    {
        return $this->get(route('restaurants.show', $restaurant))->assertOk()->getContent();
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)));
    }

    private function jsonLd(string $html, string $type): ?array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);

        return collect($blocks[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', $type);
    }

    private function review(Restaurant $restaurant, int $rating, string $status = 'approved', array $extra = []): Review
    {
        return Review::factory()->create($extra + ['restaurant_id' => $restaurant->id, 'rating' => $rating, 'status' => $status]);
    }

    // ---------- Access ----------

    public function test_a_published_restaurant_page_opens_at_its_slug(): void
    {
        $this->make();

        $this->get('/restaurant/chez-marie')->assertOk()->assertSee('Chez Marie');
    }

    public function test_drafts_and_unknown_slugs_are_404(): void
    {
        $this->make(['slug' => 'secret-draft', 'status' => 'draft']);

        $this->get('/restaurant/secret-draft')->assertNotFound();
        $this->get('/restaurant/no-such-place')->assertNotFound();
        $this->get('/restaurant/1')->assertNotFound(); // ids are not addresses, only slugs
    }

    // ---------- Content ----------

    public function test_shows_the_main_facts(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        $restaurant = $this->make([
            'city_id' => $city->id, 'price_range' => 3, 'is_featured' => true,
            'address' => '12 Wabash Ave', 'phone' => '+1 312 555 0100', 'email' => 'hello@chezmarie.test',
            'website' => 'https://www.chezmarie.test/', 'description' => "First paragraph.\n\nSecond paragraph.",
        ]);
        $restaurant->cuisines()->attach(Cuisine::factory()->create(['name' => 'French', 'slug' => 'french']));
        $restaurant->amenities()->attach(Amenity::factory()->create(['name' => 'Free Wi-Fi']));

        $html = $this->page($restaurant);
        $text = $this->text($html);

        $this->assertStringContainsString('<h1 class="text-4xl font-bold sm:text-5xl">Chez Marie</h1>', $html);
        $this->assertStringContainsString('Featured', $text);
        $this->assertStringContainsString('12 Wabash Ave, Chicago', $text);
        $this->assertStringContainsString('Free Wi-Fi', $text);
        $this->assertStringContainsString('First paragraph.', $html);
        $this->assertSame(2, substr_count($html, 'leading-relaxed text-cream/80'));
        $this->assertStringContainsString('href="tel:+13125550100"', $html);
        $this->assertStringContainsString('href="mailto:hello@chezmarie.test"', $html);
        $this->assertStringContainsString('href="https://www.chezmarie.test/" target="_blank" rel="nofollow noopener"', $html);
        $this->assertStringContainsString('href="'.url('/cuisine/french').'"', $html);
        $this->assertStringContainsString('href="'.url('/city/'.$city->slug).'"', $html);
    }

    public function test_description_text_cannot_inject_html(): void
    {
        $restaurant = $this->make(['description' => '<script>alert(1)</script> hello']);

        $html = $this->page($restaurant);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; hello', $html);
    }

    public function test_an_unsafe_website_address_is_not_made_into_a_link(): void
    {
        $restaurant = $this->make(['website' => 'javascript:alert(1)']);

        $this->assertStringNotContainsString('javascript:', $this->page($restaurant));
    }

    public function test_optional_parts_are_left_out_when_empty(): void
    {
        $restaurant = $this->make(['phone' => null, 'email' => null, 'website' => null, 'description' => null]);

        $text = $this->text($this->page($restaurant));

        $this->assertStringNotContainsString('Phone', $text);
        $this->assertStringNotContainsString('Email', $text);
        $this->assertStringNotContainsString('Website', $text);
        $this->assertStringNotContainsString('Amenities', $text);
        $this->assertStringNotContainsString('Opening hours', $text);
        $this->assertStringNotContainsString('Photos', $text);
        $this->assertStringContainsString('There is no description for this restaurant yet.', $text);
        $this->assertStringContainsString('No reviews yet', $text);
    }

    // ---------- Photos ----------

    public function test_cover_and_gallery_photos_are_shown_and_missing_files_skipped(): void
    {
        $restaurant = $this->make();
        Storage::disk('public')->put("restaurants/{$restaurant->id}/cover.jpg", 'x');
        Storage::disk('public')->put("restaurants/{$restaurant->id}/a.jpg", 'x');
        $restaurant->update(['cover_image' => "restaurants/{$restaurant->id}/cover.jpg"]);
        RestaurantImage::factory()->create(['restaurant_id' => $restaurant->id, 'path' => "restaurants/{$restaurant->id}/a.jpg", 'alt_text' => 'The dining room', 'sort_order' => 1]);
        RestaurantImage::factory()->create(['restaurant_id' => $restaurant->id, 'path' => 'restaurants/9/ghost.jpg', 'sort_order' => 2]); // file does not exist

        $html = $this->page($restaurant);

        $this->assertStringContainsString('alt="Photo of Chez Marie"', $html);
        $this->assertStringContainsString('alt="The dining room"', $html);
        $this->assertStringNotContainsString('ghost.jpg', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.asset("storage/restaurants/{$restaurant->id}/cover.jpg").'"', $html);
    }

    public function test_without_a_cover_a_decorative_placeholder_is_used(): void
    {
        $html = $this->page($this->make());

        $this->assertStringContainsString('/images/placeholders/restaurant-', $html);
        $this->assertStringContainsString('alt=""', $html);
    }

    // ---------- Opening hours ----------

    public function test_opening_hours_are_listed_monday_first_and_today_is_highlighted(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(12, 0)); // a Wednesday
        $restaurant = $this->make();
        OpeningHour::create(['restaurant_id' => $restaurant->id, 'day_of_week' => 1, 'opens_at' => '11:00', 'closes_at' => '22:30', 'is_closed' => false]);
        OpeningHour::create(['restaurant_id' => $restaurant->id, 'day_of_week' => 3, 'opens_at' => '09:00', 'closes_at' => '17:00', 'is_closed' => false]);
        OpeningHour::create(['restaurant_id' => $restaurant->id, 'day_of_week' => 7, 'opens_at' => null, 'closes_at' => null, 'is_closed' => true]);

        $html = $this->page($restaurant);
        $text = $this->text($html);

        $this->assertStringContainsString('Monday 11:00 AM – 10:30 PM', $text);
        $this->assertStringContainsString('Wednesday (today) 9:00 AM – 5:00 PM', $text);
        $this->assertStringContainsString('Tuesday Not listed', $text);
        $this->assertStringContainsString('Sunday Closed', $text);
        $this->assertTrue(strpos($text, 'Monday') < strpos($text, 'Sunday'));
        $this->assertSame(1, substr_count($html, '(today)'));
    }

    public function test_hours_formatter_handles_mysql_style_times_and_builds_the_google_format(): void
    {
        $restaurant = $this->make();
        OpeningHour::create(['restaurant_id' => $restaurant->id, 'day_of_week' => 2, 'opens_at' => '11:00:00', 'closes_at' => '23:15:00', 'is_closed' => false]);
        OpeningHour::create(['restaurant_id' => $restaurant->id, 'day_of_week' => 4, 'opens_at' => null, 'closes_at' => null, 'is_closed' => true]);
        $hours = $restaurant->openingHours()->get();

        $this->assertSame('11:00 AM – 11:15 PM', OpeningHoursFormatter::week($hours)[1]['text']);
        $this->assertSame([[
            '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Tuesday', 'opens' => '11:00', 'closes' => '23:15',
        ]], OpeningHoursFormatter::schema($hours)); // closed days are left out
    }

    // ---------- Reviews ----------

    public function test_only_approved_reviews_are_shown_and_counted_and_emails_stay_private(): void
    {
        $restaurant = $this->make();
        $this->review($restaurant, 5, 'approved', ['name' => 'Alice Public', 'comment' => 'Wonderful evening', 'email' => 'alice@secret.test']);
        $this->review($restaurant, 3, 'approved', ['name' => 'Bob Public', 'comment' => 'It was fine']);
        $this->review($restaurant, 1, 'pending', ['name' => 'Pending Pete', 'comment' => 'Awaiting moderation']);
        $this->review($restaurant, 1, 'rejected', ['name' => 'Rejected Rita', 'comment' => 'Spam spam spam']);

        $html = $this->page($restaurant);
        $text = $this->text($html);

        $this->assertStringContainsString('Alice Public', $text);
        $this->assertStringContainsString('Bob Public', $text);
        $this->assertStringNotContainsString('Pending Pete', $html);
        $this->assertStringNotContainsString('Rejected Rita', $html);
        $this->assertStringNotContainsString('Awaiting moderation', $html);
        $this->assertStringNotContainsString('alice@secret.test', $html);
        $this->assertStringContainsString('★ 4.0 from 2 reviews', $text);
    }

    public function test_review_text_is_escaped(): void
    {
        $restaurant = $this->make();
        $this->review($restaurant, 4, 'approved', ['name' => '<b>Mallory</b>', 'comment' => '<img src=x onerror=alert(1)>']);

        $html = $this->page($restaurant);

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>Mallory</b>', $html);
    }

    public function test_only_the_latest_ten_reviews_are_listed_but_all_are_counted(): void
    {
        $restaurant = $this->make();
        foreach (range(1, 12) as $n) {
            $this->review($restaurant, 5, 'approved', ['name' => "Reviewer {$n}", 'created_at' => now()->subDays(20 - $n)]);
        }

        $text = $this->text($this->page($restaurant));

        $this->assertStringContainsString('from 12 reviews', $text);
        $this->assertStringContainsString('showing the latest 10', $text);
        $this->assertStringContainsString('Reviewer 12', $text);
        $this->assertStringNotContainsString('Reviewer 1 ', $text); // the oldest two fall off
        $this->assertStringNotContainsString('Reviewer 2 ', $text);
    }

    // ---------- Google data and SEO ----------

    public function test_seo_tags(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        $restaurant = $this->make(['city_id' => $city->id, 'description' => 'A cosy bistro.']);
        $restaurant->cuisines()->attach(Cuisine::factory()->create(['name' => 'French']));

        $html = $this->page($restaurant);

        $this->assertStringContainsString('<title>Chez Marie – French in Chicago | '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="A cosy bistro.">', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/restaurant/chez-marie').'"', $html);
        $this->assertStringContainsString('content="index, follow"', $html);
        $this->assertStringContainsString('<meta property="og:type" content="restaurant.restaurant">', $html);
    }

    public function test_custom_meta_title_and_description_win(): void
    {
        $restaurant = $this->make(['meta_title' => 'Best French in Town', 'meta_description' => 'Hand written description.']);

        $html = $this->page($restaurant);

        $this->assertStringContainsString('<title>Best French in Town | ', $html);
        $this->assertStringContainsString('content="Hand written description."', $html);
    }

    public function test_restaurant_and_breadcrumb_json_ld(): void
    {
        $city = City::factory()->create(['name' => 'Chicago']);
        $restaurant = $this->make([
            'city_id' => $city->id, 'price_range' => 2, 'phone' => '+1 312 555 0100', 'address' => '12 Wabash Ave',
            'latitude' => 41.8781, 'longitude' => -87.6298, 'description' => 'A cosy bistro.',
        ]);
        $restaurant->cuisines()->attach(Cuisine::factory()->create(['name' => 'French']));
        OpeningHour::create(['restaurant_id' => $restaurant->id, 'day_of_week' => 1, 'opens_at' => '11:00', 'closes_at' => '22:00', 'is_closed' => false]);
        $this->review($restaurant, 5, 'approved', ['name' => 'Alice', 'comment' => 'Great']);
        $this->review($restaurant, 4, 'approved');
        $this->review($restaurant, 1, 'pending');

        $html = $this->page($restaurant);
        $data = $this->jsonLd($html, 'Restaurant');
        $crumbs = $this->jsonLd($html, 'BreadcrumbList');

        $this->assertSame('Chez Marie', $data['name']);
        $this->assertSame(url('/restaurant/chez-marie'), $data['url']);
        $this->assertSame('$$', $data['priceRange']);
        $this->assertSame(['French'], $data['servesCuisine']);
        $this->assertSame('12 Wabash Ave', $data['address']['streetAddress']);
        $this->assertSame('Chicago', $data['address']['addressLocality']);
        $this->assertEqualsWithDelta(41.8781, $data['geo']['latitude'], 0.0001);
        $this->assertSame('Monday', $data['openingHoursSpecification'][0]['dayOfWeek']);
        $this->assertSame(2, $data['aggregateRating']['reviewCount']);   // the pending one is not counted
        $this->assertSame(4.5, $data['aggregateRating']['ratingValue']);
        $this->assertCount(2, $data['review']);

        $this->assertSame(['Home', 'Restaurants', 'Chicago', 'Chez Marie'], array_column($crumbs['itemListElement'], 'name'));
    }

    public function test_json_ld_makes_no_rating_claims_without_approved_reviews_and_cannot_break_out_of_its_script(): void
    {
        $restaurant = $this->make(['description' => 'Try </script><script>alert(1)</script> now']);
        $this->review($restaurant, 1, 'pending');

        $html = $this->page($restaurant);
        $data = $this->jsonLd($html, 'Restaurant');

        $this->assertNotNull($data, 'JSON-LD must still be valid');
        $this->assertArrayNotHasKey('aggregateRating', $data);
        $this->assertArrayNotHasKey('review', $data);
        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
    }

    // ---------- Related, links, speed ----------

    public function test_other_restaurants_in_the_same_city_are_suggested_without_drafts_or_itself(): void
    {
        $city = City::factory()->create();
        $restaurant = $this->make(['city_id' => $city->id]);
        $this->make(['city_id' => $city->id, 'slug' => 'neighbour', 'name' => 'Neighbour Cafe']);
        $this->make(['city_id' => $city->id, 'slug' => 'hidden', 'name' => 'Hidden Draft', 'status' => 'draft']);
        $this->make(['slug' => 'faraway', 'name' => 'Faraway Diner']); // another city

        $html = $this->page($restaurant);

        $this->assertStringContainsString('More restaurants in '.$city->name, $html);
        $this->assertStringContainsString('Neighbour Cafe', $html);
        $this->assertStringNotContainsString('Hidden Draft', $html);
        $this->assertStringNotContainsString('Faraway Diner', $html);
        $this->assertSame(1, substr_count($html, 'href="'.url('/restaurant/chez-marie').'"')); // the canonical tag only, no card for itself
    }

    public function test_listing_cards_and_home_cards_now_link_here(): void
    {
        $this->make();

        $this->assertStringContainsString('href="'.url('/restaurant/chez-marie').'"', $this->get('/restaurants')->getContent());
        $this->assertStringContainsString('href="'.url('/restaurant/chez-marie').'"', $this->get('/city/'.Restaurant::first()->city->slug)->getContent());
    }

    public function test_query_count_does_not_grow_with_photos_reviews_or_amenities(): void
    {
        $restaurant = $this->make();
        Restaurant::factory()->count(3)->create(['city_id' => $restaurant->city_id]); // "more in this city" is already full
        $count = function () use ($restaurant) {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get(route('restaurants.show', $restaurant))->assertOk();

            return $queries;
        };

        $this->get(route('restaurants.show', $restaurant)); // warm up
        $few = $count();

        foreach (range(1, 6) as $n) {
            $this->review($restaurant, 4);
            Storage::disk('public')->put("restaurants/{$restaurant->id}/p{$n}.jpg", 'x');
            RestaurantImage::factory()->create(['restaurant_id' => $restaurant->id, 'path' => "restaurants/{$restaurant->id}/p{$n}.jpg", 'sort_order' => $n]);
            $restaurant->amenities()->attach(Amenity::factory()->create());
            $restaurant->cuisines()->attach(Cuisine::factory()->create());
            Restaurant::factory()->create(['city_id' => $restaurant->city_id]);
        }

        $this->assertSame($few, $count());
    }
}
