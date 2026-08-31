<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Imports\AssessImportItemMatches;
use App\Actions\Imports\CreateImportBatch;
use App\Actions\Imports\CreateImportDecisionSnapshot;
use App\Actions\Imports\RequestImportPromotionCancellation;
use App\Actions\Imports\ReserveImportPromotion;
use App\Actions\Imports\StartImportPreflight;
use App\Enums\DocumentCategoryStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\PromotionOperationKind;
use App\Jobs\AdvanceImportPromotion;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\PromotionAttempt;
use App\Models\User;
use App\Models\Workspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ImportWorkflowController extends Controller
{
    public function configuration(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        [$workspace, $user] = $this->scope($request, $workspacePublicId, $workspaces);

        return response()->json(['data' => [
            'formats' => config('documents.formats'),
            'max_upload_bytes' => config('documents.max_upload_bytes'),
            'upload_concurrency' => config('documents.upload_concurrency'),
            'retention_days' => config('imports.retention_days'),
            'review_options' => [
                'categories' => $workspace->documentCategories()->where('status', DocumentCategoryStatus::Active->value)
                    ->orderBy('normalised_name')->get(['public_id', 'name'])->values(),
                'tags' => $workspace->documentTags()->orderBy('normalised_name')
                    ->get(['public_id', 'name'])->values(),
                'owners' => $workspace->memberships()->with('user')->whereHas('user', fn ($query) => $query->whereNull('disabled_at'))
                    ->orderBy('id')->get()->map(fn ($membership): array => [
                        'public_id' => $membership->user->public_id,
                        'name' => $membership->user->name,
                    ])->values(),
                'locations' => $workspace->organisationalLocations()->orderBy('name')
                    ->get(['public_id', 'name'])->values(),
                'current_user_public_id' => $user->public_id,
            ],
        ]]);
    }

    public function index(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        [$workspace] = $this->scope($request, $workspacePublicId, $workspaces);
        $batches = ImportBatch::query()->where('workspace_id', $workspace->id)
            ->with(['items.promotionAttempts.committedDocument', 'items.preflightAttempts'])
            ->orderByDesc('id')->limit(20)->get();

        return response()->json(['data' => $batches->map(fn (ImportBatch $batch): array => $this->batch($batch))->values()]);
    }

    public function store(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, CreateImportBatch $create): JsonResponse
    {
        [$workspace, $user] = $this->scope($request, $workspacePublicId, $workspaces);
        $formats = collect(config('documents.formats'))->flatten()->unique()->values()->all();
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*.filename' => ['required', 'string', 'max:255'],
            'files.*.media_type' => ['required', 'string', Rule::in($formats)],
            'files.*.size_bytes' => ['required', 'integer', 'min:1', 'max:'.config('documents.max_upload_bytes')],
        ]);
        $result = $create->handle($workspace, $user, $validated['files']);

        return response()->json(['data' => [
            'batch' => $this->batch($result['batch']->load('items')),
            'uploads' => collect($result['items'])->map(fn (array $entry): array => [
                'item_public_id' => $entry['item']->public_id,
                'upload' => $entry['upload'],
            ])->values(),
        ]], 201);
    }

    public function show(Request $request, string $workspacePublicId, string $batchPublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        [$workspace] = $this->scope($request, $workspacePublicId, $workspaces);
        $batch = ImportBatch::query()->where('workspace_id', $workspace->id)->where('public_id', $batchPublicId)
            ->with(['items.promotionAttempts.committedDocument', 'items.preflightAttempts'])->firstOrFail();

        return response()->json(['data' => $this->batch($batch)]);
    }

    public function uploaded(Request $request, string $workspacePublicId, string $batchPublicId, string $itemPublicId, FindWorkspaceForUser $workspaces, StartImportPreflight $start): JsonResponse
    {
        [$workspace] = $this->scope($request, $workspacePublicId, $workspaces);
        $item = $this->item($workspace->id, $batchPublicId, $itemPublicId);
        $attempt = $start->handle($item);

        return response()->json(['data' => ['event_id' => $attempt->event_id, 'status' => 'verifying']], 202);
    }

    public function matches(Request $request, string $workspacePublicId, string $batchPublicId, string $itemPublicId, FindWorkspaceForUser $workspaces, AssessImportItemMatches $assess): JsonResponse
    {
        [$workspace] = $this->scope($request, $workspacePublicId, $workspaces);

        return response()->json(['data' => $assess->handle($this->item($workspace->id, $batchPublicId, $itemPublicId))]);
    }

    public function decide(Request $request, string $workspacePublicId, string $batchPublicId, string $itemPublicId, FindWorkspaceForUser $workspaces, AssessImportItemMatches $assess, CreateImportDecisionSnapshot $snapshots): JsonResponse
    {
        [$workspace, $user] = $this->scope($request, $workspacePublicId, $workspaces);
        $definition = $request->validate(['definition' => ['required', 'array']])['definition'];
        $item = $this->item($workspace->id, $batchPublicId, $itemPublicId);
        $matches = $assess->handle($item);
        abort_if($matches['exact_live_duplicates'] !== [], 409, 'An identical live document must be resolved before promotion.');
        $item->forceFill(['match_status' => ImportMatchStatus::Resolved])->save();
        $snapshot = $snapshots->handle($item->refresh(), $user, $definition);

        return response()->json(['data' => ['public_id' => $snapshot->public_id, 'digest_sha256' => $snapshot->digest_sha256]], 201);
    }

    public function promote(Request $request, string $workspacePublicId, string $batchPublicId, string $itemPublicId, FindWorkspaceForUser $workspaces, ReserveImportPromotion $reserve): JsonResponse
    {
        [$workspace, $user] = $this->scope($request, $workspacePublicId, $workspaces);
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:128']]);
        $attempt = $reserve->handle($this->item($workspace->id, $batchPublicId, $itemPublicId), $user, PromotionOperationKind::Promote, $validated['idempotency_key']);
        AdvanceImportPromotion::dispatch($attempt->id);

        return response()->json(['data' => ['public_id' => $attempt->public_id, 'status' => $attempt->status->value]], 202);
    }

    public function cancel(Request $request, string $workspacePublicId, string $batchPublicId, string $itemPublicId, string $attemptPublicId, FindWorkspaceForUser $workspaces, RequestImportPromotionCancellation $cancel): JsonResponse
    {
        [$workspace, $user] = $this->scope($request, $workspacePublicId, $workspaces);
        $item = $this->item($workspace->id, $batchPublicId, $itemPublicId);
        $attempt = PromotionAttempt::query()->where('import_item_id', $item->id)->where('public_id', $attemptPublicId)->firstOrFail();
        $attempt = $cancel->handle($attempt, $user);

        return response()->json(['data' => ['public_id' => $attempt->public_id, 'status' => $attempt->status->value]]);
    }

    /** @return array{0: Workspace, 1: User} */
    private function scope(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): array
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        Gate::authorize('uploadDocuments', $workspace);

        return [$workspace, $user];
    }

    private function item(int $workspaceId, string $batchPublicId, string $itemPublicId): ImportItem
    {
        return ImportItem::query()->where('workspace_id', $workspaceId)->where('public_id', $itemPublicId)
            ->whereHas('batch', fn ($query) => $query->where('public_id', $batchPublicId))->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function batch(ImportBatch $batch): array
    {
        $batch->loadMissing(['items.promotionAttempts.committedDocument', 'items.preflightAttempts']);

        return [
            'public_id' => $batch->public_id,
            'status' => $batch->status->value,
            'retention_expires_at' => $batch->retention_expires_at,
            'created_at' => $batch->created_at,
            'items' => $batch->items->map(function (ImportItem $item): array {
                $attempt = $item->promotionAttempts->sortByDesc('id')->first();
                $document = $attempt?->committedDocument;

                return [
                    'public_id' => $item->public_id,
                    'filename' => $item->source_filename,
                    'declared_media_type' => $item->declared_media_type,
                    'size_bytes' => $item->size_bytes,
                    'preflight_status' => $item->preflight_status->value,
                    'preflight_rejection_reason' => $item->preflight_rejection_reason?->value,
                    'match_status' => $item->match_status->value,
                    'decision_ready' => $item->current_decision_snapshot_id !== null,
                    'promotion' => $attempt === null ? null : [
                        'public_id' => $attempt->public_id,
                        'status' => $attempt->status->value,
                        'reason' => $attempt->terminal_reason,
                    ],
                    'document' => $document === null ? null : [
                        'public_id' => $document->public_id,
                        'status' => $document->status->value,
                    ],
                ];
            })->values(),
        ];
    }
}
