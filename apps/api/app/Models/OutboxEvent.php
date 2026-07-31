<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OutboxEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'event_id',
    'event_type',
    'event_version',
    'workspace_public_id',
    'document_public_id',
    'correlation_id',
    'traceparent',
    'tracestate',
    'payload',
    'occurred_at',
    'published_at',
    'failed_at',
    'claimed_at',
    'claim_token',
    'attempt_count',
    'next_attempt_at',
    'last_error',
])]
class OutboxEvent extends Model
{
    /** @use HasFactory<OutboxEventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (OutboxEvent $event): void {
            if (
                $event->exists
                && $event->isDirty([
                    'event_id',
                    'event_type',
                    'event_version',
                    'workspace_public_id',
                    'document_public_id',
                    'correlation_id',
                    'traceparent',
                    'tracestate',
                    'payload',
                    'occurred_at',
                ])
            ) {
                throw new LogicException(
                    'Outbox event identity and payload are immutable.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_version' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'attempt_count' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
        ];
    }
}
