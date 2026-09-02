<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\GovernanceEmailAttemptStatus;
use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeAttempt;
use Illuminate\Support\Facades\DB;

final class CompleteDocumentGovernanceEmailAttempt
{
    public function handle(int $attemptId, string $token, int $generation, string $providerMessageId): bool
    {
        return DB::transaction(function () use ($attemptId, $token, $generation, $providerMessageId): bool {
            $context = DocumentGovernanceEmailEnvelopeAttempt::query()->findOrFail($attemptId);
            $envelope = DocumentGovernanceEmailEnvelope::query()->lockForUpdate()->findOrFail($context->envelope_id);
            $attempt = DocumentGovernanceEmailEnvelopeAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            if (! $this->owns($envelope, $attempt, $token, $generation)) {
                return false;
            }
            $completedAt = now();
            $attempt->forceFill([
                'status' => GovernanceEmailAttemptStatus::Accepted,
                'provider_message_id' => $providerMessageId,
                'lease_expires_at' => null,
                'completed_at' => $completedAt,
            ])->save();
            $envelope->forceFill([
                'assembly_status' => GovernanceEmailEnvelopeStatus::Sent,
                'provider_message_id' => $providerMessageId,
                'dispatched_at' => $completedAt,
                'terminal_at' => $completedAt,
            ])->save();

            return true;
        }, 3);
    }

    private function owns(DocumentGovernanceEmailEnvelope $envelope, DocumentGovernanceEmailEnvelopeAttempt $attempt, string $token, int $generation): bool
    {
        return $envelope->assembly_status === GovernanceEmailEnvelopeStatus::Dispatching
            && $attempt->status === GovernanceEmailAttemptStatus::Open
            && $attempt->generation === $generation
            && hash_equals($attempt->attempt_token, $token)
            && $attempt->sealed_rendering_basis_digest_verified === $envelope->sealed_rendering_basis_digest
            && $attempt->dispatch_decision_digest_verified === $envelope->dispatch_decision_digest;
    }
}
