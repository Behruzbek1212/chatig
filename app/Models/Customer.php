<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToStore, HasFactory;

    protected $fillable = ['store_id', 'channel', 'external_id', 'name', 'phone'];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
