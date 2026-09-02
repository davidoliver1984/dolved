<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeMember;
use App\Models\DocumentGovernanceNotification;
use App\Support\Documents\GovernanceEmailCategories;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AssembleDocumentGovernanceEmailEnvelope
{
    public function __construct(private SealDocumentGovernanceEmailEnvelope $seal) {}

    public function handle(DocumentGovernanceNotification $notification): ?DocumentGovernanceEmailEnvelope
    {
        if (! GovernanceEmailCategories::eligible($notification->event_key)) {
            return null;
        }

        if (DocumentGovernanceEmailEnvelopeMember::query()->where('notification_id', $notification->id)->exists()) {
            return DocumentGovernanceEmailEnvelope::query()
                ->whereHas('members', fn ($query) => $query->where('notification_id', $notification->id))->first();
        }

        $digest = GovernanceEmailCategories::digest($notification->event_key);
        $category = GovernanceEmailCategories::group($notification->event_key);
        $date = $digest ? $this->effectiveDigestDate() : null;
        for ($dayOffset = 0; $dayOffset < 3; $dayOffset++) {
            $effectiveDate = $date === null ? null : CarbonImmutable::parse($date, 'UTC')->addDays($dayOffset)->toDateString();
            $key = $digest
                ? hash('sha256', implode('|', [$notification->recipient_user_public_id, $category, $effectiveDate]))
                : hash('sha256', 'immediate|'.$notification->source_event_id.'|'.$notification->recipient_user_public_id);
            $envelopeId = $this->append($notification, $category, $effectiveDate, $key);
            if ($envelopeId !== null) {
                return $digest
                    ? DocumentGovernanceEmailEnvelope::query()->findOrFail($envelopeId)
                    : $this->seal->handle($envelopeId);
            }
        }

        throw new \LogicException('No eligible governance digest envelope remained after bounded late-arrival routing.');
    }

    private function append(DocumentGovernanceNotification $notification, string $category, ?string $date, string $key): ?int
    {
        return DB::transaction(function () use ($notification, $category, $date, $key): ?int {
            DocumentGovernanceEmailEnvelope::query()->insertOrIgnore([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $notification->workspace_id,
                'recipient_user_public_id' => $notification->recipient_user_public_id,
                'category_group' => $category,
                'digest_date' => $date,
                'envelope_key' => $key,
                'assembly_status' => GovernanceEmailEnvelopeStatus::Assembling->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $envelope = DocumentGovernanceEmailEnvelope::query()
                ->where('workspace_id', $notification->workspace_id)->where('envelope_key', $key)
                ->lockForUpdate()->firstOrFail();
            if ($envelope->assembly_status !== GovernanceEmailEnvelopeStatus::Assembling) {
                return null;
            }
            DocumentGovernanceEmailEnvelopeMember::query()->insertOrIgnore([
                'envelope_id' => $envelope->id,
                'notification_id' => $notification->id,
                'source_event_id' => $notification->source_event_id,
                'recipient_user_public_id' => $notification->recipient_user_public_id,
                'added_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $envelope->id;
        }, 3);
    }

    private function effectiveDigestDate(): string
    {
        $now = CarbonImmutable::now('UTC');
        [$hour, $minute] = array_map('intval', explode(':', (string) config('documents.governance_digest_cutoff_utc', '16:00')));
        $cutoff = $now->startOfDay()->setTime($hour, $minute);

        return ($now->greaterThanOrEqualTo($cutoff) ? $now->addDay() : $now)->toDateString();
    }
}
