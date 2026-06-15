<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'customer_id' => Customer::factory(),
            'channel' => 'telegram',
            'status' => 'open',
            'mode' => 'suggest',
            'last_message_at' => now(),
        ];
    }
}
