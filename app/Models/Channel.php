<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $store_id
 * @property string $type
 * @property string|null $external_id
 * @property string|null $username
 * @property string|null $access_token
 * @property string $status
 * @property array|null $meta
 * @property Carbon|null $token_expires_at
 * @property-read Store $store
 */
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id', 'type', 'external_id', 'username', 'access_token',
        'status', 'meta', 'token_expires_at',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'meta' => 'array',
            'token_expires_at' => 'datetime',
        ];
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
