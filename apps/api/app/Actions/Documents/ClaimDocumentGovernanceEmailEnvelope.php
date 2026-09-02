<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\GovernanceEmailAttemptStatus;
use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Enums\WorkspaceRole;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeAttempt;
use App\Models\DocumentGovernanceEmailEnvelopeMemberDecision;
use App\Models\DocumentGovernanceNotification;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceNotificationSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClaimDocumentGovernanceEmailEnvelope
{
    /** @return array{envelope: DocumentGovernanceEmailEnvelope, attempt: DocumentGovernanceEmailEnvelopeAttempt}|null */
    public function handle(int $envelopeId): ?array
    {
        $lease = (int) config('documents.governance_email_attempt_lease_seconds', 120);
        $providerTimeout = (int) config('documents.governance_email_provider_timeout_seconds', 30);
        $reportingMargin = (int) config('documents.governance_email_result_margin_seconds', 30);
        if ($lease < $providerTimeout + $reportingMargin) {
            throw new \LogicException('Governance email attempt lease must cover provider timeout and result margin.');
        }

        return DB::transaction(function () use ($envelopeId): ?array {
            $envelope = DocumentGovernanceEmailEnvelope::query()->lockForUpdate()->findOrFail($envelopeId);
            if ($envelope->assembly_status !== GovernanceEmailEnvelopeStatus::Ready
                || DocumentGovernanceEmailEnvelopeAttempt::query()->where('envelope_id', $envelope->id)->where('status', 'open')->exists()) {
                return null;
            }

            $priorAttempts = DocumentGovernanceEmailEnvelopeAttempt::query()->where('envelope_id', $envelope->id)->count();
            $stopReason = $this->stopReason($envelope, $priorAttempts === 0);
            if ($stopReason !== null) {
                if ($priorAttempts === 0) {
                    foreach ($envelope->members()->get() as $member) {
                        DocumentGovernanceEmailEnvelopeMemberDecision::query()->create([
                            'envelope_member_id' => $member->id,
                            'decision' => 'suppressed',
                            'suppression_reason' => $stopReason,
                            'decided_at' => now(),
                        ]);
                    }
                }
                $decisionDigest = $envelope->dispatch_decision_digest ?? $this->decisionDigest($envelope);
                $envelope->forceFill([
                    'dispatch_decision_digest' => $decisionDigest,
                    'assembly_status' => GovernanceEmailEnvelopeStatus::Suppressed,
                    'suppression_reason' => $priorAttempts === 0 ? 'no_deliverable_members' : $stopReason,
                    'terminal_at' => now(),
                ])->save();

                return null;
            }

            if ($priorAttempts === 0) {
                foreach ($envelope->members()->get() as $member) {
                    DocumentGovernanceEmailEnvelopeMemberDecision::query()->create([
                        'envelope_member_id' => $member->id,
                        'decision' => 'included',
                        'suppression_reason' => null,
                        'decided_at' => now(),
                    ]);
                }
                $envelope->forceFill(['dispatch_decision_digest' => $this->decisionDigest($envelope)])->save();
            }

            $generation = $priorAttempts + 1;
            $attempt = DocumentGovernanceEmailEnvelopeAttempt::query()->create([
                'envelope_id' => $envelope->id,
                'workspace_id' => $envelope->workspace_id,
                'generation' => $generation,
                'attempt_token' => (string) Str::uuid(),
                'status' => GovernanceEmailAttemptStatus::Open,
                'lease_expires_at' => now()->addSeconds((int) config('documents.governance_email_attempt_lease_seconds', 120)),
                'opened_at' => now(),
                'provider_idempotency_key_used' => $envelope->envelope_key,
                'sealed_rendering_basis_digest_verified' => $envelope->sealed_rendering_basis_digest,
                'dispatch_decision_digest_verified' => $envelope->dispatch_decision_digest,
            ]);
            $envelope->forceFill([
                'assembly_status' => GovernanceEmailEnvelopeStatus::Dispatching,
                'attempt_count' => $generation,
                'next_attempt_at' => null,
            ])->save();

            return ['envelope' => $envelope->refresh(), 'attempt' => $attempt];
        }, 3);
    }

    private function stopReason(DocumentGovernanceEmailEnvelope $envelope, bool $fullPreflight): ?string
    {
        $settings = WorkspaceNotificationSetting::query()->where('workspace_id', $envelope->workspace_id)->first();
        if ($settings && ! $settings->email_delivery_enabled) {
            return 'workspace_email_disabled';
        }
        $user = User::query()->where('public_id', $envelope->recipient_user_public_id)->first();
        if (! $user || $user->disabled_at !== null) {
            return 'recipient_disabled';
        }
        if ($user->email_verified_at === null) {
            return 'recipient_unverified';
        }
        if (! WorkspaceMembership::query()->where('workspace_id', $envelope->workspace_id)->where('user_id', $user->id)->exists()) {
            return 'membership_removed';
        }
        $preference = UserNotificationPreference::query()->where('user_id', $user->id)
            ->where('category_group', $envelope->category_group)->first();
        if ($preference && ! $preference->email_enabled) {
            return 'personal_opt_out';
        }
        if ($fullPreflight && ! $preference && $settings && ! $settings->default_email_enabled) {
            return 'personal_opt_out';
        }
        if ($fullPreflight && ! $this->hasRequiredAuthority($envelope, $user)) {
            return 'authority_lost';
        }

        return null;
    }

    private function hasRequiredAuthority(DocumentGovernanceEmailEnvelope $envelope, User $user): bool
    {
        $notificationId = $envelope->members()->value('notification_id');
        $notification = $notificationId ? DocumentGovernanceNotification::query()->find($notificationId) : null;
        if (! $notification) {
            return false;
        }
        $event = $notification->event_key;
        $parameters = $notification->parameters;
        if (in_array($event->value, ['governance.review.due_soon', 'governance.review.overdue', 'governance.authority.blocked'], true)) {
            $familyOwner = DocumentFamily::query()->where('workspace_id', $envelope->workspace_id)
                ->where('public_id', (string) ($parameters['document_family_public_id'] ?? ''))
                ->where('owner_user_id', $user->id)->exists();

            return $familyOwner || $this->isAdministrator($envelope->workspace_id, $user->id);
        }
        if ($event->value === 'governance.ownership.reassignment_required') {
            return $this->isAdministrator($envelope->workspace_id, $user->id);
        }
        if ($event->value === 'import.batch.completed_with_exceptions'
            && ($parameters['initiating_user_public_id'] ?? null) !== $user->public_id) {
            return $this->isAdministrator($envelope->workspace_id, $user->id);
        }

        return true;
    }

    private function isAdministrator(int $workspaceId, int $userId): bool
    {
        return WorkspaceMembership::query()->where('workspace_id', $workspaceId)->where('user_id', $userId)
            ->whereIn('role', [WorkspaceRole::Owner->value, WorkspaceRole::Admin->value])->exists();
    }

    private function decisionDigest(DocumentGovernanceEmailEnvelope $envelope): string
    {
        $decisions = $envelope->members()->with('decision')->get()->map(fn ($member): array => [
            'member_id' => $member->id,
            'ordinal' => $member->ordinal,
            'decision' => $member->decision?->decision,
            'suppression_reason' => $member->decision?->suppression_reason,
        ])->all();

        return hash('sha256', json_encode([
            'members' => $decisions,
            'sealed_rendering_basis_digest' => $envelope->sealed_rendering_basis_digest,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
