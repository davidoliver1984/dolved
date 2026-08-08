<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkspaceCorpusGenerationRollbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceCorpusGenerationRollback extends Model
{
    /** @use HasFactory<WorkspaceCorpusGenerationRollbackFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<WorkspaceCorpusGeneration, $this> */
    public function demotedGeneration(): BelongsTo
    {
        return $this->belongsTo(WorkspaceCorpusGeneration::class, 'demoted_generation_id');
    }

    /** @return BelongsTo<WorkspaceCorpusGeneration, $this> */
    public function promotedGeneration(): BelongsTo
    {
        return $this->belongsTo(WorkspaceCorpusGeneration::class, 'promoted_generation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
