<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentGovernanceActorType;
use App\Enums\DocumentGovernanceSystemActorCode;
use App\Enums\DocumentGovernanceTargetScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentGovernanceAuditEvent extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'immutable_datetime',
            'target_scope' => DocumentGovernanceTargetScope::class,
            'actor_type' => DocumentGovernanceActorType::class,
            'system_actor_code' => DocumentGovernanceSystemActorCode::class,
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<DocumentFamily, $this> */
    public function documentFamily(): BelongsTo
    {
        return $this->belongsTo(DocumentFamily::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
