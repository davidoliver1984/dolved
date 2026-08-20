<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ObservabilityPolicyTarget extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'desired_value' => 'array',
            'observed_value' => 'array',
            'reconciled_at' => 'immutable_datetime',
        ];
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(ObservabilityPolicyVersion::class, 'policy_version_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ObservabilityDeploymentAttempt::class, 'policy_target_id');
    }
}
