<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'type' => 'instagram',
            'external_id' => (string) fake()->unique()->numerify('##########'),
            'username' => fake()->userName(),
            'access_token' => 'token-'.fake()->uuid(),
            'status' => 'connected',
        ];
    }

    public function disconnected(): static
    {
        return $this->state(fn () => ['status' => 'disconnected']);
    }
}
