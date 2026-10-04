<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function contact(string $fakeVisitorIp, string $email)
    {
        return $this->withHeaders(['X-Forwarded-For' => $fakeVisitorIp])->post(route('contact.store'), [
            'name' => 'Tester', 'email' => $email, 'message' => 'A message that is long enough.',
        ]);
    }

    public function test_by_default_nothing_is_trusted_so_a_visitor_cannot_dodge_the_spam_limit_with_a_fake_header(): void
    {
        $this->assertEmpty(config('trustedproxy.proxies'));

        // The contact limit is 5 per hour per visitor. A spammer sending a different made-up address each time
        // must still be recognised as ONE visitor (the real connection) and be stopped.
        foreach (range(1, 5) as $n) {
            $this->contact("203.0.113.{$n}", "n{$n}@example.com")->assertRedirect();
        }

        $this->contact('203.0.113.99', 'n6@example.com')->assertStatus(429);
    }

    public function test_with_a_trusted_proxy_each_real_visitor_gets_their_own_limit(): void
    {
        config(['trustedproxy.proxies' => '*']);

        // Behind a proxy every request arrives from the proxy; the visitor's real address is in the header.
        foreach (range(1, 5) as $n) {
            $this->contact('203.0.113.1', "a{$n}@example.com")->assertRedirect();
        }
        $this->contact('203.0.113.1', 'a6@example.com')->assertStatus(429);       // same visitor: stopped

        $this->contact('203.0.113.2', 'b1@example.com')->assertRedirect();         // a different visitor: fine
    }

    public function test_the_forwarded_address_and_https_are_only_believed_from_trusted_proxies(): void
    {
        $headers = ['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https'];

        $this->withHeaders($headers)->get('/contact');
        $this->assertSame('127.0.0.1', request()->ip());
        $this->assertFalse(request()->isSecure());

        config(['trustedproxy.proxies' => '*']);

        $this->withHeaders($headers)->get('/contact');
        $this->assertSame('203.0.113.7', request()->ip());
        $this->assertTrue(request()->isSecure());
    }

    public function test_a_list_of_addresses_is_accepted(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1, 10.0.0.5']);

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.8'])->get('/contact');

        $this->assertSame('203.0.113.8', request()->ip());
    }

    public function test_an_address_that_is_not_in_the_list_is_not_trusted(): void
    {
        config(['trustedproxy.proxies' => '10.9.9.9']);

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.8'])->get('/contact');

        $this->assertSame('127.0.0.1', request()->ip());
    }
}
