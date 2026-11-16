<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use App\UserStatus;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Create the initial admin account from the ADMIN_* environment values.
     *
     * Existing accounts are left untouched so re-seeding never resets a password.
     */
    public function run(): void
    {
        $email = config('app.admin.email');
        $password = config('app.admin.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in your .env file before running the AdminSeeder.');
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => config('app.admin.name'),
                'password' => $password,
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
    }
}
