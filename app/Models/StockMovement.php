<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $type
 * @property int $qty_change
 * @property string|null $reason
 * @property Carbon|null $created_at
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['product_id', 'type', 'qty_change', 'reason'];

    protected function casts(): array
    {
        return [
            'qty_change' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
