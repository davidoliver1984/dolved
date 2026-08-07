<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentApplicabilityScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

#[Fillable(['scope', 'sealed_at'])]
class DocumentApplicabilitySnapshot extends Model
{
    protected static function booted(): void
    {
        static::updating(function (DocumentApplicabilitySnapshot $snapshot): void {
            if ($snapshot->getOriginal('sealed_at') !== null && $snapshot->isDirty(['scope', 'sealed_at'])) {
                throw new LogicException('A sealed document applicability snapshot is immutable.');
            }
        });

        static::deleting(function (DocumentApplicabilitySnapshot $snapshot): void {
            if ($snapshot->sealed_at !== null) {
                throw new LogicException('A sealed document applicability snapshot is immutable.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scope' => DocumentApplicabilityScope::class,
            'sealed_at' => 'immutable_datetime',
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

    /** @return BelongsToMany<OrganisationalLocation, $this> */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganisationalLocation::class,
            'document_applicability_locations',
        )->withPivot('workspace_id')->withTimestamps();
    }
}
