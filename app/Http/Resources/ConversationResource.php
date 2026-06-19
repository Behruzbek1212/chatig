<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Support\ConversationStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->customer;
        $last = $this->relationLoaded('messages')
            ? $this->messages->last()
            : $this->messages()->reorder('id', 'desc')->first();

        $unread = 0;
        $cutoff = $this->last_read_at;
        $unreadQuery = $this->messages()->where('direction', 'inbound');
        if ($cutoff) {
            $unreadQuery->where('created_at', '>', $cutoff);
        }
        $unread = $unreadQuery->count();

        return [
            'id' => $this->id,
            'source' => $this->channel,
            'status' => $this->status,
            'mode' => $this->mode,
            'stage' => ConversationStage::for($this->resource),
            'customer_id' => $this->customer_id,
            'customer_name' => $customer?->name,
            'username' => $customer?->name,
            'phone' => $customer?->phone,
            'last_message' => $last?->content,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => $unread,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
