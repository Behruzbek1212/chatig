<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(50_000, 20_000_000),
            'quantity' => fake()->numberBetween(0, 50),
            'category' => fake()->randomElement(['noutbuk', 'telefon', 'aksessuar']),
            'condition' => 'new',
            'brand' => fake()->randomElement(['Asus', 'Apple', 'Samsung']),
            'status' => 'active',
        ];
    }
}
