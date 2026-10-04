<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicLayoutTest extends TestCase
{
    // The admin checks below read the database; this gives them an empty in-memory one (never MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // pages render without needing compiled CSS
    }

    /** Pattern for a tag that points at the home page, e.g. the canonical link (with or without a trailing slash) */
    private function homeTag(string $format): string
    {
        $home = preg_quote(rtrim(config('app.url'), '/'), '#');

        return '#'.sprintf(preg_quote($format, '#'), $home.'/?').'#';
    }

    /** Renders the layout with the given attributes, as a page would use it */
    private function render(string $attributes = 'title="Test page"', string $body = 'Body text'): string
    {
        return Blade::render("<x-layout {$attributes}>{$body}</x-layout>");
    }

    public function test_home_page_loads_inside_the_layout(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Discover great restaurants')
            ->assertSee('<header', false)
            ->assertSee('<footer', false)
            ->assertSee('<main id="main"', false);
    }

    public function test_every_page_has_title_description_canonical_and_social_tags(): void
    {
        $html = $this->get(route('home'))->getContent();

        $this->assertStringContainsString('<title>Discover great restaurants | '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Discover and compare', $html);
        $this->assertMatchesRegularExpression($this->homeTag('<link rel="canonical" href="%s">'), $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Discover great restaurants | '.config('app.name').'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta property="og:site_name" content="'.config('app.name').'">', $html);
        $this->assertMatchesRegularExpression($this->homeTag('<meta property="og:url" content="%s">'), $html);
        $this->assertStringContainsString('<meta name="twitter:title"', $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#0A0A0A">', $html);
        $this->assertStringContainsString('favicon.svg', $html);
    }

    public function test_canonical_address_drops_the_query_string(): void
    {
        $html = $this->get('/?page=2&q=pizza&utm_source=ad')->getContent();

        $this->assertMatchesRegularExpression($this->homeTag('<link rel="canonical" href="%s">'), $html);
        $this->assertStringNotContainsString('utm_source', $html);
    }

    public function test_page_can_set_its_own_description_canonical_image_and_type(): void
    {
        $html = $this->render('title="Chez Marie" description="A cosy French bistro." canonical="https://example.com/restaurant/chez-marie" image="https://example.com/photo.jpg" type="article"');

        $this->assertStringContainsString('<meta name="description" content="A cosy French bistro.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/restaurant/chez-marie">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://example.com/restaurant/chez-marie">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://example.com/photo.jpg">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<title>Chez Marie | '.config('app.name').'</title>', $html);
    }

    public function test_noindex_keeps_a_page_out_of_google(): void
    {
        $this->assertStringContainsString('content="noindex, follow"', $this->render('title="Results" noindex'));
        $this->assertStringContainsString('content="index, follow"', $this->render());
    }

    public function test_titles_and_descriptions_are_escaped(): void
    {
        $html = Blade::render(
            '<x-layout :title="$title" :description="$description">x</x-layout>',
            ['title' => '<script>alert(1)</script>', 'description' => '"><script>alert(2)</script>'],
        );

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<script>alert(2)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_header_footer_skip_link_and_phone_menu_are_present(): void
    {
        $html = $this->get(route('home'))->getContent();

        $this->assertStringContainsString('Skip to content', $html);
        $this->assertStringContainsString('href="#main"', $html);
        $this->assertStringContainsString('data-toggle="mobile-menu"', $html);
        $this->assertStringContainsString('id="mobile-menu"', $html);
        $this->assertStringContainsString('&copy; '.date('Y').' '.config('app.name'), $html);
        $this->assertSame(2, substr_count($html, 'aria-label="Main menu"')); // desktop + phone menu
    }

    public function test_menu_only_links_to_pages_that_exist(): void
    {
        $html = $this->get(route('home'))->getContent();

        // As later sprints add these routes, their links must appear; until then they must not
        $pages = [
            'restaurants.index' => 'Restaurants',
            'cities.index' => 'Cities',
            'cuisines.index' => 'Cuisines',
            'contact.create' => 'Contact',
            'submit.create' => 'Submit a restaurant',
        ];

        foreach ($pages as $routeName => $label) {
            if (Route::has($routeName)) {
                $this->assertStringContainsString('href="'.route($routeName).'"', $html, "{$label} should be linked");
            } else {
                $this->assertStringNotContainsString(">{$label}<", $html, "{$label} must not be shown yet");
            }
        }

        $this->assertStringNotContainsString('href="#"', $html); // never a dead link
    }

    public function test_the_current_page_is_marked_in_the_menu(): void
    {
        $this->get(route('home'))->assertSee('aria-current="page"', false);
    }

    public function test_one_time_status_message_is_shown(): void
    {
        $this->withSession(['status' => 'Thank you, your review is awaiting approval.'])
            ->get(route('home'))
            ->assertSee('Thank you, your review is awaiting approval.');
    }

    public function test_pages_can_add_structured_data_after_the_content(): void
    {
        $html = $this->render('title="With data"', '@push(\'jsonld\')<script type="application/ld+json">{"@type":"Restaurant"}</script>@endpush Visible');

        $this->assertStringContainsString('{"@type":"Restaurant"}', $html);
        $this->assertGreaterThan(strpos($html, '</footer>'), strpos($html, '{"@type":"Restaurant"}'));
    }

    public function test_the_admin_phone_menu_still_works_with_the_shared_script(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-toggle="admin-sidebar"', false)
            ->assertSee('id="admin-sidebar"', false);
    }

    public function test_the_admin_stays_out_of_google(): void
    {
        $this->get(route('admin.dashboard'))->assertSee('content="noindex, nofollow"', false);
    }
}
