<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DocumentGovernanceNotificationProjectionReceipt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
    }
}
