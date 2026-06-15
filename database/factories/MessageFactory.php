<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'conversation_id' => Conversation::factory(),
            'role' => 'customer',
            'direction' => 'inbound',
            'content' => fake()->sentence(),
            'status' => 'sent',
        ];
    }
}
