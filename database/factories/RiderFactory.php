<?php

namespace Database\Factories;

use App\Models\Rider;
use App\Models\User;
use App\RiderAvailabilityStatus;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rider>
 */
class RiderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Rider]),
            'phone_number' => fake()->numerify('09#########'),
            'address' => fake()->address(),
            'availability_status' => RiderAvailabilityStatus::Available,
        ];
    }

    /**
     * Indicate that the rider's user account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->inactive()->state(['role' => UserRole::Rider]),
        ]);
    }
}
