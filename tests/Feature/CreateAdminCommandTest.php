<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signedInAsAdmin = false;

    private const ASK = 'Password (at least 12 characters; nothing shows while you type)';

    private const AGAIN = 'Type the password again';

    public function test_it_creates_an_admin_who_can_sign_in(): void
    {
        $this->artisan('admin:create', ['email' => 'Boss@Example.com'])
            ->expectsQuestion(self::ASK, 'a-long-secret-1')
            ->expectsQuestion(self::AGAIN, 'a-long-secret-1')
            ->expectsOutputToContain('Admin created: boss@example.com')
            ->assertSuccessful();

        $user = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertNotSame('a-long-secret-1', $user->password); // stored scrambled, never as typed

        $this->post(route('admin.login.store'), ['email' => 'boss@example.com', 'password' => 'a-long-secret-1']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_running_it_again_changes_the_password_without_a_second_user(): void
    {
        User::factory()->create(['email' => 'boss@example.com', 'password' => 'old-password-123']);

        $this->artisan('admin:create', ['email' => 'boss@example.com'])
            ->expectsQuestion(self::ASK, 'brand-new-secret-2')
            ->expectsQuestion(self::AGAIN, 'brand-new-secret-2')
            ->expectsOutputToContain('Password changed for boss@example.com')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->post(route('admin.login.store'), ['email' => 'boss@example.com', 'password' => 'brand-new-secret-2']);
        $this->assertAuthenticated();
    }

    public function test_a_short_password_a_mismatch_or_a_bad_email_save_nothing(): void
    {
        $this->artisan('admin:create', ['email' => 'boss@example.com'])
            ->expectsQuestion(self::ASK, 'short')
            ->expectsOutputToContain('at least 12 characters')
            ->assertFailed();

        $this->artisan('admin:create', ['email' => 'boss@example.com'])
            ->expectsQuestion(self::ASK, 'a-long-secret-1')
            ->expectsQuestion(self::AGAIN, 'a-long-secret-2')
            ->expectsOutputToContain('different')
            ->assertFailed();

        $this->artisan('admin:create', ['email' => 'not-an-email'])
            ->expectsOutputToContain('does not look like an email')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
