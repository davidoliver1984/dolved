<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\GovernanceEmailAttemptStatus;
use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeAttempt;
use Illuminate\Support\Facades\DB;

final class ReclaimExpiredDocumentGovernanceEmailAttempts
{
    public function handle(): int
    {
        $reclaimed = 0;
        DocumentGovernanceEmailEnvelopeAttempt::query()->where('status', 'open')
            ->where('lease_expires_at', '<', now())->orderBy('id')->limit(100)->pluck('id')
            ->each(function (int $attemptId) use (&$reclaimed): void {
                $changed = DB::transaction(function () use ($attemptId): bool {
                    $context = DocumentGovernanceEmailEnvelopeAttempt::query()->find($attemptId);
                    if (! $context) {
                        return false;
                    }
                    $envelope = DocumentGovernanceEmailEnvelope::query()->lockForUpdate()->findOrFail($context->envelope_id);
                    $attempt = DocumentGovernanceEmailEnvelopeAttempt::query()->lockForUpdate()->findOrFail($attemptId);
                    if ($attempt->status !== GovernanceEmailAttemptStatus::Open || ! $attempt->lease_expires_at?->isPast()) {
                        return false;
                    }
                    $attempt->forceFill([
                        'status' => GovernanceEmailAttemptStatus::Abandoned,
                        'lease_expires_at' => null,
                        'completed_at' => now(),
                        'failure_category' => 'lease_expired',
                    ])->save();
                    $ceiling = (int) config('documents.governance_email_retry_ceiling', 5);
                    $terminal = $attempt->generation >= $ceiling;
                    $envelope->forceFill($terminal ? [
                        'assembly_status' => GovernanceEmailEnvelopeStatus::FailedPermanent,
                        'terminal_at' => now(),
                        'terminal_failure_category' => 'attempt_reclaim_ceiling_exhausted',
                        'last_error' => 'lease_expired',
                    ] : [
                        'assembly_status' => GovernanceEmailEnvelopeStatus::Ready,
                        'next_attempt_at' => now(),
                        'last_error' => 'lease_expired',
                    ])->save();

                    return true;
                }, 3);
                $reclaimed += (int) $changed;
            });

        return $reclaimed;
    }
}
