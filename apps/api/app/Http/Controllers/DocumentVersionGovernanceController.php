<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\ApproveDocumentVersion;
use App\Actions\Documents\CorrectDocumentGovernanceTimestamps;
use App\Actions\Documents\ExecuteApplicabilityOnlySuccessorCommand;
use App\Actions\Documents\ExecuteDocumentGovernanceCommand;
use App\Actions\Documents\RescheduleDocumentVersion;
use App\Actions\Documents\WithdrawDocumentVersion;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Exceptions\DocumentGovernanceException;
use App\Exceptions\DocumentGovernanceIdempotencyConflict;
use App\Http\Requests\CorrectDocumentGovernanceTimestampsRequest;
use App\Http\Requests\CreateApplicabilityOnlySuccessorRequest;
use App\Http\Requests\DocumentGovernanceCommandRequest;
use App\Http\Requests\RescheduleDocumentVersionRequest;
use App\Http\Resources\DocumentVersionResource;
use App\Models\Document;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Queries\Documents\FindDocumentFamilyForWorkspace;
use App\Queries\Documents\FindDocumentForWorkspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Support\Documents\DocumentAuthorityTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class DocumentVersionGovernanceController extends Controller
{
    public function index(
        Request $request,
        string $workspacePublicId,
        string $familyPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentFamilyForWorkspace $families,
        DocumentAuthorityTimeline $timeline,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('viewDocumentMetadata', $workspace);
        $family = $families->handle($workspace, $familyPublicId);
        $versions = $family->documents()
            ->with(['family', 'predecessor', 'applicabilitySnapshot.locations', 'activeExtractionProjectionGeneration'])
            ->orderBy('id')
            ->get();

        $current = $timeline->resolve($family, now());
        $canManage = Gate::allows('manageDocumentGovernance', $workspace);
        $canCorrect = Gate::allows('correctDocumentGovernance', $workspace);
        $versions->each(function (Document $version) use ($canCorrect, $canManage, $current, $timeline): void {
            $authorityStart = $timeline->authorityStart($version);
            $version->setAttribute('is_current_authority', $current?->is($version) ?? false);
            $version->setAttribute('capabilities', [
                'approve' => $canManage && $version->governance_status === DocumentGovernanceStatus::Draft,
                'withdraw' => $canManage && $version->governance_status === DocumentGovernanceStatus::Approved,
                'reschedule' => $canManage
                    && $version->governance_status === DocumentGovernanceStatus::Approved
                    && $authorityStart?->isFuture() === true,
                'create_applicability_successor' => $canManage && $version->status === DocumentStatus::Indexed,
                'correct_timestamps' => $canCorrect,
            ]);
        });

        return response()->json([
            'data' => DocumentVersionResource::collection($versions)->resolve($request),
            'meta' => [
                'current_version_public_id' => $current?->public_id,
                'locations' => $workspace->organisationalLocations()
                    ->orderBy('name')
                    ->get(['public_id', 'name'])
                    ->map(fn ($location): array => [
                        'public_id' => $location->public_id,
                        'name' => $location->name,
                    ])->values()->all(),
            ],
        ]);
    }

    public function approve(DocumentGovernanceCommandRequest $request, string $workspacePublicId, string $documentPublicId, FindWorkspaceForUser $workspaces, FindDocumentForWorkspace $documents, ExecuteDocumentGovernanceCommand $commands, ApproveDocumentVersion $approve): JsonResponse
    {
        return $this->execute($request, $workspacePublicId, $documentPublicId, 'approve', [], $workspaces, $documents, $commands, fn (Document $document, User $user): Document => $approve->handle($document, $user));
    }

    public function applicabilitySuccessor(
        CreateApplicabilityOnlySuccessorRequest $request,
        string $workspacePublicId,
        string $documentPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentForWorkspace $documents,
        ExecuteApplicabilityOnlySuccessorCommand $commands,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('manageDocumentGovernance', $workspace);
        $document = $documents->handle($workspace, $documentPublicId);
        $locationPublicIds = $request->array('location_public_ids');
        $locations = OrganisationalLocation::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('public_id', $locationPublicIds)
            ->orderBy('id')->get();
        if ($locations->count() !== count($locationPublicIds)) {
            abort(404);
        }

        try {
            [$result, $command, $replayed] = $commands->handle(
                $document,
                $user,
                $request->string('idempotency_key')->value(),
                CarbonImmutable::parse($request->string('effective_from')->value()),
                $locations->all(),
                (string) Str::uuid(),
            );
        } catch (DocumentGovernanceIdempotencyConflict) {
            return response()->json(['error' => ['code' => 'idempotency_key_conflict']], 409);
        } catch (DocumentGovernanceException) {
            return response()->json(['error' => ['code' => 'governance_state_conflict']], 409);
        }
        $result->load(['family', 'predecessor', 'applicabilitySnapshot.locations']);

        return response()->json([
            'data' => (new DocumentVersionResource($result))->resolve($request),
            'meta' => ['command_public_id' => $command->public_id, 'replayed' => $replayed],
        ]);
    }

    public function withdraw(DocumentGovernanceCommandRequest $request, string $workspacePublicId, string $documentPublicId, FindWorkspaceForUser $workspaces, FindDocumentForWorkspace $documents, ExecuteDocumentGovernanceCommand $commands, WithdrawDocumentVersion $withdraw): JsonResponse
    {
        return $this->execute($request, $workspacePublicId, $documentPublicId, 'withdraw', [], $workspaces, $documents, $commands, fn (Document $document, User $user): Document => $withdraw->handle($document, $user));
    }

    public function reschedule(RescheduleDocumentVersionRequest $request, string $workspacePublicId, string $documentPublicId, FindWorkspaceForUser $workspaces, FindDocumentForWorkspace $documents, ExecuteDocumentGovernanceCommand $commands, RescheduleDocumentVersion $reschedule): JsonResponse
    {
        $effectiveFrom = CarbonImmutable::parse($request->string('effective_from')->value());

        return $this->execute($request, $workspacePublicId, $documentPublicId, 'reschedule', ['effective_from' => $effectiveFrom->toISOString()], $workspaces, $documents, $commands, fn (Document $document, User $user): Document => $reschedule->handle($document, $user, $effectiveFrom));
    }

    public function correct(CorrectDocumentGovernanceTimestampsRequest $request, string $workspacePublicId, string $documentPublicId, FindWorkspaceForUser $workspaces, FindDocumentForWorkspace $documents, ExecuteDocumentGovernanceCommand $commands, CorrectDocumentGovernanceTimestamps $correct): JsonResponse
    {
        $approvedAt = CarbonImmutable::parse($request->string('approved_at')->value());
        $withdrawnAt = $request->validated('withdrawn_at') === null ? null : CarbonImmutable::parse($request->string('withdrawn_at')->value());
        $reason = trim($request->string('reason')->value());

        return $this->execute($request, $workspacePublicId, $documentPublicId, 'correct_timestamps', [
            'approved_at' => $approvedAt->toISOString(),
            'withdrawn_at' => $withdrawnAt?->toISOString(),
            'reason' => $reason,
        ], $workspaces, $documents, $commands, fn (Document $document, User $user): Document => $correct->handle($document, $user, $approvedAt, $withdrawnAt, $reason), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(Document, User): Document  $mutation
     */
    private function execute(DocumentGovernanceCommandRequest $request, string $workspacePublicId, string $documentPublicId, string $purpose, array $payload, FindWorkspaceForUser $workspaces, FindDocumentForWorkspace $documents, ExecuteDocumentGovernanceCommand $commands, callable $mutation, bool $ownerOnly = false): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize($ownerOnly ? 'correctDocumentGovernance' : 'manageDocumentGovernance', $workspace);
        $document = $documents->handle($workspace, $documentPublicId);

        try {
            [$result, $command, $replayed] = $commands->handle(
                $document,
                $user,
                $purpose,
                $request->string('idempotency_key')->value(),
                $payload,
                fn (): Document => $mutation($document, $user),
            );
        } catch (DocumentGovernanceIdempotencyConflict) {
            return response()->json(['error' => ['code' => 'idempotency_key_conflict']], 409);
        } catch (DocumentGovernanceException) {
            return response()->json(['error' => ['code' => 'governance_state_conflict']], 409);
        }

        $result->load(['family', 'predecessor', 'applicabilitySnapshot.locations']);

        return response()->json([
            'data' => (new DocumentVersionResource($result))->resolve($request),
            'meta' => ['command_public_id' => $command->public_id, 'replayed' => $replayed],
        ]);
    }
}
