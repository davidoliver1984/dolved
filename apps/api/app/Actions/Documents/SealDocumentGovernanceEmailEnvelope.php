<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class SealDocumentGovernanceEmailEnvelope
{
    public function handle(int $envelopeId): ?DocumentGovernanceEmailEnvelope
    {
        return DB::transaction(function () use ($envelopeId): ?DocumentGovernanceEmailEnvelope {
            $envelope = DocumentGovernanceEmailEnvelope::query()->lockForUpdate()->findOrFail($envelopeId);
            if ($envelope->assembly_status !== GovernanceEmailEnvelopeStatus::Assembling) {
                return $envelope;
            }

            $members = $envelope->members()->orderBy('added_at')->orderBy('id')->get();
            if ($members->isEmpty()) {
                return null;
            }

            $identities = [];
            foreach ($members as $index => $member) {
                $ordinal = $index + 1;
                $member->forceFill(['ordinal' => $ordinal])->save();
                $identities[] = ['id' => $member->id, 'source_event_id' => $member->source_event_id, 'ordinal' => $ordinal];
            }
            $membershipDigest = hash('sha256', json_encode($identities, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $workspace = Workspace::query()->findOrFail($envelope->workspace_id);
            $templateKey = $envelope->category_group === 'review_reminders'
                ? 'governance.review.digest'
                : $envelope->category_group;
            $basis = [
                'template_key' => $templateKey,
                'template_version' => 1,
                'branding_configuration_identity' => 'dolved-default-v1',
                'workspace_display_name_snapshot' => $workspace->name,
                'resolved_accent_identity' => 'dolved-green-v1',
                'sealed_membership_digest' => $membershipDigest,
            ];
            $envelope->forceFill([
                ...$basis,
                'sealed_at' => now(),
                'sealed_rendering_basis_digest' => hash('sha256', json_encode($basis, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'assembly_status' => GovernanceEmailEnvelopeStatus::Ready,
            ])->save();

            return $envelope->refresh();
        }, 3);
    }
}
