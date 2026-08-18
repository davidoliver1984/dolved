<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

final readonly class CreateConversation
{
    public function handle(Workspace $workspace, User $user): Conversation
    {
        return Conversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $user->id,
            'title' => 'New conversation',
            'status' => ConversationStatus::Active,
        ]);
    }
}
