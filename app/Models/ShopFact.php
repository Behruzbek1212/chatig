<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Database\Factories\ShopFactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopFact extends Model
{
    /** @use HasFactory<ShopFactFactory> */
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id', 'label', 'value', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }
}
