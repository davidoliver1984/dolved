<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Documents\NormaliseDocumentMetadataName;
use App\Support\Documents\SavedViewDefinition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['name', 'definition_schema_version', 'definition'])]
final class SavedView extends Model
{
    protected static function booted(): void
    {
        self::creating(fn (SavedView $view) => $view->public_id ??= (string) Str::uuid());
        self::saving(function (SavedView $view): void {
            if ($view->exists && $view->isDirty(['public_id', 'workspace_id', 'user_id', 'definition_schema_version', 'definition'])) {
                throw new LogicException('Saved-view identity, ownership and definition are immutable after creation.');
            }
            $view->normalised_name = NormaliseDocumentMetadataName::handle($view->name);
        });
    }

    protected function casts(): array
    {
        return ['definition' => 'array', 'definition_schema_version' => 'integer'];
    }

    /** @return array{definition: array<string, mixed>, notices: list<string>} */
    public function openedDefinition(): array
    {
        return SavedViewDefinition::forOpen($this->definition, $this->definition_schema_version);
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
