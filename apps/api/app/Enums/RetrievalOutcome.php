<?php

declare(strict_types=1);

namespace App\Enums;

enum RetrievalOutcome: string
{
    case EvidenceFound = 'evidence_found';
    case NoEligibleEvidence = 'no_eligible_evidence';
    case NoRetrievalCandidates = 'no_retrieval_candidates';
    case TemporalScopeUnresolved = 'temporal_scope_unresolved';
    case ComparisonScopeIncomplete = 'comparison_scope_incomplete';
    case ClarificationRequired = 'clarification_required';
    case RetrievalFailed = 'retrieval_failed';
}
