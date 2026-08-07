<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['alias'])]
class OrganisationalLocationAlias extends Model
{
    protected static function booted(): void
    {
        static::saving(function (OrganisationalLocationAlias $alias): void {
            $alias->normalised_alias = Str::lower(trim($alias->alias));
        });
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<OrganisationalLocation, $this> */
    public function organisationalLocation(): BelongsTo
    {
        return $this->belongsTo(OrganisationalLocation::class);
    }
}
