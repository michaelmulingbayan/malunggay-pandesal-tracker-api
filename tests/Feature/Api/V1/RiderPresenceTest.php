<?php

use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\User;
use App\RiderAvailabilityStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->rider = Rider::factory()->create();
});

test('a rider marks themselves unavailable and available again', function () {
    Sanctum::actingAs($this->rider->user);

    $this->patchJson(route('api.v1.me.availability.update'), ['is_available' => false])
        ->assertOk()
        ->assertJsonPath('data.is_available', false);
    expect($this->rider->fresh()->availability_status)->toBe(RiderAvailabilityStatus::Unavailable);

    $this->patchJson(route('api.v1.me.availability.update'), ['is_available' => true])
        ->assertOk()
        ->assertJsonPath('data.is_available', true);
});

test('availability is required and must be true or false', function () {
    Sanctum::actingAs($this->rider->user);

    $this->patchJson(route('api.v1.me.availability.update'), ['is_available' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_available');
});

test('a rider shares a location and it shows as their last location', function () {
    Sanctum::actingAs($this->rider->user);
    RiderLocation::factory()->for($this->rider)->create(['recorded_at' => now()->subHour()]);

    $this->postJson(route('api.v1.me.locations.store'), ['latitude' => 14.5995, 'longitude' => 120.9842])
        ->assertCreated()
        ->assertJsonPath('data.latitude', 14.5995)
        ->assertJsonPath('data.longitude', 120.9842);

    $this->getJson(route('api.v1.me.stock'))
        ->assertOk()
        ->assertJsonPath('data.last_location.latitude', 14.5995)
        ->assertJsonPath('data.last_location.longitude', 120.9842);

    expect($this->rider->locations()->count())->toBe(2);
});

test('coordinates must be valid', function (array $payload) {
    Sanctum::actingAs($this->rider->user);

    $this->postJson(route('api.v1.me.locations.store'), $payload)->assertUnprocessable();

    expect(RiderLocation::count())->toBe(0);
})->with([
    'latitude too high' => [['latitude' => 91, 'longitude' => 120]],
    'longitude too low' => [['latitude' => 14, 'longitude' => -181]],
    'missing longitude' => [['latitude' => 14]],
    'not numbers' => [['latitude' => 'north', 'longitude' => 'east']],
]);

test('last location is null before anything is shared', function () {
    Sanctum::actingAs($this->rider->user);

    $this->getJson(route('api.v1.me.stock'))->assertOk()->assertJsonPath('data.last_location', null);
});

test('admins without a rider profile cannot set availability or share a location', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->patchJson(route('api.v1.me.availability.update'), ['is_available' => true])->assertForbidden();
    $this->postJson(route('api.v1.me.locations.store'), ['latitude' => 14, 'longitude' => 121])->assertForbidden();
});
