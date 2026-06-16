<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id', 'public_id', 'customer_id', 'conversation_id',
        'customer_name', 'customer_phone', 'customer_address',
        'status', 'total', 'source', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->public_id ??= (string) Str::ulid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
