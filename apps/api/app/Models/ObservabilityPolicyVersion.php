<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ObservabilityPolicyVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['desired_values' => 'array'];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(ObservabilityPolicyTarget::class, 'policy_version_id');
    }
}
