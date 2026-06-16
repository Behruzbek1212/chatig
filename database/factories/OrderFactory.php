<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'public_id' => (string) Str::ulid(),
            'customer_name' => fake()->name(),
            'customer_phone' => '+99890'.fake()->numerify('#######'),
            'customer_address' => fake()->address(),
            'status' => 'new',
            'total' => fake()->numberBetween(50_000, 5_000_000),
            'source' => 'telegram_mini_app',
        ];
    }
}
