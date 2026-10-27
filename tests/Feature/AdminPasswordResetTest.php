<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // The people using these pages cannot sign in, so they are not signed in
    protected bool $signedInAsAdmin = false;

    private const SENT = 'If that is the admin email address, a reset link is on its way.';

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@example.com', 'password' => 'the-old-password-1']);
    }

    public function test_the_login_page_links_to_the_forgot_password_page_and_both_open(): void
    {
        $this->get(route('admin.login'))->assertSee('Forgot your password?')->assertSee(route('admin.password.request'), false);

        $this->get(route('admin.password.request'))
            ->assertOk()
            ->assertSee('Email me a reset link')
            ->assertSee('noindex', false)
            ->assertSee('name="_token"', false);
    }

    public function test_the_right_email_gets_a_link_that_opens_our_admin_reset_page(): void
    {
        Notification::fake();
        $user = $this->owner();

        $this->post(route('admin.password.email'), ['email' => 'Owner@Example.com '])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($message) => str_contains($message, self::SENT));

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, url('/admin/reset-password/')) && str_contains($url, 'email=owner%40example.com');
        });
    }

    public function test_an_unknown_email_gets_the_same_answer_and_no_email_is_sent(): void
    {
        Notification::fake();
        $this->owner();

        $this->post(route('admin.password.email'), ['email' => 'stranger@example.com'])
            ->assertSessionHas('status', fn ($message) => str_contains($message, self::SENT))
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_a_bad_email_shape_is_an_error(): void
    {
        $this->post(route('admin.password.email'), ['email' => 'nope'])->assertSessionHasErrors('email');
        $this->post(route('admin.password.email'), [])->assertSessionHasErrors('email');
    }

    public function test_asking_for_links_is_limited_to_three_a_minute(): void
    {
        Notification::fake();
        $this->owner();

        foreach (range(1, 3) as $i) {
            $this->post(route('admin.password.email'), ['email' => 'owner@example.com'])->assertStatus(302);
        }

        $this->post(route('admin.password.email'), ['email' => 'owner@example.com'])->assertStatus(429);
    }

    public function test_the_reset_page_opens_from_the_link_with_the_email_filled_in(): void
    {
        $this->get(route('admin.password.reset', ['token' => 'abc123', 'email' => 'owner@example.com']))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('value="abc123"', false)
            ->assertSee('value="owner@example.com"', false);
    }

    public function test_a_valid_link_sets_the_new_password_and_the_old_one_stops_working(): void
    {
        $user = $this->owner();
        $oldRememberToken = $user->remember_token;
        $token = Password::broker()->createToken($user);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'owner@example.com',
            'password' => 'a-brand-new-password-2',
            'password_confirmation' => 'a-brand-new-password-2',
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'Your password has been changed. Sign in with the new one.');

        $user->refresh();
        $this->assertTrue(Hash::check('a-brand-new-password-2', $user->password));
        $this->assertFalse(Hash::check('the-old-password-1', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token); // signs out "keep me signed in" computers

        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'a-brand-new-password-2']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_link_works_only_once(): void
    {
        $user = $this->owner();
        $token = Password::broker()->createToken($user);
        $data = ['token' => $token, 'email' => 'owner@example.com', 'password' => 'a-brand-new-password-2', 'password_confirmation' => 'a-brand-new-password-2'];

        $this->post(route('admin.password.update'), $data)->assertRedirect(route('admin.login'));

        $this->post(route('admin.password.update'), ['password' => 'someone-elses-password-3', 'password_confirmation' => 'someone-elses-password-3'] + $data)
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('a-brand-new-password-2', $user->fresh()->password));
    }

    public function test_a_wrong_or_expired_token_changes_nothing_and_gives_one_message(): void
    {
        $user = $this->owner();
        $token = Password::broker()->createToken($user);
        $good = ['email' => 'owner@example.com', 'password' => 'a-brand-new-password-2', 'password_confirmation' => 'a-brand-new-password-2'];

        $this->post(route('admin.password.update'), ['token' => 'wrong'] + $good)
            ->assertSessionHasErrors(['email' => 'This reset link is not valid any more. Ask for a new one on the "Forgot your password?" page.']);

        $this->travel(61)->minutes(); // links last 60 minutes
        $this->post(route('admin.password.update'), ['token' => $token] + $good)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('the-old-password-1', $user->fresh()->password));
    }

    public function test_a_short_or_unmatched_new_password_is_refused(): void
    {
        $user = $this->owner();
        $token = Password::broker()->createToken($user);

        $this->post(route('admin.password.update'), ['token' => $token, 'email' => 'owner@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->post(route('admin.password.update'), ['token' => $token, 'email' => 'owner@example.com', 'password' => 'a-brand-new-password-2', 'password_confirmation' => 'different-password-9'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('the-old-password-1', $user->fresh()->password));
    }

    public function test_someone_already_signed_in_is_sent_to_the_dashboard_instead(): void
    {
        $this->actingAs($this->owner());

        $this->get(route('admin.password.request'))->assertRedirect(route('admin.dashboard'));
    }
}
