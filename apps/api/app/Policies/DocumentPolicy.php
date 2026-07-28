<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function completeUpload(User $user, Document $document): bool
    {
        return $document->workspace
            ->memberships()
            ->whereBelongsTo($user)
            ->exists();
    }
}
