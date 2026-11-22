<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Trusted setup: create or update an account from the server console.
 *
 * Roles (including admin) can only be assigned here or in the seeder,
 * never through the web interface (Laboratory 3).
 *
 * The password is read from the NEW_USER_PASSWORD environment variable or
 * a hidden prompt - never from a command-line argument (which would land in
 * shell history) - and is stored only as a hash.
 *
 *   php artisan app:create-user someone@example.edu --name="Full Name" --role=student
 */
class CreateUser extends Command
{
    protected $signature = 'app:create-user
        {email : Login email address}
        {--name= : Full name}
        {--role=student : student or admin}';

    protected $description = 'Create or update a user account (trusted setup)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $name = $this->option('name') ?: $this->ask('Full name');
        $role = $this->option('role');
        $password = getenv('NEW_USER_PASSWORD') ?: $this->secret('Password');

        $validator = Validator::make(compact('email', 'name', 'role', 'password'), [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:student,admin'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $existed = $user->exists;

        $user->forceFill([
            'name' => $name,
            'password' => $password, // hashed by the User "hashed" cast
            'role' => $role,
        ])->save();

        $this->info(($existed ? 'Updated' : 'Created')." {$role} account #{$user->id} for {$email}.");

        return self::SUCCESS;
    }
}
