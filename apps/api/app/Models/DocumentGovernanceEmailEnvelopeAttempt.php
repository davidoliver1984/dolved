<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GovernanceEmailAttemptStatus;
use Illuminate\Database\Eloquent\Model;

final class DocumentGovernanceEmailEnvelopeAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => GovernanceEmailAttemptStatus::class,
            'lease_expires_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
