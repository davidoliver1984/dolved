<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentGovernanceEventKey;
use Illuminate\Database\Eloquent\Model;

final class DocumentGovernanceEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event_key' => DocumentGovernanceEventKey::class,
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
        ];
    }
}
