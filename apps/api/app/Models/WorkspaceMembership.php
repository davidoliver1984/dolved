<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Database\Factories\WorkspaceMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['public_id', 'workspace_id', 'user_id', 'role', 'joined_at'])]
class WorkspaceMembership extends Model
{
    /** @use HasFactory<WorkspaceMembershipFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (WorkspaceMembership $membership): void {
            $membership->public_id ??= (string) Str::uuid();
        });
        static::deleting(function (WorkspaceMembership $membership): void {
            SavedView::query()
                ->where('workspace_id', $membership->workspace_id)
                ->where('user_id', $membership->user_id)
                ->delete();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
