<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DocumentFamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['name', 'description', 'category_id', 'owner_user_id', 'review_due_date'])]
class DocumentFamily extends Model
{
    /** @use HasFactory<DocumentFamilyFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (DocumentFamily $family): void {
            if ($family->isDirty(['public_id', 'workspace_id'])) {
                throw new LogicException('Document family identity and ownership are immutable.');
            }

            if ($family->getRawOriginal('tombstoned_at') !== null
                && $family->isDirty(array_diff(array_keys($family->getDirty()), ['updated_at']))) {
                throw new LogicException('A document family tombstone is immutable.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'review_due_date' => 'immutable_date',
            'tombstoned_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderBy('effective_from');
    }

    /** @return HasMany<DocumentGovernanceAuditEvent, $this> */
    public function governanceAuditEvents(): HasMany
    {
        return $this->hasMany(DocumentGovernanceAuditEvent::class);
    }

    /** @return HasMany<DocumentFamilyDeletionOperation, $this> */
    public function deletionOperations(): HasMany
    {
        return $this->hasMany(DocumentFamilyDeletionOperation::class);
    }

    /** @return BelongsTo<DocumentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<DocumentTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentTag::class,
            'document_family_tag_assignments',
        )->withPivot('workspace_id')->withTimestamps();
    }

    /** @return BelongsToMany<OrganisationalLocation, $this> */
    public function defaultApplicabilityLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganisationalLocation::class,
            'document_family_default_applicabilities',
        )->withPivot('workspace_id')->withTimestamps();
    }
}
