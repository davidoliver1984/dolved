<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DocumentGovernanceCommand extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (DocumentGovernanceCommand $command): void {
            if ($command->isDirty([
                'public_id',
                'workspace_id',
                'purpose',
                'idempotency_key',
                'actor_user_id',
                'target_kind',
                'target_document_id',
                'target_state_at_creation',
                'request_payload_digest',
            ])) {
                throw new LogicException('Document governance command identity is immutable.');
            }

            if ($command->getRawOriginal('status') === 'completed') {
                throw new LogicException('A completed document governance command is immutable.');
            }
        });
    }

    /** @return BelongsTo<Document, $this> */
    public function resultDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'result_document_id');
    }
}
