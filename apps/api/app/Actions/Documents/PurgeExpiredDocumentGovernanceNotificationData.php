<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeAttempt;
use App\Models\DocumentGovernanceEmailEnvelopeMember;
use App\Models\DocumentGovernanceEmailEnvelopeMemberDecision;
use App\Models\DocumentGovernanceEvent;
use App\Models\DocumentGovernanceNotification;
use Illuminate\Support\Facades\DB;

final class PurgeExpiredDocumentGovernanceNotificationData
{
    /** @return array{notifications: int, envelopes: int, events: int} */
    public function handle(): array
    {
        $notifications = DocumentGovernanceNotification::query()->where('expires_at', '<=', now())->delete();
        $envelopes = 0;
        DocumentGovernanceEmailEnvelope::query()->whereNotNull('terminal_at')
            ->where('terminal_at', '<=', now()->subDays(400))->orderBy('id')->pluck('id')
            ->each(function (int $id) use (&$envelopes): void {
                DB::transaction(function () use ($id, &$envelopes): void {
                    $memberIds = DocumentGovernanceEmailEnvelopeMember::query()->where('envelope_id', $id)->pluck('id');
                    DocumentGovernanceEmailEnvelopeMemberDecision::query()->whereIn('envelope_member_id', $memberIds)->delete();
                    DocumentGovernanceEmailEnvelopeAttempt::query()->where('envelope_id', $id)->delete();
                    DocumentGovernanceEmailEnvelopeMember::query()->where('envelope_id', $id)->delete();
                    $envelopes += DocumentGovernanceEmailEnvelope::query()->whereKey($id)->delete();
                });
            });
        $events = DocumentGovernanceEvent::query()->where(function ($query): void {
            $query->whereNotNull('published_at')->orWhereNotNull('failed_at');
        })->where('updated_at', '<=', now()->subDays(400))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('document_governance_notifications')
                    ->whereColumn('document_governance_notifications.source_event_id', 'document_governance_events.event_id');
            })->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('document_governance_email_envelope_members')
                    ->whereColumn('document_governance_email_envelope_members.source_event_id', 'document_governance_events.event_id');
            })->delete();

        return compact('notifications', 'envelopes', 'events');
    }
}
