<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentGovernanceEventKey;
use App\Jobs\DispatchDocumentGovernanceEmail;
use App\Models\DocumentGovernanceEvent;
use App\Models\DocumentGovernanceEventProjection;
use App\Models\DocumentGovernanceNotification;
use App\Models\DocumentGovernanceNotificationProjectionReceipt;
use App\Models\User;
use App\Support\Documents\GovernanceEmailCategories;
use App\Support\Documents\ResolveDocumentGovernanceRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class ProjectDocumentGovernanceEvent
{
    public function __construct(private ResolveDocumentGovernanceRecipients $recipients) {}

    public function handle(DocumentGovernanceEvent $source): void
    {
        $event = DB::transaction(function () use ($source): ?DocumentGovernanceEvent {
            $event = DocumentGovernanceEvent::query()->lockForUpdate()->findOrFail($source->id);
            if ($event->published_at !== null || $event->failed_at !== null) {
                return null;
            }
            $event->forceFill([
                'claimed_at' => now(),
                'claim_token' => (string) Str::uuid(),
                'attempt_count' => $event->attempt_count + 1,
                'next_attempt_at' => null,
            ])->save();

            return $event->refresh();
        }, 3);
        if ($event === null) {
            return;
        }

        try {
            $this->project($event);
        } catch (Throwable $error) {
            $this->recordFailure($event, $error);

            throw $error;
        }
    }

    private function project(DocumentGovernanceEvent $source): void
    {
        DB::transaction(function () use ($source): void {
            $event = DocumentGovernanceEvent::query()->lockForUpdate()->findOrFail($source->id);
            if ($event->published_at !== null || $event->failed_at !== null) {
                return;
            }

            DocumentGovernanceEventProjection::query()->insertOrIgnore([
                'workspace_id' => $event->workspace_id,
                'source_event_id' => $event->event_id,
                'state' => 'resolving',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $projection = DocumentGovernanceEventProjection::query()
                ->where('workspace_id', $event->workspace_id)
                ->where('source_event_id', $event->event_id)
                ->lockForUpdate()->firstOrFail();
            $projection->forceFill(['attempt_count' => $projection->attempt_count + 1])->save();

            if ($projection->state === 'resolving') {
                $recipients = $this->recipients->handle($event);
                foreach ($recipients as $recipient) {
                    DocumentGovernanceNotificationProjectionReceipt::query()->insertOrIgnore([
                        'workspace_id' => $event->workspace_id,
                        'event_projection_id' => $projection->id,
                        'recipient_user_public_id' => $recipient['user_public_id'],
                        'outcome' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $identities = array_column($recipients, 'user_public_id');
                sort($identities, SORT_STRING);
                $projection->forceFill([
                    'state' => 'projecting',
                    'resolved_recipient_set_digest' => hash('sha256', json_encode($identities, JSON_THROW_ON_ERROR)),
                ])->save();
            }

            $receipts = DocumentGovernanceNotificationProjectionReceipt::query()
                ->where('event_projection_id', $projection->id)
                ->where('outcome', 'pending')->lockForUpdate()->get();
            foreach ($receipts as $receipt) {
                $user = User::query()->where('public_id', $receipt->recipient_user_public_id)->first();
                $membershipId = $user?->workspaceMemberships()
                    ->where('workspace_id', $event->workspace_id)->value('id');
                if (! $user || $user->disabled_at !== null || ! $membershipId) {
                    $receipt->forceFill([
                        'outcome' => 'suppressed',
                        'suppression_reason' => ! $user || $user->disabled_at !== null ? 'recipient_disabled' : 'membership_removed',
                        'resolved_at' => now(),
                    ])->save();

                    continue;
                }

                $publicId = (string) Str::uuid();
                DocumentGovernanceNotification::query()->insertOrIgnore([
                    'public_id' => $publicId,
                    'workspace_id' => $event->workspace_id,
                    'recipient_user_id' => $user->id,
                    'recipient_user_public_id' => $user->public_id,
                    'recipient_workspace_membership_id' => $membershipId,
                    'event_key' => $event->event_key->value,
                    'source_event_id' => $event->event_id,
                    'template_key' => $event->event_key->value,
                    'template_version' => 1,
                    'parameters' => json_encode($event->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    'severity' => $this->severity($event->event_key),
                    'target_kind' => $event->payload['target_kind'] ?? null,
                    'target_public_id' => $event->payload['target_public_id'] ?? null,
                    'target_display_label' => $event->payload['target_display_label'] ?? null,
                    'expires_at' => $this->severity($event->event_key) === 'info' ? now()->addDays(90) : now()->addDays(365),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $notificationId = DocumentGovernanceNotification::query()
                    ->where('workspace_id', $event->workspace_id)
                    ->where('recipient_user_public_id', $user->public_id)
                    ->where('source_event_id', $event->event_id)
                    ->value('public_id');
                $notification = DocumentGovernanceNotification::query()->where('public_id', $notificationId)->firstOrFail();
                if (GovernanceEmailCategories::eligible($event->event_key)) {
                    DispatchDocumentGovernanceEmail::dispatch($notification->id);
                }
                $receipt->forceFill([
                    'outcome' => 'notification_created',
                    'notification_public_id' => $notificationId,
                    'resolved_at' => now(),
                ])->save();
            }

            $projection->forceFill(['state' => 'completed', 'completed_at' => now()])->save();
            $event->forceFill([
                'published_at' => now(),
                'claim_token' => null,
                'last_error' => null,
                'next_attempt_at' => null,
            ])->save();
        }, 3);
    }

    private function recordFailure(DocumentGovernanceEvent $source, Throwable $error): void
    {
        DB::transaction(function () use ($source, $error): void {
            $event = DocumentGovernanceEvent::query()->lockForUpdate()->findOrFail($source->id);
            if ($event->published_at !== null || $event->failed_at !== null) {
                return;
            }

            $terminal = $event->attempt_count >= 5;
            $event->forceFill([
                'claim_token' => null,
                'last_error' => mb_substr(class_basename($error), 0, 255),
                'next_attempt_at' => $terminal ? null : now()->addSeconds(30),
                'failed_at' => $terminal ? now() : null,
            ])->save();

            DocumentGovernanceEventProjection::query()
                ->where('workspace_id', $event->workspace_id)
                ->where('source_event_id', $event->event_id)
                ->update([
                    'last_error' => mb_substr(class_basename($error), 0, 255),
                    'updated_at' => now(),
                ]);
        }, 3);
    }

    private function severity(DocumentGovernanceEventKey $key): string
    {
        return match ($key) {
            DocumentGovernanceEventKey::GovernanceOwnershipReassignmentRequired => 'action_required',
            DocumentGovernanceEventKey::ImportBatchCompletedWithExceptions,
            DocumentGovernanceEventKey::PromotionFailed,
            DocumentGovernanceEventKey::GovernanceAuthorityBlocked,
            DocumentGovernanceEventKey::GovernanceReviewOverdue,
            DocumentGovernanceEventKey::ApplicabilitySuccessorFailed,
            DocumentGovernanceEventKey::BulkOperationCompletedWithExceptions,
            DocumentGovernanceEventKey::BulkOperationFailedBeforeExecution,
            DocumentGovernanceEventKey::DeletionOperationStuckOrFailed => 'warning',
            default => 'info',
        };
    }
}
