<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Conversation extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (Conversation $conversation): void {
            if ($conversation->isDirty(['public_id', 'workspace_id', 'created_by_user_id'])) {
                throw new LogicException('Conversation identity and ownership are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['status' => ConversationStatus::class];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('ordinal');
    }

    public function generationRuns(): HasMany
    {
        return $this->hasMany(GenerationRun::class);
    }
}
