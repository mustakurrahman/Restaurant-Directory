<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    // The default "unsaved" test sign-in is not enough here: these tests need a real account to compare with
    protected bool $signedInAsAdmin = false;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create(['name' => 'Me Myself', 'email' => 'me@example.com']);
        $this->actingAs($this->me);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Helper',
            'email' => 'helper@example.com',
            'password' => 'a-long-helper-password',
            'password_confirmation' => 'a-long-helper-password',
        ], $overrides);
    }

    public function test_the_sidebar_has_a_users_link_and_the_list_shows_everyone_marking_you(): void
    {
        User::factory()->create(['name' => 'Second Admin', 'email' => 'second@example.com']);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Users')
            ->assertSee('Me Myself')
            ->assertSee('second@example.com')
            ->assertSee('You')
            ->assertSee('2 admins');
    }

    public function test_there_is_no_delete_button_for_your_own_row_but_there_is_for_others(): void
    {
        $other = User::factory()->create();

        $page = $this->get(route('admin.users.index'))->getContent();

        // Look for the delete FORM's action: the edit link of the same row starts with the same address
        $this->assertStringContainsString('action="'.route('admin.users.destroy', $other).'"', $page);
        $this->assertStringNotContainsString('action="'.route('admin.users.destroy', $this->me).'"', $page);
    }

    public function test_search_finds_by_name_or_email_and_treats_percent_literally(): void
    {
        User::factory()->create(['name' => 'Anna Baker', 'email' => 'anna@example.com']);
        User::factory()->create(['name' => 'Carl Dunn', 'email' => 'carl@example.com']);

        $this->get(route('admin.users.index', ['q' => 'baker']))->assertSee('anna@example.com')->assertDontSee('carl@example.com');
        $this->get(route('admin.users.index', ['q' => 'carl@']))->assertSee('Carl Dunn')->assertDontSee('Anna Baker');
        $this->get(route('admin.users.index', ['q' => '%']))->assertOk()->assertSee('No admins match');
        $this->get(route('admin.users.index', ['q' => ['not', 'text']]))->assertOk(); // junk in the address never breaks it
    }

    public function test_the_add_form_opens(): void
    {
        $this->get(route('admin.users.create'))->assertOk()->assertSee('Add admin')->assertSee('name="password_confirmation"', false);
    }

    public function test_adding_an_admin_stores_a_scrambled_password_and_the_new_admin_can_sign_in(): void
    {
        $this->post(route('admin.users.store'), $this->validData(['email' => 'Helper@Example.com ']))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $user = User::where('email', 'helper@example.com')->firstOrFail();
        $this->assertSame('New Helper', $user->name);
        $this->assertNotSame('a-long-helper-password', $user->password);

        $this->post(route('admin.logout'));
        $this->post(route('admin.login.store'), ['email' => 'helper@example.com', 'password' => 'a-long-helper-password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_adding_needs_a_name_a_unique_email_and_a_long_matching_password(): void
    {
        $this->post(route('admin.users.store'), [])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->post(route('admin.users.store'), $this->validData(['email' => 'me@example.com']))->assertSessionHasErrors('email');
        $this->post(route('admin.users.store'), $this->validData(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post(route('admin.users.store'), $this->validData(['password_confirmation' => 'does-not-match-at-all']))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_edit_form_shows_the_saved_values_and_never_the_password(): void
    {
        $other = User::factory()->create(['name' => 'Edit Me', 'email' => 'edit@example.com']);

        $this->get(route('admin.users.edit', $other))
            ->assertOk()
            ->assertSee('value="Edit Me"', false)
            ->assertSee('value="edit@example.com"', false)
            ->assertDontSee($other->password, false);
    }

    public function test_editing_without_a_new_password_keeps_the_old_one(): void
    {
        $other = User::factory()->create(['email' => 'edit@example.com', 'password' => 'the-old-password-1']);

        $this->put(route('admin.users.update', $other), ['name' => 'Renamed', 'email' => 'edit@example.com', 'password' => '', 'password_confirmation' => ''])
            ->assertRedirect(route('admin.users.index'));

        $other->refresh();
        $this->assertSame('Renamed', $other->name);
        $this->assertTrue(Hash::check('the-old-password-1', $other->password));
    }

    public function test_editing_with_a_new_password_changes_it_and_signs_out_remembered_computers(): void
    {
        $other = User::factory()->create(['password' => 'the-old-password-1']);
        $oldToken = $other->remember_token;

        $this->put(route('admin.users.update', $other), [
            'name' => $other->name, 'email' => $other->email,
            'password' => 'a-brand-new-password-2', 'password_confirmation' => 'a-brand-new-password-2',
        ])->assertRedirect(route('admin.users.index'));

        $other->refresh();
        $this->assertTrue(Hash::check('a-brand-new-password-2', $other->password));
        $this->assertNotSame($oldToken, $other->remember_token);
    }

    public function test_an_account_can_keep_its_own_email_but_not_take_anothers(): void
    {
        $other = User::factory()->create(['email' => 'other@example.com']);

        $this->put(route('admin.users.update', $other), ['name' => 'Same', 'email' => 'other@example.com'])->assertSessionHasNoErrors();
        $this->put(route('admin.users.update', $other), ['name' => 'Same', 'email' => 'me@example.com'])->assertSessionHasErrors('email');
    }

    public function test_deleting_another_admin_works_and_they_can_no_longer_sign_in(): void
    {
        $other = User::factory()->create(['email' => 'gone@example.com', 'password' => 'the-old-password-1']);

        $this->delete(route('admin.users.destroy', $other))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($other);
    }

    public function test_you_cannot_delete_yourself(): void
    {
        $this->delete(route('admin.users.destroy', $this->me))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($this->me);
        $this->assertAuthenticatedAs($this->me);
    }

    public function test_the_owner_cannot_be_deleted_by_another_admin(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);

        $this->delete(route('admin.users.destroy', $owner))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', 'The Owner account cannot be deleted.');

        $this->assertModelExists($owner);
    }

    public function test_the_owner_cannot_be_deleted_even_by_themselves(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        $this->delete(route('admin.users.destroy', $owner))->assertSessionHas('error');

        $this->assertModelExists($owner);
    }

    public function test_the_list_marks_the_owner_and_offers_no_delete_button_for_them(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'The Boss']);

        $page = $this->get(route('admin.users.index'))->assertOk()->assertSee('Owner')->getContent();

        $this->assertStringNotContainsString('action="'.route('admin.users.destroy', $owner).'"', $page);
    }

    public function test_another_admin_cannot_open_or_change_the_owner_account(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'owner@example.com', 'password' => 'the-owners-password-1']);

        $this->get(route('admin.users.edit', $owner))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', 'Only the Owner can change the Owner account.');

        $this->put(route('admin.users.update', $owner), [
            'name' => 'Hijacked', 'email' => 'attacker@example.com',
            'password' => 'an-attackers-password-9', 'password_confirmation' => 'an-attackers-password-9',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHas('error');

        $owner->refresh();
        $this->assertSame('owner@example.com', $owner->email);
        $this->assertTrue(Hash::check('the-owners-password-1', $owner->password));
        // and the list does not even show an Edit link for the Owner
        $this->assertStringNotContainsString(route('admin.users.edit', $owner), $this->get(route('admin.users.index'))->getContent());
    }

    public function test_the_owner_can_change_their_own_account_and_edit_other_admins(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
        $this->actingAs($owner);

        $this->put(route('admin.users.update', $owner), ['name' => 'Renamed Owner', 'email' => 'owner@example.com'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Owner', $owner->fresh()->name);
        $this->assertTrue($owner->fresh()->is_owner);
        $this->get(route('admin.users.edit', $this->me))->assertOk();
    }

    public function test_a_new_admin_added_here_is_never_an_owner_even_if_the_form_says_so(): void
    {
        $this->post(route('admin.users.store'), $this->validData(['is_owner' => '1']))->assertSessionHasNoErrors();

        $this->assertFalse(User::where('email', 'helper@example.com')->firstOrFail()->is_owner);
    }

    public function test_visitors_who_are_not_signed_in_cannot_use_any_users_page(): void
    {
        $other = User::factory()->create();
        $this->post(route('admin.logout'));

        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.users.store'), $this->validData())->assertRedirect(route('admin.login'));
        $this->delete(route('admin.users.destroy', $other))->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('users', 2);
    }

    public function test_a_missing_account_is_a_404(): void
    {
        $this->get(route('admin.users.edit', 9999))->assertNotFound();
    }
}
