<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceAdministrationCommand extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['result' => 'array'];
    }
}
