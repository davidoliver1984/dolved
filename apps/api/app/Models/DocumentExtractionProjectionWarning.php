<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['projection_generation_id', 'ordinal', 'payload'])]
class DocumentExtractionProjectionWarning extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Extraction projection warnings are immutable.'));
    }

    protected function casts(): array
    {
        return ['ordinal' => 'integer', 'payload' => 'array'];
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(DocumentExtractionProjectionGeneration::class, 'projection_generation_id');
    }
}
