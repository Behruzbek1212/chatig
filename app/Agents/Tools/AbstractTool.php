<?php

namespace App\Agents\Tools;

use App\Agents\Tools\Contracts\Tool;

abstract class AbstractTool implements Tool
{
    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->parameters(),
            ],
        ];
    }
}
