# Location-reference shadow review v2

This signed, disabled-by-default diagnostic request preserves v1's workspace-
scoped candidate lists, exact/alias fast path, bounded model call, and strict
separation from authoritative eligibility. The request and response carry
`contract_version: 2`. The review result is logged but is never used to select
an eligible location, document, or answer.

The response selection adds `competing_target_quote` (nullable). It must be
null for `resolved_site`, `unresolved_or_ambiguous`, and `not_a_site` with
`reference_type: document_or_record`. For any other `not_a_site` role
(`organisation`, `answer_value`, `incidental_phrase`), it must be an exact
substring of the supplied question naming what the question actually asks
about instead of a site. Python and Laravel both require 8–300 characters,
at least half the reference's length (rounded up), and no case-insensitive
occurrence of the original reference within the target quote. A larger clause
that merely contains the reference cannot serve as its own competing target.
The existing `evidence_quote` and, for documents, authorised family-title
checks remain in force.

Missing, malformed, invented, or ungrounded output fails closed as an
unavailable shadow review. The diagnostic log is versioned
`retrieval.location_reference.shadow.v2` and includes the bounded competing
target quote and usage when a response validates. No raw provider completion
is persisted. This syntactic grounding check narrows one known bypass; it does
not prove that a model's semantic classification is correct. In particular,
an unknown site must still resolve or remain ambiguous before any real
eligibility cutover could be considered.

On failure, the signed internal endpoint still returns HTTP 503 with
`code: location_reference_review_unavailable`; it additionally returns a
bounded `failure_reason` diagnostic. Only allowlisted validation-rejection
codes or `transport_error`, `malformed_output`, and `incomplete_output` cross
the service boundary. The shadow log copies this into
`shadow_failure_reason`, separate from its existing `review_failed` category.
Invalid JSON is `malformed_output`; parseable JSON that fails the selection
schema is `validation_rejected:selection_schema`, with the missing-target shape
reported more specifically as `validation_rejected:no_competing_target`.
The diagnostic is not shown to the end user and never affects eligibility.
