<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WorkspaceNotificationSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['email_delivery_enabled' => 'boolean', 'default_email_enabled' => 'boolean'];
    }
}
