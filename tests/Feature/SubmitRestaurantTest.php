<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Submission;
use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class SubmitRestaurantTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function valid(array $overrides = []): array
    {
        return $overrides + [
            'restaurant_name' => 'Luigi Corner', 'address' => '5 Main Street', 'city' => 'Springfield',
            'cuisine' => 'Italian', 'phone' => '+1 555 0100', 'website' => 'https://luigi.example',
            'description' => 'Wood-fired pizza.', 'submitter_name' => 'Alice Example', 'submitter_email' => 'Alice@Example.com',
        ];
    }

    // Switches the rate limit off, so tests that send many requests are not blocked (the limit has its own test)
    private function submit(array $data)
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        return $this->post(route('submit.store'), $data);
    }

    public function test_the_page_shows_the_form_with_seo_csrf_and_honeypot(): void
    {
        $html = $this->get('/submit-restaurant')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Submit a restaurant | '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/submit-restaurant').'"', $html);
        $this->assertStringContainsString('content="index, follow"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="'.Honeypot::FIELD.'"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        foreach (['restaurant_name', 'address', 'city', 'cuisine', 'phone', 'website', 'description', 'submitter_name', 'submitter_email'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html);
        }
    }

    public function test_a_valid_suggestion_is_saved_as_pending_and_creates_no_restaurant(): void
    {
        $this->submit($this->valid())
            ->assertRedirect(route('submit.create').'#submit-form')
            ->assertSessionHas('submission_received');

        $submission = Submission::sole();
        $this->assertSame('pending', $submission->status);
        $this->assertSame('Luigi Corner', $submission->restaurant_name);
        $this->assertSame('alice@example.com', $submission->submitter_email);
        $this->assertSame(0, Restaurant::count()); // nothing is public until the owner acts
    }

    public function test_visitors_cannot_choose_the_status(): void
    {
        $this->submit($this->valid(['status' => 'approved']));

        $this->assertSame('pending', Submission::sole()->status);
    }

    public function test_the_visitor_sees_a_thank_you_once(): void
    {
        $this->followingRedirects()->submit($this->valid())->assertSee('We received your suggestion');
        $this->get('/submit-restaurant')->assertDontSee('We received your suggestion');
    }

    public function test_optional_fields_can_be_left_empty(): void
    {
        $this->submit($this->valid(['cuisine' => '', 'phone' => '  ', 'website' => '', 'description' => '']))->assertSessionHasNoErrors();

        $submission = Submission::sole();
        $this->assertNull($submission->cuisine);
        $this->assertNull($submission->phone);
        $this->assertNull($submission->website);
        $this->assertNull($submission->description);
    }

    public function test_required_fields_and_formats(): void
    {
        $this->submit([])->assertSessionHasErrors(['restaurant_name', 'address', 'city', 'submitter_name', 'submitter_email']);
        $this->submit($this->valid(['submitter_email' => 'nope']))->assertSessionHasErrors('submitter_email');
        $this->submit($this->valid(['phone' => 'call me maybe']))->assertSessionHasErrors('phone');
        $this->submit($this->valid(['website' => 'javascript:alert(1)']))->assertSessionHasErrors('website');
        $this->submit($this->valid(['website' => 'luigi.example']))->assertSessionHasErrors('website'); // needs http(s)://
        $this->submit($this->valid(['restaurant_name' => str_repeat('a', 256)]))->assertSessionHasErrors('restaurant_name');
        $this->submit($this->valid(['description' => str_repeat('a', 2001)]))->assertSessionHasErrors('description');
        $this->assertSame(0, Submission::count());
    }

    public function test_errors_return_to_the_form_and_keep_what_was_typed(): void
    {
        $this->from('/submit-restaurant')->submit($this->valid(['submitter_email' => 'nope']))
            ->assertRedirect(route('submit.create').'#submit-form');

        $html = $this->followingRedirects()->from('/submit-restaurant')->submit($this->valid(['submitter_email' => 'nope']))->getContent();
        $this->assertStringContainsString('value="Luigi Corner"', $html);
        $this->assertStringContainsString('valid email address', $html);
    }

    public function test_the_same_person_cannot_send_the_same_restaurant_twice_while_pending(): void
    {
        $this->submit($this->valid())->assertSessionHasNoErrors();
        $this->submit($this->valid(['restaurant_name' => 'LUIGI corner', 'submitter_email' => 'ALICE@example.com']))->assertSessionHasErrors('submitter_email');
        $this->assertSame(1, Submission::count());

        // another restaurant from the same person is fine; so is the same name once the first was handled
        $this->submit($this->valid(['restaurant_name' => 'Another Place']))->assertSessionHasNoErrors();
        Submission::where('restaurant_name', 'Luigi Corner')->update(['status' => 'rejected']);
        $this->submit($this->valid())->assertSessionHasNoErrors();
    }

    public function test_a_filled_honeypot_saves_nothing_but_looks_like_success(): void
    {
        $this->submit($this->valid([Honeypot::FIELD => 'http://spam.example']))
            ->assertRedirect(route('submit.create').'#submit-form')
            ->assertSessionHas('submission_received');

        $this->assertSame(0, Submission::count());
    }

    public function test_rate_limit_allows_three_per_hour_then_shows_the_429_page(): void
    {
        foreach (['a', 'b', 'c'] as $letter) {
            $this->post(route('submit.store'), $this->valid(['restaurant_name' => "Place {$letter}"]))->assertRedirect();
        }

        $response = $this->post(route('submit.store'), $this->valid(['restaurant_name' => 'Place d']))->assertStatus(429);

        $this->assertNotEmpty($response->headers->get('Retry-After'));
        $this->assertStringNotContainsString('Stack Trace', $response->getContent());
        $this->assertSame(3, Submission::count());
    }

    public function test_text_is_escaped_when_shown_again(): void
    {
        $html = $this->followingRedirects()->from('/submit-restaurant')
            ->submit($this->valid(['restaurant_name' => '"><script>alert(1)</script>', 'submitter_email' => 'bad']))
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_the_form_needs_a_csrf_token_in_real_use(): void
    {
        $this->withMiddleware();
        $this->app['env'] = 'production'; // the CSRF check is skipped only in the 'testing' environment

        $this->post(route('submit.store'), $this->valid())->assertStatus(419);
        $this->assertSame(0, Submission::count());
    }

    public function test_site_wide_links_to_the_form_are_now_live(): void
    {
        $home = $this->get('/')->getContent();

        $this->assertStringContainsString('href="'.route('submit.create').'"', $home);
        $this->assertStringContainsString('Own or love a restaurant?', $home);
    }
}
