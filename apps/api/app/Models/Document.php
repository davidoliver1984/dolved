<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'source_filename',
    'media_type',
    'size_bytes',
    'failure_category',
    'failure_message',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Document $document): void {
            if ($document->exists && $document->isDirty('public_id')) {
                throw new LogicException('A document public ID is immutable.');
            }

            if ($document->exists && $document->isDirty('workspace_id')) {
                throw new LogicException('Document workspace ownership is immutable.');
            }

            if ($document->exists && $document->isDirty('created_by_user_id')) {
                throw new LogicException('Document creation provenance is immutable.');
            }

            if (
                $document->status === DocumentStatus::Failed
                && (
                    blank($document->failure_category)
                    || blank($document->failure_message)
                )
            ) {
                throw new LogicException(
                    'A failed document requires a failure category and message.'
                );
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
