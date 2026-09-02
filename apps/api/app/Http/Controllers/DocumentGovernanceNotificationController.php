<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\DocumentGovernanceNotification;
use App\Models\User;
use App\Queries\Documents\CountAwaitingDocumentApproval;
use App\Queries\Documents\CountImportActionableWork;
use App\Queries\Documents\CountReviewActionableWork;
use App\Queries\Documents\CountScheduledDocumentChanges;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Support\Documents\RenderDocumentGovernanceNotification;
use App\Support\Documents\ResolveDocumentGovernanceNotificationRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocumentGovernanceNotificationController extends Controller
{
    public function index(
        Request $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
        RenderDocumentGovernanceNotification $renderer,
        ResolveDocumentGovernanceNotificationRoute $routes,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $history = $request->boolean('history');
        $notifications = DocumentGovernanceNotification::query()
            ->where('workspace_id', $workspace->id)
            ->where('recipient_user_id', $user->id)
            ->where('expires_at', '>', now())
            ->when(! $history, fn ($query) => $query->whereNull('dismissed_at'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->cursorPaginate(25);

        return response()->json([
            'data' => collect($notifications->items())->map(function (DocumentGovernanceNotification $notification) use ($renderer, $routes, $workspace): array {
                $copy = $renderer->handle($notification);

                return [
                    'public_id' => $notification->public_id,
                    'title' => $copy['title'],
                    'message' => $copy['message'],
                    'severity' => $notification->severity,
                    'target_label' => $notification->target_display_label,
                    'target_route' => $routes->handle($notification, $workspace),
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'dismissed_at' => $notification->dismissed_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ];
            })->values(),
            'meta' => [
                'unread_count' => $this->unreadQuery($workspace->id, $user->id)->count(),
                'next_cursor' => $notifications->nextCursor()?->encode(),
            ],
        ]);
    }

    public function actionableWork(
        Request $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
        CountAwaitingDocumentApproval $approval,
        CountImportActionableWork $imports,
        CountReviewActionableWork $reviews,
        CountScheduledDocumentChanges $scheduled,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $membership = $workspaces->handle($user, $workspacePublicId);
        $workspace = $membership->workspace;
        $canGovern = in_array($membership->role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true);
        $importCounts = $imports->handle($workspace);
        $reviewCounts = $reviews->handle($workspace);

        return response()->json(['data' => [
            'awaiting_approval' => $canGovern ? $approval->handle($workspace) : 0,
            'imports_processing' => $importCounts['processing'],
            'imports_warning' => $importCounts['warning'],
            'scheduled_changes' => $scheduled->handle($workspace),
            'review_due_soon' => $reviewCounts['due_soon'],
            'review_overdue' => $reviewCounts['overdue'],
        ]]);
    }

    public function read(Request $request, string $workspacePublicId, string $notificationPublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $notification = $this->ownNotification($request, $workspacePublicId, $notificationPublicId, $workspaces);
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => ['read_at' => $notification->read_at?->toIso8601String()]]);
    }

    public function dismiss(Request $request, string $workspacePublicId, string $notificationPublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $notification = $this->ownNotification($request, $workspacePublicId, $notificationPublicId, $workspaces);
        if ($notification->dismissed_at === null) {
            $notification->forceFill(['read_at' => $notification->read_at ?? now(), 'dismissed_at' => now()])->save();
        }

        return response()->json(['data' => ['dismissed_at' => $notification->dismissed_at?->toIso8601String()]]);
    }

    private function unreadQuery(int $workspaceId, int $userId)
    {
        return DocumentGovernanceNotification::query()
            ->where('workspace_id', $workspaceId)
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')->whereNull('dismissed_at')
            ->where('expires_at', '>', now());
    }

    private function ownNotification(Request $request, string $workspacePublicId, string $notificationPublicId, FindWorkspaceForUser $workspaces): DocumentGovernanceNotification
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;

        return DocumentGovernanceNotification::query()
            ->where('workspace_id', $workspace->id)
            ->where('recipient_user_id', $user->id)
            ->where('public_id', $notificationPublicId)
            ->firstOrFail();
    }
}
