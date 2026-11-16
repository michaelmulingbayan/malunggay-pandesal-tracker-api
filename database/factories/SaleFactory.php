<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\StockDistribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(10, 100);
        $price = 3.00;

        return [
            'distribution_id' => StockDistribution::factory(),
            'quantity_sold' => $quantity,
            'price_per_piece' => $price,
            'total_amount' => $quantity * $price,
            'sale_date' => now(),
        ];
    }
}
