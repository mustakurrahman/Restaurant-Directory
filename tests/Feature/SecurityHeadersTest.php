<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_every_kind_of_response_carries_the_protective_headers(): void
    {
        foreach (['/', '/restaurants', '/contact', '/admin', '/sitemap.xml', '/robots.txt', '/no-such-page'] as $path) {
            $response = $this->get($path);

            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'), $path);
            $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'), $path);
            $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'), $path);
            $this->assertStringContainsString('geolocation=()', $response->headers->get('Permissions-Policy'), $path);
        }
    }

    public function test_form_posts_and_error_pages_get_them_too(): void
    {
        $this->post('/contact', [])->assertHeader('X-Content-Type-Options', 'nosniff');   // validation redirect
        $this->get('/preview-nothing-here')->assertNotFound()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_hsts_is_only_sent_over_https_in_production(): void
    {
        $this->assertNull($this->get('/')->headers->get('Strict-Transport-Security'));                                  // testing, http

        $this->app['env'] = 'production';
        $this->assertNull($this->get('/')->headers->get('Strict-Transport-Security'));                                  // production, http

        $secure = $this->get('https://localhost/');
        $this->assertSame('max-age=31536000', $secure->headers->get('Strict-Transport-Security'));                     // production, https

        $this->app['env'] = 'local';
        $this->assertNull($this->get('https://localhost/')->headers->get('Strict-Transport-Security'));                 // local, https
    }
}
