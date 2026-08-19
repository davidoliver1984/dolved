<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Workspaces\AcceptWorkspaceInvitation;
use App\Actions\Workspaces\ChangeWorkspaceMemberRole;
use App\Actions\Workspaces\IssueWorkspaceInvitation;
use App\Actions\Workspaces\LeaveWorkspace;
use App\Actions\Workspaces\RemoveWorkspaceMember;
use App\Actions\Workspaces\RevokeWorkspaceInvitation;
use App\Actions\Workspaces\TransferWorkspaceOwnership;
use App\Enums\WorkspaceRole;
use App\Exceptions\WorkspaceAdministrationException;
use App\Http\Requests\AcceptWorkspaceInvitationRequest;
use App\Http\Requests\ChangeWorkspaceMemberRoleRequest;
use App\Http\Requests\IssueWorkspaceInvitationRequest;
use App\Http\Requests\WorkspaceAdministrationCommandRequest;
use App\Http\Resources\WorkspaceInvitationResource;
use App\Http\Resources\WorkspaceMembershipResource;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitationNotification;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Support\CorrelationId;
use App\Support\Workspaces\RecordWorkspaceAdministrationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class WorkspaceAdministrationController extends Controller
{
    public function __construct(private readonly RecordWorkspaceAdministrationAudit $audit) {}

    public function members(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): AnonymousResourceCollection
    {
        [$workspace, $role] = $this->administrableWorkspace($request, $workspacePublicId, $workspaces);
        $request->attributes->set('workspace_role', $role->value);

        return WorkspaceMembershipResource::collection(
            $workspace->memberships()->with(['user', 'workspace'])->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")->orderBy('id')->paginate(50),
        );
    }

    public function invitations(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): AnonymousResourceCollection
    {
        [$workspace, $role] = $this->administrableWorkspace($request, $workspacePublicId, $workspaces);
        $request->attributes->set('workspace_role', $role->value);

        return WorkspaceInvitationResource::collection(
            $workspace->invitations()->with('workspace')->latest('id')->paginate(50),
        );
    }

    public function issue(
        IssueWorkspaceInvitationRequest $request,
        string $workspacePublicId,
        FindWorkspaceForUser $workspaces,
        IssueWorkspaceInvitation $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        [$workspace] = $this->administrableWorkspace($request, $workspacePublicId, $workspaces);
        $correlationId = CorrelationId::resolve($request->header('X-Correlation-ID'));
        $result = $this->execute(
            $workspace,
            $user,
            'invitation.issue',
            null,
            $correlationId,
            fn (): array => $action->handle(
                $workspace,
                $user,
                $request->validated('email'),
                WorkspaceRole::from($request->validated('role')),
                $request->validated('idempotency_key'),
                $correlationId,
            ),
        );
        $delivery = 'not_attempted';
        $link = null;
        if ($result['invitation'] !== null && $result['token'] !== null) {
            $link = config('workspace_administration.frontend_url').'/invitations/'.rawurlencode($result['token']);
            try {
                Notification::route('mail', $result['invitation']->invited_email)
                    ->notify(new WorkspaceInvitationNotification($result['invitation'], $result['token']));
                $delivery = 'sent';
            } catch (Throwable) {
                Log::warning('Workspace invitation delivery was unavailable.', [
                    'workspace_id' => $workspace->public_id,
                    'invitation_id' => $result['invitation']->public_id,
                    'correlation_id' => $correlationId,
                ]);
                $delivery = 'unavailable';
            }
        }

        return response()->json(['data' => [
            'invitation' => $result['invitation'] === null ? null : (new WorkspaceInvitationResource($result['invitation']->load('workspace')))->resolve($request),
            'invitation_link' => $link,
            'link_returned_once' => $link !== null,
            'delivery_status' => $delivery,
            'replayed' => $result['replayed'],
            'already_member' => $result['already_member'],
        ]], $result['replayed'] ? 200 : 201)->header('X-Correlation-ID', $correlationId);
    }

    public function revoke(
        WorkspaceAdministrationCommandRequest $request,
        string $workspacePublicId,
        string $invitationPublicId,
        FindWorkspaceForUser $workspaces,
        RevokeWorkspaceInvitation $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        [$workspace] = $this->administrableWorkspace($request, $workspacePublicId, $workspaces);
        $correlationId = CorrelationId::resolve($request->header('X-Correlation-ID'));
        $invitation = $this->execute(
            $workspace,
            $user,
            'invitation.revoke',
            $invitationPublicId,
            $correlationId,
            fn () => $action->handle($workspace, $user, $invitationPublicId, $request->validated('idempotency_key'), $correlationId),
        );

        return response()->json(['data' => ['invitation' => (new WorkspaceInvitationResource($invitation->load('workspace')))->resolve($request)]])
            ->header('X-Correlation-ID', $correlationId);
    }

    public function accept(AcceptWorkspaceInvitationRequest $request, AcceptWorkspaceInvitation $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $correlationId = CorrelationId::resolve($request->header('X-Correlation-ID'));
        $membership = $action->handle($user, $request->validated('token'), $correlationId);

        return response()->json(['data' => [
            'workspace_id' => $membership->workspace->public_id,
            'membership_id' => $membership->public_id,
            'role' => $membership->role->value,
        ]])->header('X-Correlation-ID', $correlationId);
    }

    public function changeRole(
        ChangeWorkspaceMemberRoleRequest $request,
        string $workspacePublicId,
        string $membershipPublicId,
        FindWorkspaceForUser $workspaces,
        ChangeWorkspaceMemberRole $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        [$workspace] = $this->administrableWorkspace($request, $workspacePublicId, $workspaces);
        $correlationId = CorrelationId::resolve($request->header('X-Correlation-ID'));
        $membership = $this->execute(
            $workspace,
            $user,
            'membership.role.change',
            $membershipPublicId,
            $correlationId,
            fn () => $action->handle($workspace, $user, $membershipPublicId, WorkspaceRole::from($request->validated('role')), $request->validated('idempotency_key'), $correlationId),
        );

        return response()->json(['data' => ['membership' => (new WorkspaceMembershipResource($membership->load(['workspace', 'user'])))->resolve($request)]])
            ->header('X-Correlation-ID', $correlationId);
    }

    public function remove(
        WorkspaceAdministrationCommandRequest $request,
        string $workspacePublicId,
        string $membershipPublicId,
        FindWorkspaceForUser $workspaces,
        RemoveWorkspaceMember $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        [$workspace] = $this->administrableWorkspace($request, $workspacePublicId, $workspaces);
        $correlationId = CorrelationId::resolve($request->header('X-Correlation-ID'));
        $result = $this->execute(
            $workspace,
            $user,
            'membership.remove',
            $membershipPublicId,
            $correlationId,
            fn (): array => $action->handle($workspace, $user, $membershipPublicId, $request->validated('idempotency_key'), $correlationId),
        );

        return response()->json(['data' => $result])->header('X-Correlation-ID', $correlationId);
    }

    public function transfer(
        WorkspaceAdministrationCommandRequest $request,
        string $workspacePublicId,
        string $membershipPublicId,
        FindWorkspaceForUser $workspaces,
        TransferWorkspaceOwnership $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        [$workspace] = $this->administrableWorkspace($request, $workspacePublicId, $workspaces);
        $correlationId = CorrelationId::resolve($request->header('X-Correlation-ID'));
        $result = $this->execute(
            $workspace,
            $user,
            'ownership.transfer',
            $membershipPublicId,
            $correlationId,
            fn (): array => $action->handle($workspace, $user, $membershipPublicId, $request->validated('idempotency_key'), $correlationId),
        );

        return response()->json(['data' => [
            'former_owner_membership_id' => $result['former_owner']->public_id,
            'owner_membership_id' => $result['owner']->public_id,
        ]])->header('X-Correlation-ID', $correlationId);
    }

    public function leave(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, LeaveWorkspace $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $workspaces->handle($user, $workspacePublicId);
        $correlationId = CorrelationId::resolve($request->header('X-Correlation-ID'));
        $this->execute(
            $membership->workspace,
            $user,
            'membership.leave',
            $membership->public_id,
            $correlationId,
            fn () => $action->handle($membership->workspace, $user, $correlationId),
        );

        return response()->json(['data' => ['left' => true]])->header('X-Correlation-ID', $correlationId);
    }

    /** @return array{Workspace, WorkspaceRole} */
    private function administrableWorkspace(Request $request, string $publicId, FindWorkspaceForUser $workspaces): array
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $workspaces->handle($user, $publicId);
        Gate::authorize('viewAdministration', $membership->workspace);

        return [$membership->workspace, $membership->role];
    }

    private function execute(
        Workspace $workspace,
        User $actor,
        string $command,
        ?string $targetPublicId,
        string $correlationId,
        callable $operation,
    ): mixed {
        try {
            return $operation();
        } catch (WorkspaceAdministrationException $exception) {
            $this->audit->record(
                $workspace,
                $actor,
                'administration_failed',
                'workspace_administration_command',
                $targetPublicId,
                null,
                null,
                $correlationId,
                ['command' => $command, 'error_code' => $exception->errorCode],
            );

            throw $exception;
        }
    }
}
