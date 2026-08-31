<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LegacyUploadInitializationGate extends Model
{
    protected $table = 'legacy_upload_initialization_gate';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'closed' => 'boolean',
            'inventory_cursor_id' => 'integer',
            'total_marked_count' => 'integer',
            'closed_at' => 'immutable_datetime',
            'drain_closed_at' => 'immutable_datetime',
        ];
    }
}
