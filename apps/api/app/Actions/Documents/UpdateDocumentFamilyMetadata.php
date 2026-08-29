<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentCategoryStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Models\DocumentCategory;
use App\Models\DocumentFamily;
use App\Models\User;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class UpdateDocumentFamilyMetadata
{
    public function __construct(private RecordDocumentGovernanceAudit $audit) {}

    public function handle(
        DocumentFamily $family,
        User $actor,
        ?string $description,
        ?DocumentCategory $category,
        User $owner,
        ?string $reviewDueDate,
    ): DocumentFamily {
        return DB::transaction(function () use ($family, $actor, $description, $category, $owner, $reviewDueDate): DocumentFamily {
            $locked = DocumentFamily::query()->lockForUpdate()->findOrFail($family->id);

            if ($category !== null && ($category->workspace_id !== $locked->workspace_id || $category->status !== DocumentCategoryStatus::Active)) {
                throw new DocumentGovernanceException('The selected category is not available in this workspace.');
            }

            $eligibleOwner = User::query()
                ->whereKey($owner->id)
                ->whereNull('disabled_at')
                ->whereHas('workspaceMemberships', fn ($query) => $query->where('workspace_id', $locked->workspace_id))
                ->exists();

            if (! $eligibleOwner) {
                throw new DocumentGovernanceException('The selected owner is not an active workspace member.');
            }

            $before = $this->values($locked);
            $locked->description = $description === null ? null : trim($description);
            $locked->category_id = $category?->id;
            $locked->owner_user_id = $owner->id;
            $locked->review_due_date = $reviewDueDate === null ? null : CarbonImmutable::parse($reviewDueDate)->toDateString();
            $locked->save();
            $after = $this->values($locked->refresh());

            if ($before !== $after) {
                $this->audit->recordFamily($locked, $actor, 'document_family_metadata_updated', $before, $after);
            }

            return $locked->load(['category', 'owner', 'tags']);
        });
    }

    /** @return array<string, mixed> */
    private function values(DocumentFamily $family): array
    {
        return [
            'description' => $family->description,
            'category_public_id' => $family->category?->public_id,
            'owner_public_id' => $family->owner?->public_id,
            'review_due_date' => $family->review_due_date?->toDateString(),
        ];
    }
}
