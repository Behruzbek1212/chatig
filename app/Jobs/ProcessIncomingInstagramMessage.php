<?php

namespace App\Jobs;

use App\Agents\DTO\AgentContext;
use App\Agents\SalesAgent;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Auth\Ai\AiSettingsService;
use App\Services\Channels\InstagramService;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Inbound Instagram DM pipeline: resolve tenant + conversation, run the sales
 * agent, then reply (auto mode) or store a suggestion for the owner (suggest
 * mode). All OpenAI/Instagram calls happen here, off the webhook request.
 */
class ProcessIncomingInstagramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $instagramAccountId,
        public readonly string $senderId,
        public readonly string $text,
        public readonly ?string $messageId = null,
    ) {}

    public function handle(
        Tenancy $tenancy,
        SalesAgent $agent,
        AiSettingsService $aiSettings,
        InstagramService $instagram,
    ): void {
        $channel = Channel::withoutGlobalScope('store')
            ->where('type', 'instagram')
            ->where('external_id', $this->instagramAccountId)
            ->where('status', 'connected')
            ->first();

        if (! $channel) {
            return;
        }

        $store = $channel->store;
        $tenancy->set($store); // scope all subsequent model access to this store

        // Idempotency: ignore duplicate webhook deliveries.
        if ($this->messageId && Message::where('external_mid', $this->messageId)->exists()) {
            return;
        }

        $customer = Customer::firstOrCreate(
            ['store_id' => $store->id, 'channel' => 'instagram', 'external_id' => $this->senderId],
        );

        $conversation = Conversation::where('customer_id', $customer->id)
            ->whereNotIn('status', ['closed'])
            ->latest('id')
            ->first()
            ?? Conversation::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'channel' => 'instagram',
                'status' => 'open',
            ]);

        $history = $this->history($conversation);

        Message::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'role' => 'customer',
            'direction' => 'inbound',
            'content' => $this->text,
            'external_mid' => $this->messageId,
            'status' => 'sent',
        ]);

        $config = $aiSettings->current($store);

        $result = $agent->handle(new AgentContext(
            conversation: $conversation->load('store', 'customer'),
            userMessage: $this->text,
            history: $history,
            systemPrompt: $config?->system_prompt,
        ));

        $auto = ($config->mode ?? 'suggest') === 'auto' && ! $result->needsHuman;

        Message::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'role' => 'ai',
            'direction' => 'outbound',
            'content' => $result->reply,
            'agent_used' => 'sales',
            'tool_calls' => $result->toolCalls,
            'tokens' => $result->tokens,
            'status' => $auto ? 'sent' : 'suggested',
        ]);

        if ($auto) {
            $instagram->sendMessage($channel, $this->senderId, $result->reply);
        }

        $conversation->update([
            'last_message_at' => now(),
            'status' => $result->needsHuman ? 'needs_human' : 'ai_handling',
        ]);
    }

    /**
     * Prior turns as LLM messages (customer => user, ai/owner => assistant).
     *
     * @return array<int, array{role:string, content:string}>
     */
    private function history(Conversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', ['customer', 'ai', 'owner'])
            ->where('status', '!=', 'suggested')
            ->latest('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn (Message $m) => [
                'role' => $m->role === 'customer' ? 'user' : 'assistant',
                'content' => (string) $m->content,
            ])
            ->values()
            ->all();
    }
}
