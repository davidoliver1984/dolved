<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

final readonly class CreateConversation
{
    public function handle(Workspace $workspace, User $user, ?OrganisationalLocation $selectedLocation = null): Conversation
    {
        if ($selectedLocation !== null && $selectedLocation->workspace_id !== $workspace->id) {
            throw new \InvalidArgumentException('The selected site does not belong to the workspace.');
        }

        return Conversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $user->id,
            'selected_location_id' => $selectedLocation?->id,
            'title' => 'New conversation',
            'status' => ConversationStatus::Active,
        ]);
    }
}
