<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    // The admin 404 test looks a record up, so it needs an empty in-memory database (never MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // pages render without needing compiled CSS

        // Small doors to trigger each error on purpose
        Route::get('/_test/abort/{code}', fn (int $code) => abort($code))->where('code', '[0-9]+');
        Route::get('/_test/secret-403', fn () => abort(403, 'Internal reason: admin flag missing'));
        Route::get('/_test/too-many', fn () => abort(429, '', array_filter(['Retry-After' => request('after')])));
        Route::get('/_test/boom', fn () => throw new RuntimeException('database password is hunter2'));
    }

    // ---------- 404 ----------

    public function test_unknown_address_shows_the_branded_404_inside_the_site_layout(): void
    {
        $html = $this->get('/this-page-does-not-exist')
            ->assertNotFound() // the real status code stays 404, so Google drops the page
            ->assertSee("We can't find that page")
            ->getContent();

        $this->assertStringContainsString('content="noindex, follow"', $html);
        $this->assertStringContainsString('<title>Page not found | '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('Skip to content', $html);              // full site layout
        $this->assertStringContainsString('aria-label="Main menu"', $html);       // visitors can navigate on
        $this->assertStringContainsString('href="'.url('/').'"', $html);          // way back home
        $this->assertStringContainsString('Back to the homepage', $html);
    }

    public function test_a_missing_record_in_the_admin_also_shows_the_branded_404(): void
    {
        $this->get('/admin/cities/9999/edit')->assertNotFound()->assertSee("We can't find that page");
    }

    public function test_the_404_offers_the_restaurant_list_once_it_exists(): void
    {
        $this->get('/nope')->assertNotFound()->assertDontSee('Browse restaurants');

        Route::get('/restaurants', fn () => 'x')->name('restaurants.index');
        app('router')->getRoutes()->refreshNameLookups();

        $this->get('/nope')->assertNotFound()->assertSee('Browse restaurants');
    }

    // ---------- 403, 419, 429 ----------

    public function test_403_hides_the_internal_reason(): void
    {
        $this->get('/_test/secret-403')
            ->assertForbidden()
            ->assertSee("You don't have access to this page")
            ->assertDontSee('Internal reason')
            ->assertDontSee('admin flag');
    }

    public function test_419_explains_the_expired_form_and_offers_to_go_back(): void
    {
        $this->from('/admin/cities/create')
            ->get('/_test/abort/419')
            ->assertStatus(419)
            ->assertSee('This page has expired')
            ->assertSee('Go back and try again')
            ->assertSee('href="'.url('/admin/cities/create').'"', false);
    }

    public function test_429_tells_how_long_to_wait(): void
    {
        $cases = [
            null => 'wait a minute',
            1 => 'wait 1 second ',
            30 => 'wait 30 seconds',
            90 => 'wait 2 minutes',
            600 => 'wait 10 minutes',
        ];

        foreach ($cases as $after => $expected) {
            $response = $this->get('/_test/too-many'.($after ? "?after={$after}" : ''))->assertStatus(429);
            $this->assertStringContainsString($expected, preg_replace('/\s+/', ' ', strip_tags($response->getContent())).' ');
        }
    }

    // ---------- 500, 503 and the rest ----------

    public function test_500_shows_a_friendly_page_and_never_the_real_error(): void
    {
        config(['app.debug' => false, 'logging.default' => 'null']); // production behaviour; keep the test out of the log file

        $response = $this->get('/_test/boom')
            ->assertStatus(500)
            ->assertSee('Something went wrong on our side');

        $html = $response->getContent();
        $this->assertStringNotContainsString('hunter2', $html);          // the exception message stays private
        $this->assertStringNotContainsString('RuntimeException', $html);
        $this->assertStringNotContainsString('Stack trace', $html);
        $this->assertStringContainsString('content="noindex, nofollow"', $html);
        $this->assertStringNotContainsString('Skip to content', $html);  // standalone page, not the full layout
        $this->assertStringContainsString('href="'.url('/').'"', $html);
    }

    public function test_a_deliberate_500_uses_the_branded_page_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);

        $this->get('/_test/abort/500')->assertStatus(500)->assertSee('Something went wrong on our side');
    }

    public function test_503_says_we_will_be_right_back_without_a_pointless_home_button(): void
    {
        $html = $this->get('/_test/abort/503')
            ->assertStatus(503)
            ->assertSee("We'll be right back")
            ->getContent();

        $this->assertStringContainsString('Try again', $html);
        $this->assertStringNotContainsString('Back to the homepage', $html); // the homepage would show this same page
    }

    public function test_other_codes_get_the_generic_page_with_their_real_status_and_name(): void
    {
        // 401/405/408/413/418 use 4xx, 501/502/504 use 5xx. We must prove it is OUR page and not
        // Laravel's developer error page, which also happens to contain the status names.
        $cases = [
            401 => 'Unauthorized', 405 => 'Method Not Allowed', 408 => 'Request Timeout',
            413 => 'Content Too Large', 418 => "I'm a teapot", 501 => 'Not Implemented',
            502 => 'Bad Gateway', 504 => 'Gateway Timeout',
        ];

        foreach ($cases as $code => $name) {
            $html = $this->get("/_test/abort/{$code}")->assertStatus($code)->getContent();

            $this->assertStringContainsString('Sorry, something unexpected happened.', $html, "{$code}: not our generic page");
            $this->assertStringContainsString('<title>'.e($name).' | '.config('app.name').'</title>', $html, "{$code}: wrong title");
            $this->assertStringContainsString('content="noindex, nofollow"', $html, "{$code}: must stay out of Google");
            $this->assertStringNotContainsString('Stack Trace', $html, "{$code}: leaked the developer page");
            $this->assertStringNotContainsString('Illuminate\\', $html, "{$code}: leaked internals");
        }
    }

    public function test_every_error_page_is_our_own_in_debug_mode_too(): void
    {
        config(['app.debug' => true]);

        foreach ([403, 404, 419, 429, 500, 503, 418, 502] as $code) {
            $html = $this->get("/_test/abort/{$code}")->assertStatus($code)->getContent();

            $this->assertStringContainsString('content="noindex, ', $html, "{$code} is not one of our pages");
            $this->assertStringNotContainsString('Stack Trace', $html, "{$code} shows the developer page");
        }
    }

    // ---------- Safety ----------

    public function test_standalone_error_pages_do_not_touch_the_database(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->get('/_test/abort/503');
        $this->get('/_test/abort/502');

        $this->assertSame(0, $queries);
    }

    public function test_standalone_error_views_never_use_session_or_user_data(): void
    {
        // If the database is down, the session (stored in it) is down too. These views must work regardless.
        $files = [
            'components/error-shell.blade.php',
            'errors/500.blade.php',
            'errors/503.blade.php',
            'errors/generic.blade.php',
            'errors/4xx.blade.php',
            'errors/5xx.blade.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(resource_path("views/{$file}"));

            foreach (['session(', 'old(', '$errors', '@csrf', 'auth(', 'Auth::', 'url()->previous', 'request()->user'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, "{$file} must not use {$forbidden}");
            }
        }
    }

    public function test_json_requests_get_json_not_html(): void
    {
        $this->getJson('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_the_preview_door_is_not_available_outside_local_development(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());

        $this->assertFalse($uris->contains(fn ($uri) => str_contains($uri, 'preview-error')));
    }
}
