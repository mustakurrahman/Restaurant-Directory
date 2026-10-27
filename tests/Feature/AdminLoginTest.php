<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    // These tests are about visitors who are not signed in
    protected bool $signedInAsAdmin = false;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@example.com', 'password' => 'a-long-test-password']);
    }

    public function test_every_admin_page_sends_visitors_to_the_login(): void
    {
        foreach (['/admin', '/admin/restaurants', '/admin/restaurants/create', '/admin/cities', '/admin/cuisines',
            '/admin/amenities', '/admin/reviews', '/admin/submissions', '/admin/messages'] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
    }

    public function test_changing_things_without_signing_in_is_refused_and_changes_nothing(): void
    {
        $this->post(route('admin.cities.store'), ['name' => 'Sneaky'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('cities', 0);
    }

    public function test_the_login_page_opens_and_is_kept_out_of_google(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('noindex', false)
            ->assertSee('name="_token"', false);
    }

    public function test_the_right_email_and_password_sign_in_and_return_to_the_page_asked_for(): void
    {
        $this->owner();

        $this->get('/admin/cities')->assertRedirect(route('admin.login'));

        $this->post(route('admin.login.store'), ['email' => 'Owner@Example.com ', 'password' => 'a-long-test-password'])
            ->assertRedirect('/admin/cities');

        $this->assertAuthenticated();
        $this->get('/admin')->assertOk();
    }

    public function test_a_wrong_password_or_unknown_email_gives_the_same_message_and_does_not_sign_in(): void
    {
        $this->owner();

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'wrong'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'The email or password is not correct.']);

        $this->post(route('admin.login.store'), ['email' => 'nobody@example.com', 'password' => 'a-long-test-password'])
            ->assertSessionHasErrors(['email' => 'The email or password is not correct.']);

        $this->assertGuest();
    }

    public function test_missing_fields_show_errors(): void
    {
        $this->post(route('admin.login.store'), [])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_the_password_is_never_sent_back_to_the_form(): void
    {
        $this->owner();

        $this->followingRedirects()
            ->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'wrong-secret-xyz'])
            ->assertDontSee('wrong-secret-xyz');
    }

    public function test_the_sixth_attempt_in_a_minute_is_blocked(): void
    {
        $this->owner();

        foreach (range(1, 5) as $i) {
            $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'wrong'])->assertStatus(302);
        }

        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'a-long-test-password'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAs($this->owner());

        $this->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'You are signed out.');

        $this->assertGuest();
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_someone_already_signed_in_is_taken_from_the_login_page_to_the_dashboard(): void
    {
        $this->actingAs($this->owner());

        $this->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_admin_shows_a_sign_out_button_and_public_pages_do_not(): void
    {
        $this->actingAs($this->owner());

        $this->get('/admin')->assertOk()->assertSee('Sign out')->assertSee(route('admin.logout'), false);
        $this->get('/')->assertOk()->assertDontSee('Sign out');
    }
}
