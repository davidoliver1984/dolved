<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GovernanceEmailEnvelopeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DocumentGovernanceEmailEnvelope extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assembly_status' => GovernanceEmailEnvelopeStatus::class,
            'digest_date' => 'immutable_date',
            'sealed_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'terminal_at' => 'immutable_datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(DocumentGovernanceEmailEnvelopeMember::class, 'envelope_id')->orderBy('ordinal');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(DocumentGovernanceEmailEnvelopeAttempt::class, 'envelope_id')->orderBy('generation');
    }
}
