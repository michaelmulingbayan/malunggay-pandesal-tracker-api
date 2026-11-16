<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Production>
 */
class ProductionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_date' => today(),
            'quantity_produced' => fake()->numberBetween(200, 1500),
            'remarks' => fake()->optional()->sentence(),
            'recorded_by' => User::factory()->admin(),
        ];
    }

    /**
     * Indicate that the production happened a given number of days ago.
     */
    public function daysAgo(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'production_date' => today()->subDays($days),
        ]);
    }
}
