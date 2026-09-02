<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class OwnershipEligibilityReconciliation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['claimed_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }
}
