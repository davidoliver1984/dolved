<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class DifyConversationSession extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $session): void {
            if ($session->isDirty(['workspace_id', 'conversation_id', 'remote_conversation_id'])) {
                throw new LogicException('Dify conversation identity is immutable.');
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
