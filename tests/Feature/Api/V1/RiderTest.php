<?php

use App\Models\Rider;
use App\Models\User;
use App\UserRole;
use App\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function validRiderPayload(array $overrides = []): array
{
    return [
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'password' => 'secret-password',
        'phone_number' => '09171234567',
        'address' => 'Brgy. San Isidro, Quezon City',
        ...$overrides,
    ];
}

describe('listing riders', function () {
    test('admin sees all riders sorted by name with their active status', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        Rider::factory()->for(User::factory()->state(['name' => 'Zed Rider']))->create();
        Rider::factory()->inactive()->create();
        Rider::factory()->for(User::factory()->state(['name' => 'Ana Rider']))->create();

        $response = $this->getJson(route('api.v1.riders.index'))->assertOk();

        expect($response->json('data'))->toHaveCount(3)
            ->and($response->json('data.0.name'))->toBe('Ana Rider')
            ->and($response->json('data.2.name'))->toBe('Zed Rider')
            ->and(collect($response->json('data'))->pluck('is_active')->filter()->count())->toBe(2);
    });

    test('riders cannot list riders', function () {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));

        $this->getJson(route('api.v1.riders.index'))->assertForbidden();
    });

    test('guests cannot list riders', function () {
        $this->getJson(route('api.v1.riders.index'))->assertUnauthorized();
    });
});

describe('adding a rider', function () {
    test('admin creates a rider with a login account', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.riders.store'), validRiderPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Juan Dela Cruz')
            ->assertJsonPath('data.phone_number', '09171234567')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'juan@example.com')->sole();

        expect($user)
            ->role->toBe(UserRole::Rider)
            ->status->toBe(UserStatus::Active)
            ->and(Hash::check('secret-password', $user->password))->toBeTrue()
            ->and($user->rider)->address->toBe('Brgy. San Isidro, Quezon City');
    });

    test('the new rider can log in', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson(route('api.v1.riders.store'), validRiderPayload())->assertCreated();

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'juan@example.com',
            'password' => 'secret-password',
            'device_name' => 'Rider phone',
        ])->assertOk()->assertJsonPath('user.role', 'rider');
    });

    test('email must be unique', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory()->create(['email' => 'juan@example.com']);

        $this->postJson(route('api.v1.riders.store'), validRiderPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        expect(Rider::count())->toBe(0);
    });

    test('all rider fields are required', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.riders.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'phone_number', 'address']);
    });

    test('password must be at least 8 characters', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.riders.store'), validRiderPayload(['password' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    });

    test('riders cannot add riders', function () {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));

        $this->postJson(route('api.v1.riders.store'), validRiderPayload())->assertForbidden();

        expect(User::where('email', 'juan@example.com')->exists())->toBeFalse();
    });
});

describe('toggling rider status', function () {
    test('admin deactivates a rider and their tokens are revoked', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $rider = Rider::factory()->create();
        $rider->user->createToken('Rider phone');

        $this->patchJson(route('api.v1.riders.status.update', $rider), ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect($rider->user->fresh()->status)->toBe(UserStatus::Inactive)
            ->and($rider->user->tokens()->count())->toBe(0);
    });

    test('admin reactivates a rider', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $rider = Rider::factory()->inactive()->create();

        $this->patchJson(route('api.v1.riders.status.update', $rider), ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        expect($rider->user->fresh()->status)->toBe(UserStatus::Active);
    });

    test('a deactivated rider cannot log in', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $rider = Rider::factory()->create();

        $this->patchJson(route('api.v1.riders.status.update', $rider), ['is_active' => false])->assertOk();

        $this->postJson(route('api.v1.auth.login'), [
            'email' => $rider->user->email,
            'password' => 'password',
            'device_name' => 'Rider phone',
        ])->assertForbidden();
    });

    test('is_active is required', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $rider = Rider::factory()->create();

        $this->patchJson(route('api.v1.riders.status.update', $rider), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_active');
    });

    test('riders cannot change rider status', function () {
        $rider = Rider::factory()->create();
        Sanctum::actingAs($rider->user);

        $this->patchJson(route('api.v1.riders.status.update', $rider), ['is_active' => false])->assertForbidden();

        expect($rider->user->fresh()->status)->toBe(UserStatus::Active);
    });

    test('unknown rider returns not found', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson(route('api.v1.riders.status.update', 999), ['is_active' => false])->assertNotFound();
    });
});
