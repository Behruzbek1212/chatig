<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'channel' => 'telegram',
            'external_id' => (string) fake()->unique()->numerify('#########'),
            'name' => fake()->name(),
            'phone' => null,
        ];
    }
}
