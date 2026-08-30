<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecordLibrarySettingsAudit
{
    /** @param array<string, mixed> $previous @param array<string, mixed> $next */
    public function handle(
        Workspace $workspace,
        User $actor,
        string $targetKind,
        string $targetPublicId,
        string $action,
        array $previous,
        array $next,
    ): void {
        DB::table('library_settings_audit_events')->insert([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'actor_user_id' => $actor->id,
            'target_kind' => $targetKind,
            'target_public_id' => $targetPublicId,
            'action' => $action,
            'previous_values' => json_encode($previous, JSON_THROW_ON_ERROR),
            'new_values' => json_encode($next, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
