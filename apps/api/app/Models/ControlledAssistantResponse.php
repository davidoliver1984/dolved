<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ControlledAssistantResponse extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $response): void {
            $run = GenerationRun::query()->find($response->generation_run_id);
            $message = Message::query()->find($response->message_id);
            if (! $run instanceof GenerationRun || ! $message instanceof Message
                || $run->workspace_id !== $response->workspace_id
                || $message->workspace_id !== $response->workspace_id
                || $message->conversation_id !== $run->conversation_id
                || $message->role !== MessageRole::Assistant
                || $message->kind !== $response->response_kind) {
                throw new LogicException('Controlled response tenancy and message lineage are inconsistent.');
            }
            if ($response->retrieval_outcome_snapshot_id !== null) {
                $snapshot = RetrievalOutcomeSnapshot::query()->find($response->retrieval_outcome_snapshot_id);
                if (! $snapshot instanceof RetrievalOutcomeSnapshot
                    || $snapshot->generation_run_id !== $run->id
                    || $snapshot->workspace_id !== $response->workspace_id) {
                    throw new LogicException('Controlled response retrieval lineage is inconsistent.');
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['response_kind' => MessageKind::class];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(GenerationRun::class);
    }

    public function retrievalOutcomeSnapshot(): BelongsTo
    {
        return $this->belongsTo(RetrievalOutcomeSnapshot::class);
    }
}
