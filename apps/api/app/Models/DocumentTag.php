<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Documents\NormaliseDocumentMetadataName;
use Database\Factories\DocumentTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['name'])]
class DocumentTag extends Model
{
    /** @use HasFactory<DocumentTagFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (DocumentTag $tag): void {
            $tag->public_id ??= (string) Str::uuid();
        });

        static::saving(function (DocumentTag $tag): void {
            if ($tag->exists && $tag->isDirty(['public_id', 'workspace_id'])) {
                throw new LogicException('Document tag identity and ownership are immutable.');
            }

            $tag->normalised_name = NormaliseDocumentMetadataName::handle($tag->name);
        });
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsToMany<DocumentFamily, $this> */
    public function documentFamilies(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentFamily::class,
            'document_family_tag_assignments',
        )->withPivot('workspace_id')->withTimestamps();
    }
}
