<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrganisationalLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['parent_id', 'name', 'kind'])]
class OrganisationalLocation extends Model
{
    /** @use HasFactory<OrganisationalLocationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (OrganisationalLocation $location): void {
            if ($location->exists && $location->isDirty(['public_id', 'workspace_id'])) {
                throw new LogicException('Organisational location identity and ownership are immutable.');
            }

            if ($location->parent_id !== null && $location->parent_id === $location->id) {
                throw new LogicException('An organisational location cannot be its own parent.');
            }

            if ($location->parent_id !== null) {
                $parent = self::query()->findOrFail($location->parent_id);
                if ($parent->workspace_id !== $location->workspace_id) {
                    throw new LogicException('An organisational location parent must belong to the same workspace.');
                }

                $visited = [$location->id];
                while ($parent !== null) {
                    if (in_array($parent->id, $visited, true)) {
                        throw new LogicException('An organisational location hierarchy cannot contain a cycle.');
                    }
                    $visited[] = $parent->id;
                    $parent = $parent->parent_id === null ? null : self::query()->find($parent->parent_id);
                }
            }
        });
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<OrganisationalLocation, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<OrganisationalLocation, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<OrganisationalLocationAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(OrganisationalLocationAlias::class);
    }

    /** @return BelongsToMany<DocumentApplicabilitySnapshot, $this> */
    public function applicabilitySnapshots(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentApplicabilitySnapshot::class,
            'document_applicability_locations',
        )->withPivot('workspace_id')->withTimestamps();
    }

    /** @return BelongsToMany<DocumentFamily, $this> */
    public function defaultForDocumentFamilies(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentFamily::class,
            'document_family_default_applicabilities',
            'organisational_location_id',
            'document_family_id',
        )->withPivot('workspace_id')->withTimestamps();
    }
}
