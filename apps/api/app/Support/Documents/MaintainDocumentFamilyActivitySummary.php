<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\DocumentExtractionProjectionGeneration;
use App\Models\DocumentFamily;
use App\Models\DocumentFamilyActivitySummary;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class MaintainDocumentFamilyActivitySummary
{
    public const GOVERNANCE_ACTIONS = [
        'approved',
        'document_family_metadata_backfilled',
        'document_family_metadata_updated',
        'document_family_owner_backfilled',
        'document_family_renamed',
        'document_family_tags_updated',
        'document_family_title_reinterpreted',
        'historical_timestamps_corrected',
        'rescheduled',
        'withdrawn',
    ];

    public function record(DocumentFamily $family, CarbonInterface $occurredAt): void
    {
        $timestamp = CarbonImmutable::instance($occurredAt);
        $now = now();
        DB::table('document_family_activity_summary')->insertOrIgnore([
            'family_id' => $family->id,
            'last_meaningful_update' => $timestamp,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('document_family_activity_summary')
            ->where('family_id', $family->id)
            ->where('last_meaningful_update', '<', $timestamp)
            ->update(['last_meaningful_update' => $timestamp, 'updated_at' => $now]);
    }

    public function rebuild(DocumentFamily $family): CarbonImmutable
    {
        $locked = DocumentFamily::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
        $latest = CarbonImmutable::instance($locked->created_at);

        $candidate = $locked->documents()->max('created_at');
        $latest = $this->later($latest, $candidate);
        $candidate = $locked->governanceAuditEvents()->whereIn('action', self::GOVERNANCE_ACTIONS)->max('occurred_at');
        $latest = $this->later($latest, $candidate);
        $candidate = DB::table('ingestion_audit_events')
            ->join('documents', 'documents.id', '=', 'ingestion_audit_events.document_id')
            ->where('documents.document_family_id', $locked->id)
            ->where(function ($query): void {
                $query->where(fn ($inner) => $inner->where('action', 'publication_completed')->where('outcome', 'indexed'))
                    ->orWhere(fn ($inner) => $inner->where('action', 'processing_failed')->where('outcome', 'failed'));
            })->max('ingestion_audit_events.occurred_at');
        $latest = $this->later($latest, $candidate);
        $candidate = DB::table('document_content_clone_operations')
            ->join('documents', 'documents.id', '=', 'document_content_clone_operations.target_document_id')
            ->where('documents.document_family_id', $locked->id)
            ->max('document_content_clone_operations.authorised_at');
        $latest = $this->later($latest, $candidate);

        foreach (DocumentExtractionProjectionGeneration::query()
            ->with('document')
            ->whereHas('document', fn ($query) => $query->where('document_family_id', $locked->id))
            ->where('expected_warning_count', '>', 0)
            ->whereNotNull('published_at')->get() as $generation) {
            $at = CarbonImmutable::instance($generation->published_at);
            if ($this->warningWasCurrent($locked, $generation->document, $at)) {
                $latest = $this->later($latest, $at);
            }
        }

        $summary = DocumentFamilyActivitySummary::query()->firstOrNew(['family_id' => $locked->id]);
        $summary->last_meaningful_update = $latest;
        $summary->save();

        return $latest;
    }

    private function later(CarbonImmutable $current, mixed $candidate): CarbonImmutable
    {
        if ($candidate === null) {
            return $current;
        }
        $value = CarbonImmutable::parse((string) $candidate);

        return $value->greaterThan($current) ? $value : $current;
    }

    public function warningWasCurrent(DocumentFamily $family, Document $document, CarbonInterface $at): bool
    {
        try {
            return app(DocumentAuthorityTimeline::class)->resolve($family, $at)?->id === $document->id;
        } catch (DocumentGovernanceException) {
            return false;
        }
    }
}
