<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable(['name', 'slug', 'created_by_user_id'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Workspace $workspace): void {
            if ($workspace->isDirty('public_id')) {
                throw new LogicException('A workspace public ID is immutable.');
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<WorkspaceMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    /** @return HasMany<WorkspaceInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<DocumentFamily, $this> */
    public function documentFamilies(): HasMany
    {
        return $this->hasMany(DocumentFamily::class);
    }

    /** @return HasMany<DocumentCategory, $this> */
    public function documentCategories(): HasMany
    {
        return $this->hasMany(DocumentCategory::class);
    }

    /** @return HasMany<DocumentTag, $this> */
    public function documentTags(): HasMany
    {
        return $this->hasMany(DocumentTag::class);
    }

    /** @return HasMany<OrganisationalLocation, $this> */
    public function organisationalLocations(): HasMany
    {
        return $this->hasMany(OrganisationalLocation::class);
    }

    /** @return HasOne<WorkspaceBrandingProfile, $this> */
    public function brandingProfile(): HasOne
    {
        return $this->hasOne(WorkspaceBrandingProfile::class);
    }

    /** @return HasMany<WorkspaceBrandingRevision, $this> */
    public function brandingRevisions(): HasMany
    {
        return $this->hasMany(WorkspaceBrandingRevision::class);
    }

    /** @return HasMany<WorkspaceBrandingAsset, $this> */
    public function brandingAssets(): HasMany
    {
        return $this->hasMany(WorkspaceBrandingAsset::class);
    }

    public function questionReviewPolicy(): HasOne
    {
        return $this->hasOne(WorkspaceQuestionReviewPolicy::class);
    }

    public function questionReviewSubjects(): HasMany
    {
        return $this->hasMany(QuestionReviewSubject::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return HasMany<DocumentGovernanceAuditEvent, $this> */
    public function documentGovernanceAuditEvents(): HasMany
    {
        return $this->hasMany(DocumentGovernanceAuditEvent::class);
    }

    /**
     * @return HasMany<DocumentChunk, $this>
     */
    public function documentChunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    /**
     * @return HasMany<WorkspaceCorpusGeneration, $this>
     */
    public function workspaceCorpusGenerations(): HasMany
    {
        return $this->hasMany(WorkspaceCorpusGeneration::class);
    }

    /** @return HasMany<WorkspaceCorpusGenerationRollback, $this> */
    public function corpusGenerationRollbacks(): HasMany
    {
        return $this->hasMany(WorkspaceCorpusGenerationRollback::class);
    }

    /**
     * @return BelongsTo<WorkspaceCorpusGeneration, $this>
     */
    public function activeCorpusGeneration(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceCorpusGeneration::class,
            'active_workspace_corpus_generation_id'
        );
    }

    /**
     * @return HasManyThrough<User, WorkspaceMembership, $this>
     */
    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            WorkspaceMembership::class,
            'workspace_id',
            'id',
            'id',
            'user_id'
        );
    }
}
