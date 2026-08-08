<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

    /** @return HasMany<OrganisationalLocation, $this> */
    public function organisationalLocations(): HasMany
    {
        return $this->hasMany(OrganisationalLocation::class);
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
