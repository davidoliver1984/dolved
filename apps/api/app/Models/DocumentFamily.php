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

#[Fillable(['name'])]
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
        });
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

    /** @return BelongsToMany<OrganisationalLocation, $this> */
    public function defaultApplicabilityLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganisationalLocation::class,
            'document_family_default_applicabilities',
        )->withPivot('workspace_id')->withTimestamps();
    }
}
