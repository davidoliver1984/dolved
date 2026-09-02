<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\GovernanceEmailAttemptStatus;
use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeAttempt;
use Illuminate\Support\Facades\DB;

final class FailDocumentGovernanceEmailAttempt
{
    public function handle(int $attemptId, string $token, int $generation, string $failureCategory, bool $retryable): bool
    {
        return DB::transaction(function () use ($attemptId, $token, $generation, $failureCategory, $retryable): bool {
            $context = DocumentGovernanceEmailEnvelopeAttempt::query()->findOrFail($attemptId);
            $envelope = DocumentGovernanceEmailEnvelope::query()->lockForUpdate()->findOrFail($context->envelope_id);
            $attempt = DocumentGovernanceEmailEnvelopeAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            if ($envelope->assembly_status !== GovernanceEmailEnvelopeStatus::Dispatching
                || $attempt->status !== GovernanceEmailAttemptStatus::Open
                || $attempt->generation !== $generation
                || ! hash_equals($attempt->attempt_token, $token)) {
                return false;
            }
            $ceiling = (int) config('documents.governance_email_retry_ceiling', 5);
            $terminal = ! $retryable || $attempt->generation >= $ceiling;
            $attempt->forceFill([
                'status' => $retryable ? GovernanceEmailAttemptStatus::FailedRetryable : GovernanceEmailAttemptStatus::FailedPermanent,
                'lease_expires_at' => null,
                'completed_at' => now(),
                'failure_category' => $failureCategory,
            ])->save();
            $envelope->forceFill($terminal ? [
                'assembly_status' => GovernanceEmailEnvelopeStatus::FailedPermanent,
                'terminal_at' => now(),
                'terminal_failure_category' => $retryable ? 'retry_ceiling_exhausted' : 'provider_permanent_failure',
                'last_error' => $failureCategory,
            ] : [
                'assembly_status' => GovernanceEmailEnvelopeStatus::Ready,
                'next_attempt_at' => now()->addSeconds(30),
                'last_error' => $failureCategory,
            ])->save();

            return true;
        }, 3);
    }
}
