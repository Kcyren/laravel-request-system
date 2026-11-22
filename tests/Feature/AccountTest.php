<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_and_wrong_password_is_rejected_generically(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in');

        $user = User::factory()->create();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_user_can_sign_in_and_reach_requests(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('requests.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_page_shows_own_profile_only(): void
    {
        $user = User::factory()->create(['name' => 'Profile Owner']);

        $this->actingAs($user)->get(route('account.show'))
            ->assertOk()->assertSee('Profile Owner')->assertSee('Change password');
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'not-it',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_change_rejects_weak_or_unconfirmed_passwords(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrorsIn('password', 'password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_change_succeeds_and_is_hashed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertRedirect(route('account.show'))->assertSessionHasNoErrors();

        $stored = $user->fresh()->password;
        $this->assertNotSame('NewPass123', $stored);
        $this->assertTrue(Hash::check('NewPass123', $stored));
    }

    public function test_accounts_directory_is_admin_only(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($student)->get(route('accounts.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('accounts.index'))->assertOk()->assertSee($student->email);
        $this->get(route('accounts.index'))->assertOk(); // still signed in as admin in this test
    }

    public function test_guest_cannot_open_account_pages(): void
    {
        $this->get(route('account.show'))->assertRedirect(route('login'));
        $this->get(route('accounts.index'))->assertRedirect(route('login'));
    }

    public function test_create_page_is_for_students_only(): void
    {
        $this->actingAs(User::factory()->create())->get(route('requests.create'))->assertOk()->assertSee('Submit request');
        $this->actingAs(User::factory()->admin()->create())->get(route('requests.create'))->assertForbidden();
    }
}
