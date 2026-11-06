<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    protected $table = 'ai_usage';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'cost_usd' => 'float',
            'units' => 'float',
        ];
    }
}
