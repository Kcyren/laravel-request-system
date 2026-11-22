<?php

namespace Database\Seeders;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Trusted setup for the fictional lab accounts (Labs 3, 4 and 8).
 *
 * - Idempotent: running it again updates the same accounts instead of
 *   failing or creating duplicates, and never deletes existing requests.
 * - The administrator role is assigned here (trusted setup), never from
 *   user input.
 * - Outside local/testing, passwords MUST come from runtime environment
 *   variables (SEED_STUDENT_PASSWORD, SEED_ADMIN_PASSWORD), so no staging
 *   password is stored in Git.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $studentPassword = $this->password('SEED_STUDENT_PASSWORD', 'Password123!');
        $adminPassword = $this->password('SEED_ADMIN_PASSWORD', 'AdminPass123!');

        $studentA = $this->account('studenta@example.edu', 'Student A (Alex Reyes)', 'student', $studentPassword);
        $studentB = $this->account('studentb@example.edu', 'Student B (Bianca Cruz)', 'student', $studentPassword);
        $this->account('admin@example.edu', 'System Administrator', 'admin', $adminPassword);

        $this->sampleRequest($studentA, 'USB-C Docking Station', 1, 'Needed for capstone presentation setup.');
        $this->sampleRequest($studentB, 'Cat6 Ethernet Patch Cable (10m)', 2, 'Networking lab workstation connection.');
    }

    private function password(string $envKey, string $localDefault): string
    {
        $value = env($envKey);

        if (filled($value)) {
            return $value;
        }

        if (app()->environment(['local', 'testing'])) {
            return $localDefault;
        }

        throw new RuntimeException("Set {$envKey} in the runtime environment before seeding a non-local environment.");
    }

    private function account(string $email, string $name, string $role, string $password): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => $password, // hashed by the User "hashed" cast
            'role' => $role,
        ])->save();

        return $user;
    }

    private function sampleRequest(User $owner, string $item, int $quantity, string $purpose): void
    {
        ServiceRequest::firstOrCreate(
            ['user_id' => $owner->id, 'item_name' => $item],
            [
                'requester_name' => $owner->name,
                'requester_email' => $owner->email,
                'quantity' => $quantity,
                'purpose' => $purpose,
                'status' => 'pending',
            ],
        );
    }
}
