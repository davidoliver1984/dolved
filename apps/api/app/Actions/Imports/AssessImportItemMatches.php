<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentStatus;
use App\Enums\ImportPreflightStatus;
use App\Exceptions\ImportPreflightException;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\ImportItem;
use App\Support\Imports\DeterministicImportMatchScore;
use App\Support\Imports\NormaliseImportMatchText;
use InvalidArgumentException;

final readonly class AssessImportItemMatches
{
    public function __construct(
        private NormaliseImportMatchText $normaliser,
        private DeterministicImportMatchScore $scores,
    ) {}

    /**
     * @return array{
     *   profile_version: string,
     *   exact_live_duplicates: list<array{document_id: string, family_id: string, status: string}>,
     *   deleted_duplicates: list<array{document_id: string, family_id: string, status: string}>,
     *   applicability_only_redirect_document_id: ?string,
     *   family_candidates: list<array{family_id: string, title: string, score_basis_points: int}>
     * }
     */
    public function handle(ImportItem $item): array
    {
        if ($item->preflight_status !== ImportPreflightStatus::Verified
            || ! is_string($item->source_checksum_sha256)
            || ! is_string($item->source_filename)
            || $item->source_filename === '') {
            throw ImportPreflightException::conflict('matching_not_eligible');
        }
        try {
            $source = $this->normaliser->sourceFilename($item->source_filename);
        } catch (InvalidArgumentException) {
            throw ImportPreflightException::invalid('unsupported_source_filename');
        }
        if ($source === '') {
            throw ImportPreflightException::invalid('unsupported_source_filename');
        }

        $map = static fn (Document $document): array => [
            'document_id' => $document->public_id,
            'family_id' => $document->family->public_id,
            'status' => $document->status->value,
        ];
        $liveStatuses = [
            DocumentStatus::Uploaded,
            DocumentStatus::Queued,
            DocumentStatus::Processing,
            DocumentStatus::Indexed,
            DocumentStatus::Failed,
        ];
        $limit = (int) config('imports.matching.maximum_exact_matches');
        $baseQuery = Document::query()
            ->with('family:id,public_id')
            ->where('workspace_id', $item->workspace_id)
            ->where('source_checksum_sha256', $item->source_checksum_sha256)
            ->where('checksum_verification_status', ChecksumVerificationStatus::Verified->value);
        $live = (clone $baseQuery)->whereIn('status', array_map(
            static fn (DocumentStatus $status): string => $status->value,
            $liveStatuses,
        ))->orderBy('public_id')->limit($limit)->get()->map($map)->all();
        $deleted = (clone $baseQuery)->whereIn('status', [
            DocumentStatus::Deleting->value,
            DocumentStatus::Deleted->value,
        ])->orderBy('public_id')->limit($limit)->get()->map($map)->all();

        return [
            'profile_version' => (string) config('imports.matching.profile_version'),
            'exact_live_duplicates' => $live,
            'deleted_duplicates' => $deleted,
            'applicability_only_redirect_document_id' => $live[0]['document_id'] ?? null,
            'family_candidates' => $live === [] ? $this->familyCandidates($item, $source) : [],
        ];
    }

    /** @return list<array{family_id: string, title: string, score_basis_points: int}> */
    private function familyCandidates(ImportItem $item, string $source): array
    {
        $threshold = (int) config('imports.matching.threshold_basis_points');
        $candidates = [];
        foreach (DocumentFamily::query()
            ->where('workspace_id', $item->workspace_id)
            ->whereNull('tombstoned_at')
            ->select(['id', 'public_id', 'name'])
            ->orderBy('id')
            ->lazyById(200) as $family) {
            $title = $this->normaliser->familyTitle((string) $family->name);
            if ($title === '') {
                continue;
            }
            $score = $this->scores->basisPoints($source, $title);
            if ($score < $threshold) {
                continue;
            }
            $candidates[] = [
                'family_id' => $family->public_id,
                'title' => $family->name,
                'score_basis_points' => $score,
            ];
        }
        usort($candidates, static fn (array $left, array $right): int => [
            -$left['score_basis_points'],
            $left['family_id'],
        ] <=> [
            -$right['score_basis_points'],
            $right['family_id'],
        ]);

        return array_slice($candidates, 0, (int) config('imports.matching.maximum_candidates'));
    }
}
