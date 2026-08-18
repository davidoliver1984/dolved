<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatStreamEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ChatDeliveryEvent extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (ChatDeliveryEvent $event): void {
            $run = GenerationRun::query()->find($event->generation_run_id);
            if (! $run instanceof GenerationRun || $run->workspace_id !== $event->workspace_id) {
                throw new LogicException('Chat delivery event tenancy is inconsistent.');
            }
        });
        static::updating(fn (): never => throw new LogicException('Chat delivery events are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'event_type' => ChatStreamEventType::class,
            'provisional' => 'boolean',
            'safe_payload' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(GenerationRun::class);
    }
}
