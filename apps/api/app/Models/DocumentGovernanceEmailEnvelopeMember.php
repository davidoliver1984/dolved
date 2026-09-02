<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class DocumentGovernanceEmailEnvelopeMember extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['added_at' => 'immutable_datetime'];
    }

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(DocumentGovernanceEmailEnvelope::class, 'envelope_id');
    }

    public function decision(): HasOne
    {
        return $this->hasOne(DocumentGovernanceEmailEnvelopeMemberDecision::class, 'envelope_member_id');
    }
}
