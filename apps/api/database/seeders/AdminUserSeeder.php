<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a single admin user.
     *
     * Auth uses the Better Auth shape: the `user` row holds the profile/role and
     * the matching `account` row (providerId = "credential") holds the bcrypt
     * password Fortify reads. We create/refresh both. Idempotent — safe to run
     * repeatedly; it keys off the email so re-seeding won't duplicate or error.
     *
     * Credentials are taken from env so they aren't hard-coded for real
     * deployments, with dev-friendly defaults:
     *   ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD
     */
    public function run(): void
    {
        $name = env('ADMIN_NAME', 'Admin');
        $email = env('ADMIN_EMAIL', 'admin@helpdesk.test');
        $password = env('ADMIN_PASSWORD', 'password');

        // A user is either 'admin' or 'agent' (column defaults to 'agent').
        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => 'admin',
                'emailVerified' => true,
                'deletedAt' => null,
            ],
        );

        // Upsert the credential account that stores the password hash.
        $user->accounts()->updateOrCreate(
            ['providerId' => 'credential'],
            [
                'accountId' => $user->id,
                'password' => Hash::make($password),
            ],
        );

        $this->command?->info("Admin user ready: {$email} (role: admin)");
    }
}
