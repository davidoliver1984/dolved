<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\EligibilityClarificationReason;
use App\Exceptions\LocationReferenceReviewDiagnosticException;
use App\Exceptions\RetrievalException;
use App\Models\DocumentFamily;
use App\Models\OrganisationalLocation;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\RetrievalPlan;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Diagnostic-only review. Its result is never returned to the eligibility resolver. */
final readonly class LocationReferenceShadowReview
{
    public function __construct(private RetrievalClient $client) {}

    public function observe(AuthorisedKnowledgeScope $authorised, RetrievalPlan $plan, string $reference,
        OrganisationalLocation|EligibilityClarificationReason $old): void
    {
        if (config('retrieval.location_reference_shadow.enabled') !== true) {
            return;
        }
        $question = $plan->evidenceNeed['question_span']['quote'] ?? null;
        $oldDecision = $old instanceof OrganisationalLocation ? 'resolved_site' : 'clarification';
        $base = ['workspace_id' => $authorised->workspace->public_id,
            'need_id' => $plan->evidenceNeed['need_id'] ?? null,
            'reference_sha256' => hash('sha256', $reference),
            'question_sha256' => is_string($question) ? hash('sha256', $question) : null,
            'old_decision' => $oldDecision,
            'old_location_id' => $old instanceof OrganisationalLocation ? $old->public_id : null,
            'old_reason' => $old instanceof EligibilityClarificationReason ? $old->value : null];
        try {
            if (! is_string($question) || ! str_contains($question, $reference)
                || mb_strlen($reference) > 500 || mb_strlen($question) > 8000 || mb_strlen($plan->query) > 8000) {
                $this->record($base + ['shadow_decision' => 'unavailable', 'shadow_reason' => 'context_unavailable']);

                return;
            }
            $locationModels = OrganisationalLocation::query()->where('workspace_id', $authorised->workspace->id)
                ->with('aliases')->orderBy('id')->limit(201)->get();
            $familyModels = DocumentFamily::query()->where('workspace_id', $authorised->workspace->id)
                ->whereNull('tombstoned_at')->orderBy('id')->limit(501)->get(['public_id', 'name']);
            if ($locationModels->count() > 200 || $familyModels->count() > 500) {
                $this->record($base + ['shadow_decision' => 'unavailable', 'shadow_reason' => 'candidate_bound_exceeded']);

                return;
            }
            if ($locationModels->contains(fn (OrganisationalLocation $location): bool => $location->aliases->count() > 20 || mb_strlen($location->name) > 500
                || $location->aliases->contains(fn ($alias): bool => mb_strlen($alias->alias) > 500))
                || $familyModels->contains(fn (DocumentFamily $family): bool => mb_strlen($family->name) > 500)) {
                $this->record($base + ['shadow_decision' => 'unavailable', 'shadow_reason' => 'candidate_bound_exceeded']);

                return;
            }
            $locations = $locationModels->map(fn (OrganisationalLocation $location): array => [
                'location_id' => $location->public_id, 'name' => $location->name,
                'aliases' => $location->aliases->pluck('alias')->values()->all(),
            ])->all();
            $families = $familyModels->map(fn (DocumentFamily $family): array => [
                'family_id' => $family->public_id, 'title' => $family->name,
            ])->all();
            $payloadBytes = strlen(json_encode([$reference, $question, $plan->query, $locations, $families], JSON_THROW_ON_ERROR));
            if ($payloadBytes > 50000) {
                $this->record($base + ['shadow_decision' => 'unavailable', 'shadow_reason' => 'payload_bound_exceeded']);

                return;
            }
            $review = $this->client->reviewLocationReference($authorised->workspace,
                $reference, $question, $plan->query, $locations, $families);
            $selection = $review['selection'];
            $this->record($base + [
                'shadow_decision' => $selection['decision'],
                'shadow_location_id' => $selection['location_id'],
                'shadow_reference_type' => $selection['reference_type'],
                'shadow_evidence_family_id' => $selection['evidence_family_id'],
                'shadow_evidence_quote' => $selection['evidence_quote'],
                'shadow_competing_target_quote' => $selection['competing_target_quote'],
                'shadow_reason' => $selection['reason'],
                'usage' => $review['usage'],
            ]);
        } catch (LocationReferenceReviewDiagnosticException $exception) {
            $this->record($base + ['shadow_decision' => 'unavailable', 'shadow_reason' => 'review_failed',
                'shadow_failure_reason' => $exception->failureReason,
                'shadow_raw_response' => $exception->rawResponse,
                'shadow_raw_response_truncated' => $exception->rawResponseTruncated]);
        } catch (RetrievalException) {
            $this->record($base + ['shadow_decision' => 'unavailable', 'shadow_reason' => 'review_failed',
                'shadow_failure_reason' => 'validation_rejected:application_contract']);
        } catch (Throwable) {
            $this->record($base + ['shadow_decision' => 'unavailable', 'shadow_reason' => 'review_failed',
                'shadow_failure_reason' => 'unclassified_error']);
        }
    }

    /** @param array<string, mixed> $record */
    private function record(array $record): void
    {
        try {
            Log::info('retrieval.location_reference.shadow.v2', $record);
        } catch (Throwable) {
            // Shadow logging must not affect the authoritative resolver.
        }
    }
}
