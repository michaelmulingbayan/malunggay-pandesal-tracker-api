<?php

namespace Database\Factories;

use App\Models\Rider;
use App\Models\RiderLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiderLocation>
 */
class RiderLocationFactory extends Factory
{
    /**
     * Define the model's default state (somewhere in Metro Manila).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rider_id' => Rider::factory(),
            'latitude' => fake()->latitude(14.40, 14.75),
            'longitude' => fake()->longitude(120.95, 121.15),
            'recorded_at' => now(),
        ];
    }
}
