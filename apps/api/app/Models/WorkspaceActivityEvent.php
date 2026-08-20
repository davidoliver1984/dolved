<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceActivityEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}
