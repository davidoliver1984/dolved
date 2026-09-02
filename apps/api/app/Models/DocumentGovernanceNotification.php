<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentGovernanceEventKey;
use Illuminate\Database\Eloquent\Model;

final class DocumentGovernanceNotification extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event_key' => DocumentGovernanceEventKey::class,
            'parameters' => 'array',
            'read_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
