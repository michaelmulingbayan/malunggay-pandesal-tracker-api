<?php

use App\Models\User;
use App\UserRole;
use App\UserStatus;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'app.admin.name' => 'Head Admin',
        'app.admin.email' => 'admin@example.com',
        'app.admin.password' => 'secret-password',
    ]);
});

test('it creates an active admin from config', function () {
    $this->seed(AdminSeeder::class);

    $admin = User::sole();

    expect($admin)
        ->name->toBe('Head Admin')
        ->email->toBe('admin@example.com')
        ->role->toBe(UserRole::Admin)
        ->status->toBe(UserStatus::Active)
        ->and(Hash::check('secret-password', $admin->password))->toBeTrue();
});

test('the seeded admin can log in through the api', function () {
    $this->seed(AdminSeeder::class);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'admin@example.com',
        'password' => 'secret-password',
        'device_name' => 'Pixel 8',
    ])->assertOk()->assertJsonPath('user.role', 'admin');
});

test('re-seeding does not reset an existing admin password', function () {
    $this->seed(AdminSeeder::class);
    User::sole()->update(['password' => 'changed-password']);

    $this->seed(AdminSeeder::class);

    expect(User::count())->toBe(1)
        ->and(Hash::check('changed-password', User::sole()->password))->toBeTrue();
});

test('it fails when admin credentials are missing', function () {
    config(['app.admin.password' => null]);

    $this->seed(AdminSeeder::class);
})->throws(RuntimeException::class);
