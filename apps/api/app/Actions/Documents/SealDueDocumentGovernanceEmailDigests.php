<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Jobs\DispatchDocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelope;

final readonly class SealDueDocumentGovernanceEmailDigests
{
    public function __construct(private SealDocumentGovernanceEmailEnvelope $seal) {}

    public function handle(): int
    {
        $sealed = 0;
        DocumentGovernanceEmailEnvelope::query()
            ->where('assembly_status', GovernanceEmailEnvelopeStatus::Assembling->value)
            ->whereNotNull('digest_date')
            ->whereDate('digest_date', '<=', today('UTC'))
            ->orderBy('id')
            ->chunkById(100, function ($envelopes) use (&$sealed): void {
                foreach ($envelopes as $envelope) {
                    $ready = $this->seal->handle($envelope->id);
                    if ($ready?->assembly_status === GovernanceEmailEnvelopeStatus::Ready) {
                        DispatchDocumentGovernanceEmailEnvelope::dispatch($ready->id);
                        $sealed++;
                    }
                }
            });

        return $sealed;
    }
}
