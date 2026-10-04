<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Review;
use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ReviewSubmissionTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        RateLimiter::clear('reviews');
        $this->restaurant = Restaurant::factory()->create(['slug' => 'chez-marie', 'name' => 'Chez Marie']);
    }

    private function valid(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Alice Example', 'email' => 'Alice@Example.com', 'rating' => 5,
            'comment' => 'A wonderful evening with lovely food.',
        ];
    }

    private function submit(array $data, ?Restaurant $restaurant = null)
    {
        return $this->post(route('reviews.store', $restaurant ?? $this->restaurant), $data);
    }

    public function test_the_page_shows_the_form_with_csrf_and_a_honeypot(): void
    {
        $html = $this->get('/restaurant/chez-marie')->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('reviews.store', $this->restaurant).'"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="'.Honeypot::FIELD.'"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertSame(5, substr_count($html, 'name="rating"'));
    }

    public function test_a_valid_review_is_saved_as_pending_and_not_shown(): void
    {
        $this->submit($this->valid())
            ->assertRedirect(route('restaurants.show', $this->restaurant).'#review-form')
            ->assertSessionHas('review_submitted');

        $review = Review::sole();
        $this->assertSame('pending', $review->status);
        $this->assertSame($this->restaurant->id, $review->restaurant_id);
        $this->assertSame('alice@example.com', $review->email); // one spelling per address
        $this->assertSame(5, $review->rating);

        $page = $this->get('/restaurant/chez-marie')->getContent();
        $this->assertStringNotContainsString('A wonderful evening', $page);   // nothing is public until approved
        $this->assertStringContainsString('No reviews yet', $page);
    }

    public function test_the_visitor_sees_a_thank_you_message_once(): void
    {
        $this->followingRedirects()->submit($this->valid())
            ->assertSee('Your review was received and will appear here once it has been approved');

        $this->get('/restaurant/chez-marie')->assertDontSee('Your review was received');
    }

    public function test_visitors_cannot_choose_the_status_or_restaurant(): void
    {
        $other = Restaurant::factory()->create();

        $this->submit($this->valid(['status' => 'approved', 'restaurant_id' => $other->id]));

        $review = Review::sole();
        $this->assertSame('pending', $review->status);
        $this->assertSame($this->restaurant->id, $review->restaurant_id);
    }

    public function test_mistakes_show_errors_keep_what_was_typed_and_return_to_the_form(): void
    {
        $response = $this->from('/restaurant/chez-marie')->submit([
            'name' => 'Bob', 'email' => 'not-an-email', 'rating' => 9, 'comment' => 'short',
        ]);

        $response->assertRedirect(route('restaurants.show', $this->restaurant).'#review-form')
            ->assertSessionHasErrors(['email', 'rating', 'comment']);
        $this->assertSame(0, Review::count());

        $page = $this->followingRedirects()->from('/restaurant/chez-marie')->submit([
            'name' => 'Bob', 'email' => 'not-an-email', 'rating' => 9, 'comment' => 'short',
        ])->getContent();
        $this->assertStringContainsString('value="Bob"', $page);   // typed text is kept
        $this->assertStringContainsString('Please write at least 10 characters.', $page);
    }

    public function test_every_field_is_required(): void
    {
        $this->submit([])->assertSessionHasErrors(['name', 'email', 'rating', 'comment']);
        $this->assertSame(0, Review::count());
    }

    public function test_length_limits(): void
    {
        $this->submit($this->valid(['name' => str_repeat('a', 101)]))->assertSessionHasErrors('name');
        $this->submit($this->valid(['comment' => str_repeat('a', 2001)]))->assertSessionHasErrors('comment');
        $this->assertSame(0, Review::count());
    }

    public function test_one_review_per_email_per_restaurant_but_rejected_ones_dont_block(): void
    {
        $this->submit($this->valid())->assertSessionHasNoErrors();
        $this->submit($this->valid(['email' => 'ALICE@example.com']))->assertSessionHasErrors('email');
        $this->assertSame(1, Review::count());

        // a different restaurant is fine
        $this->submit($this->valid(), Restaurant::factory()->create())->assertSessionHasNoErrors();

        Review::query()->where('restaurant_id', $this->restaurant->id)->update(['status' => 'rejected']);
        $this->submit($this->valid())->assertSessionHasNoErrors();
    }

    public function test_a_filled_honeypot_saves_nothing_but_looks_like_success(): void
    {
        $this->submit($this->valid([Honeypot::FIELD => 'http://spam.example']))
            ->assertRedirect(route('restaurants.show', $this->restaurant).'#review-form')
            ->assertSessionHas('review_submitted'); // the robot learns nothing

        $this->assertSame(0, Review::count());
    }

    public function test_rate_limit_allows_three_then_shows_the_friendly_429_page(): void
    {
        foreach (['a', 'b', 'c'] as $letter) {
            $this->submit($this->valid(['email' => "{$letter}@example.com"]))->assertRedirect();
        }

        $response = $this->submit($this->valid(['email' => 'd@example.com']))->assertStatus(429);

        $this->assertNotEmpty($response->headers->get('Retry-After'));
        $this->assertStringContainsString('Too many requests', strip_tags($response->getContent()) ?: 'Too many requests'); // our own page, not a stack trace
        $this->assertStringNotContainsString('Stack Trace', $response->getContent());
        $this->assertSame(3, Review::count());
    }

    public function test_drafts_cannot_be_reviewed_and_unknown_restaurants_are_404(): void
    {
        $draft = Restaurant::factory()->create(['status' => 'draft', 'slug' => 'secret']);

        $this->submit($this->valid(), $draft)->assertNotFound();
        $this->post('/restaurant/nowhere/reviews', $this->valid())->assertNotFound();
        $this->assertSame(0, Review::count());
    }

    public function test_the_form_needs_a_valid_csrf_token_outside_tests(): void
    {
        // Tests skip the token check by default; here we switch it back on to prove the route is protected
        $this->withMiddleware();
        $this->app['env'] = 'production'; // the CSRF check is skipped only in the 'testing' environment

        $this->post(route('reviews.store', $this->restaurant), $this->valid())->assertStatus(419);
        $this->assertSame(0, Review::count());
    }

    public function test_the_review_form_is_not_a_get_address(): void
    {
        $this->get('/restaurant/chez-marie/reviews')->assertStatus(405);
    }
}
