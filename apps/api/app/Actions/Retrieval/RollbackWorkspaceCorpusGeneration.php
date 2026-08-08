<?php

declare(strict_types=1);

namespace App\Actions\Retrieval;

use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Exceptions\RetrievalException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationRollback;
use App\Services\Ingestion\IngestionCanonicaliser;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RollbackWorkspaceCorpusGeneration
{
    public function __construct(
        private RetrievalClient $client,
        private IngestionCanonicaliser $canonicaliser,
    ) {}

    public function handle(
        Workspace $workspace,
        WorkspaceCorpusGeneration $target,
        ?User $actor,
        string $reason,
    ): WorkspaceCorpusGenerationRollback {
        $reason = trim($reason);
        if ($target->workspace_id !== $workspace->id || $reason === '' || mb_strlen($reason) > 500) {
            throw new RetrievalException('The rollback target or reason is invalid.');
        }
        if ($target->status !== WorkspaceCorpusGenerationStatus::Superseded) {
            throw new RetrievalException('Only a retained superseded generation can be rolled back.');
        }
        $verification = $this->client->verifyCorpus($workspace, $target);
        $digest = $this->canonicaliser->pointManifestDigest($verification['point_ids']);
        if (
            ! $verification['complete']
            || ($target->expected_point_count !== null
                && count($verification['point_ids']) !== $target->expected_point_count)
            || ($target->point_manifest_digest !== null
                && ! hash_equals($target->point_manifest_digest, $digest))
        ) {
            throw new RetrievalException('The rollback target failed completeness re-verification.');
        }

        return DB::transaction(function () use ($workspace, $target, $actor, $reason, $verification, $digest): WorkspaceCorpusGenerationRollback {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $current = WorkspaceCorpusGeneration::query()
                ->lockForUpdate()
                ->findOrFail($lockedWorkspace->active_workspace_corpus_generation_id);
            $promoted = WorkspaceCorpusGeneration::query()->lockForUpdate()->findOrFail($target->id);
            if (
                $current->status !== WorkspaceCorpusGenerationStatus::Active
                || $promoted->status !== WorkspaceCorpusGenerationStatus::Superseded
                || $current->id === $promoted->id
            ) {
                throw new RetrievalException('Corpus rollback state changed during re-verification.');
            }
            $now = now();
            $current->forceFill([
                'status' => WorkspaceCorpusGenerationStatus::Superseded,
                'superseded_at' => $now,
            ])->save();
            $promoted->forceFill([
                'status' => WorkspaceCorpusGenerationStatus::Active,
                'superseded_at' => null,
                'expected_point_count' => count($verification['point_ids']),
                'point_manifest_digest' => $digest,
                'verified_at' => $now,
            ])->save();
            $lockedWorkspace->forceFill([
                'active_workspace_corpus_generation_id' => $promoted->id,
            ])->save();
            $rollback = new WorkspaceCorpusGenerationRollback([
                'workspace_id' => $lockedWorkspace->id,
                'demoted_generation_id' => $current->id,
                'promoted_generation_id' => $promoted->id,
                'actor_user_id' => $actor?->id,
                'reason' => $reason,
                'occurred_at' => $now,
            ]);
            $rollback->public_id = (string) Str::uuid();
            $rollback->save();

            return $rollback->load(['workspace', 'demotedGeneration', 'promotedGeneration', 'actor']);
        });
    }
}
