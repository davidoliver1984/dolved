<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentCategoryStatus;
use App\Support\Documents\NormaliseDocumentMetadataName;
use Database\Factories\DocumentCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['name', 'status'])]
class DocumentCategory extends Model
{
    /** @use HasFactory<DocumentCategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (DocumentCategory $category): void {
            $category->public_id ??= (string) Str::uuid();
        });

        static::saving(function (DocumentCategory $category): void {
            if ($category->exists && $category->isDirty(['public_id', 'workspace_id'])) {
                throw new LogicException('Document category identity and ownership are immutable.');
            }

            $category->normalised_name = NormaliseDocumentMetadataName::handle($category->name);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => DocumentCategoryStatus::class];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return HasMany<DocumentFamily, $this> */
    public function documentFamilies(): HasMany
    {
        return $this->hasMany(DocumentFamily::class, 'category_id');
    }
}
