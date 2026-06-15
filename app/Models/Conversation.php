<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $store_id
 * @property int|null $customer_id
 * @property string $channel
 * @property string $status
 * @property string $mode
 * @property Carbon|null $last_message_at
 * @property-read Store $store
 * @property-read Customer|null $customer
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use BelongsToStore, HasFactory;

    protected $fillable = ['store_id', 'customer_id', 'channel', 'status', 'mode', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }
}
