<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Message extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Message $message): void {
            $conversation = Conversation::query()->find($message->conversation_id);
            if (! $conversation instanceof Conversation || $conversation->workspace_id !== $message->workspace_id) {
                throw new LogicException('Message tenancy does not match its conversation.');
            }
            if (trim((string) $message->display_text) === '') {
                throw new LogicException('Message text must not be empty.');
            }
            if ($message->role === MessageRole::User
                && ($message->kind !== null || $message->created_by_user_id === null || $message->in_reply_to_message_id !== null)) {
                throw new LogicException('User message ownership fields are inconsistent.');
            }
            if ($message->role === MessageRole::Assistant
                && ($message->kind === null || $message->created_by_user_id !== null || $message->in_reply_to_message_id === null)) {
                throw new LogicException('Assistant message ownership fields are inconsistent.');
            }
            if ($message->role === MessageRole::Assistant) {
                $reply = Message::query()->find($message->in_reply_to_message_id);
                if (! $reply instanceof Message || $reply->role !== MessageRole::User
                    || $reply->conversation_id !== $message->conversation_id
                    || $reply->workspace_id !== $message->workspace_id) {
                    throw new LogicException('Assistant reply lineage is inconsistent.');
                }
            }
        });

        static::updating(function (Message $message): void {
            if ($message->isDirty()) {
                throw new LogicException('Messages are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['role' => MessageRole::class, 'kind' => MessageKind::class];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function inReplyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'in_reply_to_message_id');
    }

    public function generationRuns(): HasMany
    {
        return $this->hasMany(GenerationRun::class, 'user_message_id');
    }

    public function generatedAnswer(): HasOne
    {
        return $this->hasOne(GeneratedAnswer::class, 'assistant_message_id');
    }

    public function controlledResponse(): HasOne
    {
        return $this->hasOne(ControlledAssistantResponse::class);
    }
}
