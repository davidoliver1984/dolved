<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\GovernanceEmailAttemptStatus;
use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeAttempt;
use Illuminate\Support\Facades\DB;

final class VerifyDocumentGovernanceEmailAttempt
{
    public function handle(int $attemptId, string $token, int $generation): ?DocumentGovernanceEmailEnvelopeAttempt
    {
        return DB::transaction(function () use ($attemptId, $token, $generation): ?DocumentGovernanceEmailEnvelopeAttempt {
            $context = DocumentGovernanceEmailEnvelopeAttempt::query()->findOrFail($attemptId);
            $envelope = DocumentGovernanceEmailEnvelope::query()->lockForUpdate()->findOrFail($context->envelope_id);
            $attempt = DocumentGovernanceEmailEnvelopeAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            if ($envelope->assembly_status !== GovernanceEmailEnvelopeStatus::Dispatching
                || $attempt->status !== GovernanceEmailAttemptStatus::Open
                || ! hash_equals($attempt->attempt_token, $token)
                || $attempt->generation !== $generation
                || $attempt->lease_expires_at?->isPast()) {
                return null;
            }

            if ($attempt->sealed_rendering_basis_digest_verified !== $envelope->sealed_rendering_basis_digest
                || $attempt->dispatch_decision_digest_verified !== $envelope->dispatch_decision_digest) {
                $attempt->forceFill([
                    'status' => GovernanceEmailAttemptStatus::FailedPermanent,
                    'lease_expires_at' => null,
                    'completed_at' => now(),
                    'failure_category' => 'rendering_integrity_failure',
                ])->save();
                $envelope->forceFill([
                    'assembly_status' => GovernanceEmailEnvelopeStatus::FailedPermanent,
                    'terminal_at' => now(),
                    'terminal_failure_category' => 'rendering_integrity_failure',
                ])->save();

                return null;
            }

            $attempt->forceFill([
                'lease_expires_at' => now()->addSeconds((int) config('documents.governance_email_attempt_lease_seconds', 120)),
            ])->save();

            return $attempt->refresh();
        }, 3);
    }
}
