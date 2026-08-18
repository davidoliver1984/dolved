<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Workspace, $this>
     */
    public function createdWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function createdDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<DocumentGovernanceAuditEvent, $this>
     */
    public function documentGovernanceAuditEvents(): HasMany
    {
        return $this->hasMany(DocumentGovernanceAuditEvent::class, 'actor_user_id');
    }

    /**
     * @return HasMany<WorkspaceMembership, $this>
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function createdConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by_user_id');
    }

    /**
     * @return HasManyThrough<Workspace, WorkspaceMembership, $this>
     */
    public function workspaces(): HasManyThrough
    {
        return $this->hasManyThrough(
            Workspace::class,
            WorkspaceMembership::class,
            'user_id',
            'id',
            'id',
            'workspace_id'
        );
    }
}
