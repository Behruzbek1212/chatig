<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'business_type' => fake()->randomElement(config('chatig.business_types')),
            'status' => 'active',
            'trial_ends_at' => now()->addDays(config('chatig.trial_days')),
        ];
    }
}
