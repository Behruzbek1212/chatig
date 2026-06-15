<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id', 'customer_id', 'conversation_id',
        'first_name', 'last_name', 'city', 'phone', 'status', 'source', 'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
