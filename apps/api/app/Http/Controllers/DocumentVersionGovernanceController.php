<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\ApproveDocumentVersion;
use App\Actions\Documents\CorrectDocumentGovernanceTimestamps;
use App\Actions\Documents\ExecuteDocumentGovernanceCommand;
use App\Actions\Documents\RescheduleDocumentVersion;
use App\Actions\Documents\WithdrawDocumentVersion;
use App\Exceptions\DocumentGovernanceException;
use App\Exceptions\DocumentGovernanceIdempotencyConflict;
use App\Http\Requests\CorrectDocumentGovernanceTimestampsRequest;
use App\Http\Requests\DocumentGovernanceCommandRequest;
use App\Http\Requests\RescheduleDocumentVersionRequest;
use App\Http\Resources\DocumentVersionResource;
use App\Models\Document;
use App\Models\User;
use App\Queries\Documents\FindDocumentFamilyForWorkspace;
use App\Queries\Documents\FindDocumentForWorkspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DocumentVersionGovernanceController extends Controller
{
    public function index(
        Request $request,
        string $workspacePublicId,
        string $familyPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentFamilyForWorkspace $families,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('viewDocumentMetadata', $workspace);
        $family = $families->handle($workspace, $familyPublicId);
        $versions = $family->documents()
            ->with(['family', 'predecessor', 'applicabilitySnapshot.locations'])
            ->orderBy('id')
            ->get();

        return response()->json(['data' => DocumentVersionResource::collection($versions)->resolve($request)]);
    }

    public function approve(DocumentGovernanceCommandRequest $request, string $workspacePublicId, string $documentPublicId, FindWorkspaceForUser $workspaces, FindDocumentForWorkspace $documents, ExecuteDocumentGovernanceCommand $commands, ApproveDocumentVersion $approve): JsonResponse
    {
        return $this->execute($request, $workspacePublicId, $documentPublicId, 'approve', [], $workspaces, $documents, $commands, fn (Document $document, User $user): Document => $approve->handle($document, $user));
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
