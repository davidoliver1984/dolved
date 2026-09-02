<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\WorkspaceNotificationSetting;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocumentGovernanceNotificationPreferenceController extends Controller
{
    public function show(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $workspaces->handle($user, $workspacePublicId);
        $settings = WorkspaceNotificationSetting::query()->firstOrCreate(
            ['workspace_id' => $membership->workspace->id],
            ['email_delivery_enabled' => true, 'default_email_enabled' => true],
        );

        return response()->json(['data' => [
            'workspace' => [
                'email_delivery_enabled' => $settings->email_delivery_enabled,
                'default_email_enabled' => $settings->default_email_enabled,
                'can_manage' => in_array($membership->role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true),
            ],
            'personal' => UserNotificationPreference::query()->where('user_id', $user->id)
                ->orderBy('category_group')->get(['category_group', 'email_enabled']),
        ]]);
    }

    public function updateWorkspace(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $workspaces->handle($user, $workspacePublicId);
        abort_unless(in_array($membership->role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true), 404);
        $data = $request->validate([
            'email_delivery_enabled' => ['required', 'boolean'],
            'default_email_enabled' => ['required', 'boolean'],
        ]);
        $settings = WorkspaceNotificationSetting::query()->updateOrCreate(
            ['workspace_id' => $membership->workspace->id],
            $data,
        );

        return response()->json(['data' => $settings->only(['email_delivery_enabled', 'default_email_enabled'])]);
    }

    public function updatePersonal(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaces->handle($user, $workspacePublicId);
        $data = $request->validate([
            'category_group' => ['required', 'string', 'max:96'],
            'email_enabled' => ['required', 'boolean'],
        ]);
        $preference = UserNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'category_group' => $data['category_group']],
            ['email_enabled' => $data['email_enabled']],
        );

        return response()->json(['data' => $preference->only(['category_group', 'email_enabled'])]);
    }
}
