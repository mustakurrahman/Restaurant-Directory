<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class ContactFormTest extends TestCase
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
            'name' => 'Alice Example', 'email' => 'Alice@Example.com', 'subject' => 'Wrong hours',
            'message' => 'The opening hours on one listing are out of date.',
        ];
    }

    // Switches the rate limit off, so tests that send many requests are not blocked (the limit has its own test)
    private function send(array $data)
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        return $this->post(route('contact.store'), $data);
    }

    public function test_the_page_shows_the_form_with_seo_csrf_and_honeypot(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Contact us | '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/contact').'"', $html);
        $this->assertStringContainsString('content="index, follow"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="'.Honeypot::FIELD.'"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        foreach (['name', 'email', 'subject', 'message'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html);
        }
    }

    public function test_the_menu_now_links_to_contact_and_marks_it_current(): void
    {
        $home = $this->get('/')->getContent();
        $this->assertStringContainsString('href="'.route('contact.create').'"', $home);

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('contact.create'), '#').'"[^>]*aria-current="page"|aria-current="page"[^>]*href="'.preg_quote(route('contact.create'), '#').'"#', $this->get('/contact')->getContent());
    }

    public function test_a_valid_message_is_saved_unread(): void
    {
        $this->send($this->valid())
            ->assertRedirect(route('contact.create').'#contact-form')
            ->assertSessionHas('message_sent');

        $message = ContactMessage::sole();
        $this->assertFalse($message->is_read);
        $this->assertSame('alice@example.com', $message->email);
        $this->assertSame('Wrong hours', $message->subject);
    }

    public function test_visitors_cannot_pre_mark_a_message_as_read(): void
    {
        $this->send($this->valid(['is_read' => 1]));

        $this->assertFalse(ContactMessage::sole()->is_read);
    }

    public function test_the_visitor_sees_a_confirmation_once(): void
    {
        $this->followingRedirects()->send($this->valid())->assertSee('Message sent!');
        $this->get('/contact')->assertDontSee('Message sent!');
    }

    public function test_the_subject_is_optional(): void
    {
        $this->send($this->valid(['subject' => '  ']))->assertSessionHasNoErrors();

        $this->assertNull(ContactMessage::sole()->subject);
    }

    public function test_required_fields_formats_and_limits(): void
    {
        $this->send([])->assertSessionHasErrors(['name', 'email', 'message']);
        $this->send($this->valid(['email' => 'nope']))->assertSessionHasErrors('email');
        $this->send($this->valid(['message' => 'too short']))->assertSessionHasErrors('message');
        $this->send($this->valid(['message' => str_repeat('a', 3001)]))->assertSessionHasErrors('message');
        $this->send($this->valid(['name' => str_repeat('a', 101)]))->assertSessionHasErrors('name');
        $this->send($this->valid(['subject' => str_repeat('a', 151)]))->assertSessionHasErrors('subject');
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_errors_return_to_the_form_keep_the_text_and_escape_it(): void
    {
        $this->from('/contact')->send($this->valid(['email' => 'nope']))->assertRedirect(route('contact.create').'#contact-form');

        $html = $this->followingRedirects()->from('/contact')
            ->send($this->valid(['email' => 'nope', 'name' => '"><script>alert(1)</script>']))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('valid email address', $html);
        $this->assertStringContainsString('The opening hours on one listing', $html); // typed message kept
    }

    public function test_a_filled_honeypot_saves_nothing_but_looks_like_success(): void
    {
        $this->send($this->valid([Honeypot::FIELD => 'http://spam.example']))
            ->assertRedirect(route('contact.create').'#contact-form')
            ->assertSessionHas('message_sent');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_rate_limit_allows_five_per_hour_then_shows_the_429_page(): void
    {
        foreach (range(1, 5) as $n) {
            $this->post(route('contact.store'), $this->valid(['email' => "p{$n}@example.com"]))->assertRedirect();
        }

        $response = $this->post(route('contact.store'), $this->valid(['email' => 'p6@example.com']))->assertStatus(429);

        $this->assertNotEmpty($response->headers->get('Retry-After'));
        $this->assertStringNotContainsString('Stack Trace', $response->getContent());
        $this->assertSame(5, ContactMessage::count());
    }

    public function test_the_form_needs_a_csrf_token_in_real_use(): void
    {
        $this->withMiddleware();
        $this->app['env'] = 'production'; // the CSRF check is skipped only in the 'testing' environment

        $this->post(route('contact.store'), $this->valid())->assertStatus(419);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_address_is_not_available_for_other_methods(): void
    {
        $this->put('/contact')->assertStatus(405);
    }
}
