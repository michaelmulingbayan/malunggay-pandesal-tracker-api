<?php

namespace Database\Factories;

use App\Models\StockDistribution;
use App\Models\StockReturn;
use App\Models\User;
use App\StockReturnStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockReturn>
 */
class StockReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'distribution_id' => StockDistribution::factory(),
            'quantity_returned' => fake()->numberBetween(5, 40),
            'return_date' => now(),
            'status' => StockReturnStatus::Pending,
            'verified_by' => null,
        ];
    }

    /**
     * Indicate that an admin has verified the return.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StockReturnStatus::Verified,
            'verified_by' => User::factory()->admin(),
        ]);
    }

    /**
     * Indicate that the return was rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StockReturnStatus::Rejected,
            'verified_by' => User::factory()->admin(),
        ]);
    }
}
