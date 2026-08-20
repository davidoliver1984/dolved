<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceUsageEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'decimal:3',
            'cost_usd' => 'decimal:8',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
