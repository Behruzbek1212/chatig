<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property int $position
 * @property bool $is_primary
 * @property-read Product $product
 */
class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'position', 'is_primary'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return Storage::disk(config('chatig.media_disk'))->url($this->path);
    }
}
