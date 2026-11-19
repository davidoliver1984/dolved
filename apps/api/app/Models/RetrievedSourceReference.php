<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RetrievedSourceReference extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['safe_metadata' => 'array', 'score' => 'decimal:12'];
    }

    public function generatedAnswer(): BelongsTo
    {
        return $this->belongsTo(GeneratedAnswer::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
