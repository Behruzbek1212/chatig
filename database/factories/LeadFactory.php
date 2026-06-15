<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'city' => fake()->city(),
            'phone' => '+998'.fake()->unique()->numerify('#########'),
            'status' => 'new',
            'source' => 'telegram',
        ];
    }
}
