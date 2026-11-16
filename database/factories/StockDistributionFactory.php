<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\Rider;
use App\Models\StockDistribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockDistribution>
 */
class StockDistributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_id' => Production::factory(),
            'rider_id' => Rider::factory(),
            'quantity_distributed' => fake()->numberBetween(50, 200),
            'distribution_date' => today(),
            'distributed_by' => User::factory()->admin(),
        ];
    }
}
