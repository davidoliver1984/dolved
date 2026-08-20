<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ObservabilityDeploymentAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'observed_value' => 'array',
            'applied_at' => 'immutable_datetime',
            'derived_state_applied' => 'boolean',
        ];
    }
}
