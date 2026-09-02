<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class UserDisablementReconciliationSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'disabled_at' => 'immutable_datetime',
            'cursor_membership_id' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
