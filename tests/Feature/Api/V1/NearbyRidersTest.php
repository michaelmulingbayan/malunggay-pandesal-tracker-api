<?php

use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\User;
use App\RiderAvailabilityStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('anyone can see available riders who shared a location recently, first name only', function () {
    $juan = Rider::factory()->for(User::factory()->state(['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com']))->create();
    RiderLocation::factory()->for($juan)->create(['latitude' => 14.5995, 'longitude' => 120.9842, 'recorded_at' => now()->subMinutes(5)]);

    $response = $this->getJson(route('api.v1.public.riders.nearby'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.first_name', 'Juan')
        ->assertJsonPath('data.0.latitude', 14.5995)
        ->assertJsonPath('data.0.longitude', 120.9842);

    // No contact details or full name leak to the public.
    expect($response->getContent())
        ->not->toContain('Dela Cruz')
        ->not->toContain('juan@example.com')
        ->not->toContain($juan->phone_number);
});

test('unavailable, inactive, stale and never-shared riders are hidden', function () {
    $unavailable = Rider::factory()->create(['availability_status' => RiderAvailabilityStatus::Unavailable]);
    RiderLocation::factory()->for($unavailable)->create();

    $inactive = Rider::factory()->inactive()->create();
    RiderLocation::factory()->for($inactive)->create();

    $stale = Rider::factory()->create();
    RiderLocation::factory()->for($stale)->create(['recorded_at' => now()->subHours(2)]);

    Rider::factory()->create(); // never shared

    $this->getJson(route('api.v1.public.riders.nearby'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('only the latest location counts as fresh', function () {
    $rider = Rider::factory()->create();
    RiderLocation::factory()->for($rider)->create(['recorded_at' => now()->subMinutes(10)]);
    RiderLocation::factory()->for($rider)->create(['latitude' => 14.7, 'recorded_at' => now()->subMinutes(2)]);

    $this->getJson(route('api.v1.public.riders.nearby'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.latitude', 14.7);
});

test('the endpoint is rate limited', function () {
    foreach (range(1, 30) as $attempt) {
        $this->getJson(route('api.v1.public.riders.nearby'))->assertOk();
    }

    $this->getJson(route('api.v1.public.riders.nearby'))->assertTooManyRequests();
});
