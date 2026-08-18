<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationRunFailureCode;
use App\Enums\GenerationRunStatus;
use App\Enums\MessageRole;
use App\Enums\RetrievalOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class GenerationRun extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (GenerationRun $run): void {
            $conversation = Conversation::query()->find($run->conversation_id);
            $message = Message::query()->find($run->user_message_id);
            if (! $conversation instanceof Conversation || ! $message instanceof Message
                || $conversation->workspace_id !== $run->workspace_id
                || $message->workspace_id !== $run->workspace_id
                || $message->conversation_id !== $run->conversation_id
                || $message->role !== MessageRole::User) {
                throw new LogicException('Generation run tenancy and message lineage are inconsistent.');
            }
            if ($run->retry_of_run_id !== null) {
                $original = GenerationRun::query()->find($run->retry_of_run_id);
                if (! $original instanceof GenerationRun
                    || $original->workspace_id !== $run->workspace_id
                    || $original->conversation_id !== $run->conversation_id
                    || $original->user_message_id !== $run->user_message_id) {
                    throw new LogicException('Generation retry lineage is inconsistent.');
                }
            }
        });

        static::updating(function (GenerationRun $run): void {
            if ($run->isDirty(['public_id', 'workspace_id', 'conversation_id', 'user_message_id', 'retry_of_run_id', 'correlation_id'])) {
                throw new LogicException('Generation run identity and lineage are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => GenerationRunStatus::class,
            'failure_code' => GenerationRunFailureCode::class,
            'retrieval_outcome' => RetrievalOutcome::class,
            'usage' => 'array',
            'started_at' => 'immutable_datetime',
            'first_progress_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancellation_requested_at' => 'immutable_datetime',
            'cancellation_acknowledged_at' => 'immutable_datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'user_message_id');
    }

    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'assistant_message_id');
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_run_id');
    }

    public function contextualisationResult(): HasOne
    {
        return $this->hasOne(ContextualisationResultSnapshot::class);
    }

    public function retrievalOutcomeSnapshot(): HasOne
    {
        return $this->hasOne(RetrievalOutcomeSnapshot::class);
    }

    public function generatedAnswer(): HasOne
    {
        return $this->hasOne(GeneratedAnswer::class);
    }
}
