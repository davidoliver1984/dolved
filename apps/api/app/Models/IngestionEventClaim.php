<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IngestionEventClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'event_id',
    'workspace_public_id',
    'document_public_id',
    'correlation_id',
    'payload_sha256',
    'claimed_at',
])]
class IngestionEventClaim extends Model
{
    /** @use HasFactory<IngestionEventClaimFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'A durable ingestion event claim is immutable.',
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_at' => 'immutable_datetime',
        ];
    }
}
