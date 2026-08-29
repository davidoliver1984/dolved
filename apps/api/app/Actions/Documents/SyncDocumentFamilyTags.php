<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\DocumentGovernanceException;
use App\Models\DocumentFamily;
use App\Models\DocumentTag;
use App\Models\User;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use Illuminate\Support\Facades\DB;

final readonly class SyncDocumentFamilyTags
{
    public function __construct(private RecordDocumentGovernanceAudit $audit) {}

    /** @param array<int, string> $tagPublicIds */
    public function handle(DocumentFamily $family, User $actor, array $tagPublicIds): DocumentFamily
    {
        return DB::transaction(function () use ($family, $actor, $tagPublicIds): DocumentFamily {
            $locked = DocumentFamily::query()->lockForUpdate()->findOrFail($family->id);
            $requested = array_values(array_unique($tagPublicIds));

            if (count($requested) > 20) {
                throw new DocumentGovernanceException('A document family may have at most 20 tags.');
            }

            $tags = DocumentTag::query()
                ->where('workspace_id', $locked->workspace_id)
                ->whereIn('public_id', $requested)
                ->get();

            if ($tags->count() !== count($requested)) {
                throw new DocumentGovernanceException('One or more selected tags are not available in this workspace.');
            }

            $before = $locked->tags()->orderBy('public_id')->pluck('public_id')->all();
            $sync = $tags->mapWithKeys(fn (DocumentTag $tag): array => [
                $tag->id => ['workspace_id' => $locked->workspace_id],
            ])->all();
            $locked->tags()->sync($sync);
            $after = $locked->tags()->orderBy('public_id')->pluck('public_id')->all();

            if ($before !== $after) {
                $this->audit->recordFamily($locked, $actor, 'document_family_tags_updated', ['tag_public_ids' => $before], ['tag_public_ids' => $after]);
            }

            return $locked->load(['category', 'owner', 'tags']);
        });
    }
}
