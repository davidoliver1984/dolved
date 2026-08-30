<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentFamilyActivitySummary extends Model
{
    protected $table = 'document_family_activity_summary';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_meaningful_update' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DocumentFamily, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(DocumentFamily::class, 'family_id');
    }
}
