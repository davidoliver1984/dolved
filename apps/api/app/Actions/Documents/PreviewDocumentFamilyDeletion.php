<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\User;
use App\Support\Documents\FamilyDeletionConfirmationDigest;
use App\Support\Documents\FamilyDeletionState;
use Illuminate\Support\Facades\DB;

final readonly class PreviewDocumentFamilyDeletion
{
    public function __construct(
        private FamilyDeletionState $states,
        private FamilyDeletionConfirmationDigest $digests,
    ) {}

    /** @return array<string, mixed> */
    public function handle(DocumentFamily $family, User $actor): array
    {
        return DB::transaction(function () use ($family, $actor): array {
            $locked = DocumentFamily::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            if ($locked->tombstoned_at !== null) {
                throw new DocumentGovernanceException('The document family has already been deleted.');
            }
            $versions = Document::query()->where('document_family_id', $locked->id)->orderBy('id')->lockForUpdate()->get();
            $state = $this->states->capture($locked, $versions);
            $stateDigest = $this->states->digest($state);

            return [
                'family' => ['public_id' => $locked->public_id, 'name' => $locked->name],
                'versions' => collect($state['versions'])->map(function (array $version): array {
                    unset($version['id']);

                    return $version;
                })->all(),
                'counts' => $state['counts'],
                'blockers' => $state['blockers'],
                'confirmation_digest' => $this->digests->issue($actor->id, $state, $stateDigest),
                'confirmation_state_digest' => $stateDigest,
                'warning' => 'Restoration is unavailable after completion. Existing citation snapshots survive, but source viewing disappears.',
                'knowledge_gap' => collect($state['versions'])->contains(fn (array $version): bool => $version['classification'] === 'current')
                    ? 'Deleting this family removes its current authoritative evidence immediately.'
                    : 'This family has no current authoritative version.',
            ];
        }, attempts: 3);
    }
}
