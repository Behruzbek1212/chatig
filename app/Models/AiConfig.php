<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $store_id
 * @property string $system_prompt
 * @property string $mode
 * @property array|null $working_hours
 * @property array|null $raw_inputs
 * @property int $version
 * @property bool $is_active
 */
class AiConfig extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id', 'system_prompt', 'mode', 'working_hours', 'raw_inputs', 'version', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'working_hours' => 'array',
            'raw_inputs' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }
}
