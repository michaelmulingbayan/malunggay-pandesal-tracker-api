<?php

namespace Database\Factories;

use App\Models\PandesalPrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PandesalPrice>
 */
class PandesalPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_per_piece' => fake()->randomElement([2.50, 3.00, 3.50, 4.00]),
            'set_by' => User::factory()->admin(),
        ];
    }
}
