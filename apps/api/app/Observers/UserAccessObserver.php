<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class UserAccessObserver
{
    public function updated(User $user): void
    {
        if ($user->wasChanged('disabled_at') && $user->disabled_at !== null) {
            $this->invalidateSessions($user);
        }
    }

    public function deleting(User $user): void
    {
        $this->invalidateSessions($user);
    }

    private function invalidateSessions(User $user): void
    {
        $table = (string) config('session.table', 'sessions');
        if (Schema::hasTable($table)) {
            DB::table($table)->where('user_id', $user->getKey())->delete();
        }
    }
}
