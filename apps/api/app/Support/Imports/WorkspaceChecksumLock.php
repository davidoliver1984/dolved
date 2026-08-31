<?php

declare(strict_types=1);

namespace App\Support\Imports;

use App\Models\WorkspaceChecksumReservation;
use Illuminate\Support\Facades\DB;
use LogicException;

final class WorkspaceChecksumLock
{
    public function acquire(int $workspaceId, string $checksum): WorkspaceChecksumReservation
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('The workspace checksum lock requires an active database transaction.');
        }
        if ($workspaceId < 1 || preg_match('/^[0-9a-f]{64}$/', $checksum) !== 1) {
            throw new LogicException('The workspace checksum lock identity is invalid.');
        }
        DB::table('workspace_checksum_reservations')->insertOrIgnore([
            'workspace_id' => $workspaceId,
            'source_checksum_sha256' => $checksum,
            'created_at' => now(),
        ]);

        return WorkspaceChecksumReservation::query()
            ->where('workspace_id', $workspaceId)
            ->where('source_checksum_sha256', $checksum)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
