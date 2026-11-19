<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class DifyWorkspaceBinding extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $binding): void {
            if ($binding->isDirty(['workspace_id', 'app_id', 'dataset_id'])) {
                throw new LogicException('Dify binding identity is immutable; replace the binding explicitly.');
            }
        });
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
