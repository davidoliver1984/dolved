<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_id',
    'workspace_id',
    'document_id',
    'action',
    'outcome',
    'context',
    'occurred_at',
])]
class IngestionAuditEvent extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
