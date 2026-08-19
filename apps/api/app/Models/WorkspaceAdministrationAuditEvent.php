<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceAdministrationAuditEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
