<?php

use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\User;
use App\RiderAvailabilityStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin sees each active rider\'s latest location, most recent first', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $ana = Rider::factory()->for(User::factory()->state(['name' => 'Ana']))->create();
    RiderLocation::factory()->for($ana)->create(['latitude' => 14.1, 'longitude' => 121.1, 'recorded_at' => now()->subHour()]);
    RiderLocation::factory()->for($ana)->create(['latitude' => 14.5, 'longitude' => 121.0, 'recorded_at' => now()->subMinutes(5)]);

    $ben = Rider::factory()->for(User::factory()->state(['name' => 'Ben']))->create([
        'availability_status' => RiderAvailabilityStatus::Unavailable,
    ]);
    RiderLocation::factory()->for($ben)->create(['latitude' => 14.6, 'longitude' => 120.9, 'recorded_at' => now()]);

    Rider::factory()->create(); // never shared a location
    $inactive = Rider::factory()->inactive()->create();
    RiderLocation::factory()->for($inactive)->create();

    $this->getJson(route('api.v1.riders.locations'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Ben')
        ->assertJsonPath('data.0.is_available', false)
        ->assertJsonPath('data.1.name', 'Ana')
        ->assertJsonPath('data.1.latitude', 14.5)
        ->assertJsonPath('data.1.longitude', 121)
        ->assertJsonPath('data.1.is_available', true);
});

test('riders cannot see other riders\' locations', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Rider]));

    $this->getJson(route('api.v1.riders.locations'))->assertForbidden();
});
