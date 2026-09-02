<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DocumentGovernanceEventKey;
use App\Jobs\ProjectDocumentGovernanceNotifications;
use App\Models\DocumentGovernanceEvent;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class RecordDocumentGovernanceEvent
{
    /** @param array<string, bool|float|int|string|null|list<bool|float|int|string|null>> $payload */
    public function record(
        Workspace $workspace,
        DocumentGovernanceEventKey $eventKey,
        string $correlationId,
        string $occurrenceKey,
        array $payload,
    ): DocumentGovernanceEvent {
        $this->assertSafePayload($payload);

        return DB::transaction(function () use ($workspace, $eventKey, $correlationId, $occurrenceKey, $payload): DocumentGovernanceEvent {
            $inserted = DocumentGovernanceEvent::query()->insertOrIgnore([
                'event_id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'event_key' => $eventKey->value,
                'event_version' => 1,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'correlation_id' => $correlationId,
                'occurrence_key' => $occurrenceKey,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $event = DocumentGovernanceEvent::query()
                ->where('workspace_id', $workspace->id)
                ->where('occurrence_key', $occurrenceKey)
                ->firstOrFail();
            if ($inserted === 1) {
                ProjectDocumentGovernanceNotifications::dispatch($event->id);
            }

            return $event;
        });
    }

    private function assertSafePayload(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (! is_string($key) || strlen($key) > 64) {
                throw new InvalidArgumentException('Governance event payload keys must be bounded strings.');
            }
            $values = is_array($value) ? $value : [$value];
            foreach ($values as $scalar) {
                if (! is_null($scalar) && ! is_bool($scalar) && ! is_int($scalar) && ! is_float($scalar) && ! is_string($scalar)) {
                    throw new InvalidArgumentException('Governance event payloads accept safe scalar values only.');
                }
                if (is_string($scalar) && mb_strlen($scalar) > 255) {
                    throw new InvalidArgumentException('Governance event payload strings must be bounded.');
                }
            }
        }
    }
}
