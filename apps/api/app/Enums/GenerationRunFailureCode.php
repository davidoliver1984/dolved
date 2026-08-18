<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationRunFailureCode: string
{
    case ContextualisationFailed = 'contextualisation_failed';
    case RetrievalFailed = 'retrieval_failed';
    case RetrievalScopeUnresolved = 'retrieval_scope_unresolved';
    case GenerationContextBudgetExceeded = 'generation_context_budget_exceeded';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderResponseTimeout = 'provider_response_timeout';
    case RunTimeout = 'run_timeout';
    case ProviderContractFailure = 'provider_contract_failure';
    case GenerationContractInvalid = 'generation_contract_invalid';
    case DeterministicValidationFailed = 'deterministic_validation_failed';
    case PersistenceFailed = 'persistence_failed';
    case InternalFailure = 'internal_failure';
}
