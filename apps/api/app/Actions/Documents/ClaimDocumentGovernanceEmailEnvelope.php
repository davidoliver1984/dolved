<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\GovernanceEmailAttemptStatus;
use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Enums\GovernanceEmailSuppressionReason;
use App\Enums\WorkspaceRole;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeAttempt;
use App\Models\DocumentGovernanceEmailEnvelopeMember;
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
                || ($envelope->next_attempt_at !== null && $envelope->next_attempt_at->isFuture())
                || DocumentGovernanceEmailEnvelopeAttempt::query()->where('envelope_id', $envelope->id)
                    ->where('status', GovernanceEmailAttemptStatus::Open->value)->exists()) {
                return null;
            }

            $priorAttempts = DocumentGovernanceEmailEnvelopeAttempt::query()->where('envelope_id', $envelope->id)->count();
            $stopReason = $this->envelopeStopReason($envelope, $priorAttempts === 0);
            if ($stopReason !== null) {
                if ($priorAttempts === 0) {
                    $this->persistDecisions($envelope, fn (): GovernanceEmailSuppressionReason => $stopReason);
                }
                $decisionDigest = $envelope->dispatch_decision_digest ?? $this->decisionDigest($envelope);
                $envelope->forceFill([
                    'dispatch_decision_digest' => $decisionDigest,
                    'assembly_status' => GovernanceEmailEnvelopeStatus::Suppressed,
                    'suppression_reason' => $priorAttempts === 0
                        ? GovernanceEmailSuppressionReason::NoDeliverableMembers->value
                        : $stopReason->value,
                    'terminal_at' => now(),
                ])->save();

                return null;
            }

            if ($priorAttempts === 0) {
                $user = User::query()->where('public_id', $envelope->recipient_user_public_id)->firstOrFail();
                $included = $this->persistDecisions(
                    $envelope,
                    fn (DocumentGovernanceEmailEnvelopeMember $member): ?GovernanceEmailSuppressionReason => $this->memberStopReason($envelope, $member, $user),
                );
                $envelope->forceFill(['dispatch_decision_digest' => $this->decisionDigest($envelope)])->save();
                if ($included === 0) {
                    $envelope->forceFill([
                        'assembly_status' => GovernanceEmailEnvelopeStatus::Suppressed,
                        'suppression_reason' => GovernanceEmailSuppressionReason::NoDeliverableMembers->value,
                        'terminal_at' => now(),
                    ])->save();

                    return null;
                }
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

    private function envelopeStopReason(
        DocumentGovernanceEmailEnvelope $envelope,
        bool $fullPreflight,
    ): ?GovernanceEmailSuppressionReason {
        $settings = WorkspaceNotificationSetting::query()->where('workspace_id', $envelope->workspace_id)->first();
        if ($settings && ! $settings->email_delivery_enabled) {
            return GovernanceEmailSuppressionReason::WorkspaceEmailDisabled;
        }
        $user = User::query()->where('public_id', $envelope->recipient_user_public_id)->first();
        if (! $user || $user->disabled_at !== null) {
            return GovernanceEmailSuppressionReason::RecipientDisabled;
        }
        if ($user->email_verified_at === null) {
            return GovernanceEmailSuppressionReason::RecipientUnverified;
        }
        if (! WorkspaceMembership::query()->where('workspace_id', $envelope->workspace_id)->where('user_id', $user->id)->exists()) {
            return GovernanceEmailSuppressionReason::MembershipRemoved;
        }
        $preference = UserNotificationPreference::query()->where('user_id', $user->id)
            ->where('category_group', $envelope->category_group)->first();
        if ($preference && ! $preference->email_enabled) {
            return GovernanceEmailSuppressionReason::PersonalOptOut;
        }
        if ($fullPreflight && ! $preference && $settings && ! $settings->default_email_enabled) {
            return GovernanceEmailSuppressionReason::PersonalOptOut;
        }

        return null;
    }

    private function memberStopReason(
        DocumentGovernanceEmailEnvelope $envelope,
        DocumentGovernanceEmailEnvelopeMember $member,
        User $user,
    ): ?GovernanceEmailSuppressionReason {
        $notification = $member->notification;
        if (! $notification instanceof DocumentGovernanceNotification
            || $notification->workspace_id !== $envelope->workspace_id
            || $notification->recipient_user_public_id !== $envelope->recipient_user_public_id
            || $member->recipient_user_public_id !== $envelope->recipient_user_public_id
            || $notification->source_event_id !== $member->source_event_id) {
            return GovernanceEmailSuppressionReason::AuthorityLost;
        }

        $event = $notification->event_key;
        $parameters = $notification->parameters;
        if (in_array($event->value, ['governance.review.due_soon', 'governance.review.overdue', 'governance.authority.blocked'], true)) {
            $familyPublicId = $parameters['document_family_public_id'] ?? null;
            if (! is_string($familyPublicId) || ! Str::isUuid($familyPublicId)) {
                return GovernanceEmailSuppressionReason::AuthorityLost;
            }
            $familyOwner = DocumentFamily::query()->where('workspace_id', $envelope->workspace_id)
                ->where('public_id', $familyPublicId)
                ->where('owner_user_id', $user->id)->exists();

            return $familyOwner || $this->isAdministrator($envelope->workspace_id, $user->id)
                ? null
                : GovernanceEmailSuppressionReason::AuthorityLost;
        }
        if ($event->value === 'governance.ownership.reassignment_required') {
            return $this->isAdministrator($envelope->workspace_id, $user->id)
                ? null
                : GovernanceEmailSuppressionReason::AuthorityLost;
        }
        if ($event->value === 'import.batch.completed_with_exceptions'
            && ($parameters['initiating_user_public_id'] ?? null) !== $user->public_id) {
            return $this->isAdministrator($envelope->workspace_id, $user->id)
                ? null
                : GovernanceEmailSuppressionReason::AuthorityLost;
        }

        return null;
    }

    /**
     * @param  callable(DocumentGovernanceEmailEnvelopeMember): (?GovernanceEmailSuppressionReason)  $reasonFor
     */
    private function persistDecisions(DocumentGovernanceEmailEnvelope $envelope, callable $reasonFor): int
    {
        $included = 0;
        foreach ($envelope->members()->with('notification')->orderBy('ordinal')->get() as $member) {
            $reason = $reasonFor($member);
            if ($reason === null) {
                $included++;
            }
            DocumentGovernanceEmailEnvelopeMemberDecision::query()->create([
                'envelope_member_id' => $member->id,
                'decision' => $reason === null ? 'included' : 'suppressed',
                'suppression_reason' => $reason?->value,
                'decided_at' => now(),
            ]);
        }

        return $included;
    }

    private function isAdministrator(int $workspaceId, int $userId): bool
    {
        return WorkspaceMembership::query()->where('workspace_id', $workspaceId)->where('user_id', $userId)
            ->whereIn('role', [WorkspaceRole::Owner->value, WorkspaceRole::Admin->value])->exists();
    }

    private function decisionDigest(DocumentGovernanceEmailEnvelope $envelope): string
    {
        $decisions = $envelope->members()->with('decision')->orderBy('ordinal')->get()->map(fn ($member): array => [
            'member_id' => $member->id,
            'ordinal' => $member->ordinal,
            'decision' => $member->decision?->decision,
            'suppression_reason' => $member->decision?->suppression_reason,
        ])->all();
        $included = array_values(array_map(
            fn (array $decision): array => ['member_id' => $decision['member_id'], 'ordinal' => $decision['ordinal']],
            array_filter($decisions, fn (array $decision): bool => $decision['decision'] === 'included'),
        ));

        return hash('sha256', json_encode([
            'members' => $decisions,
            'included' => $included,
            'sealed_rendering_basis_digest' => $envelope->sealed_rendering_basis_digest,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
