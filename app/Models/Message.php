<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $store_id
 * @property int $conversation_id
 * @property string $role
 * @property string $direction
 * @property string|null $content
 * @property string|null $agent_used
 * @property array|null $tool_calls
 * @property int $tokens
 * @property string|null $external_mid
 * @property string $status
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id', 'conversation_id', 'role', 'direction', 'content',
        'agent_used', 'tool_calls', 'tokens', 'external_mid', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'tokens' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
