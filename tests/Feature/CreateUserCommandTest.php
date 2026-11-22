<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_account_with_hashed_password_and_role(): void
    {
        $this->artisan('app:create-user', ['email' => 'New.Person@Example.edu', '--name' => 'New Person', '--role' => 'admin'])
            ->expectsQuestion('Password', 'Example123')
            ->assertSuccessful();

        $user = User::where('email', 'new.person@example.edu')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertNotSame('Example123', $user->password);
        $this->assertTrue(Hash::check('Example123', $user->password));
    }

    public function test_rejects_weak_password_and_unknown_role(): void
    {
        $this->artisan('app:create-user', ['email' => 'x@example.edu', '--name' => 'X', '--role' => 'superuser'])
            ->expectsQuestion('Password', 'short')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'x@example.edu']);
    }

    public function test_rerunning_updates_the_same_account(): void
    {
        User::factory()->create(['email' => 'same@example.edu', 'name' => 'Old Name']);

        $this->artisan('app:create-user', ['email' => 'same@example.edu', '--name' => 'New Name', '--role' => 'student'])
            ->expectsQuestion('Password', 'Example123')
            ->assertSuccessful();

        $this->assertSame(1, User::where('email', 'same@example.edu')->count());
        $this->assertSame('New Name', User::where('email', 'same@example.edu')->value('name'));
    }
}
