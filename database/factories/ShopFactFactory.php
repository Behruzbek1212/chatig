<?php

namespace Database\Factories;

use App\Models\ShopFact;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopFact>
 */
class ShopFactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'label' => fake()->randomElement(['Manzil', 'Telefon', 'Ish vaqti', 'Yetkazib berish']),
            'value' => fake()->sentence(),
            'display_order' => 0,
        ];
    }
}
