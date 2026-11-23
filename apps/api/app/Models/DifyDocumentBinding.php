<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DifyDocumentBinding extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'synced_at' => 'immutable_datetime',
            'indexed_segment_count' => 'integer',
            'segment_count_observed_at' => 'immutable_datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
