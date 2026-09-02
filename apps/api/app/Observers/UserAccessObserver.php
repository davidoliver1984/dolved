<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\FanOutUserDisablementReconciliation;
use App\Models\User;
use App\Models\UserDisablementReconciliationSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class UserAccessObserver
{
    public function updated(User $user): void
    {
        if ($user->wasChanged('disabled_at') && $user->disabled_at !== null) {
            $this->invalidateSessions($user);
            $source = UserDisablementReconciliationSource::query()->create([
                'public_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'disabled_at' => $user->disabled_at,
            ]);
            FanOutUserDisablementReconciliation::dispatch($source->id);
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
