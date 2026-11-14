# Topical comparison family review v1

This internal, signed `retrieval.compare-family` request is used only when an
evidence need is `compare` and its catalogue binding is `topic`. It is not a
replacement for catalogue binding, eligibility, authority resolution or
generation.

The application first searches only authorised **current-side** documents in
the caller's workspace and active corpus generation, then rechecks and hydrates
at most 12 candidate chunks. The request contains the user's comparison aspect
and, for each candidate, its family ID, chunk ID, title and at most 2,000
characters of text. The reviewer returns `selected`, `ambiguous` or `no_match`.
`selected` must carry one family ID, one chunk ID and a literal 8–300 character
supporting quote. Both services verify that this exact family/chunk pair was in
the authorised candidate set and that the quote is present in that chunk.
Unknown, inconsistent or unsupported output fails closed; no free-form family
name or historical version is accepted from the reviewer.

Only after `selected` does Laravel resolve both comparison sides for that one
family using the existing timeline, location, tenant and final recheck rules.
If the family has no distinct authorised historical counterpart, the need is
`comparison_scope_incomplete`. An ambiguous or absent topical selection never
triggers broad historical-pair search. Named-family comparisons do not use this
review and retain their existing path.

The reviewer consumes one bounded model request when reached. Its token counts
are recorded in the retrieval usage stream; cost is labelled `unavailable`
unless the provider supplies a defensible price basis. The reviewer treats
candidate text as untrusted data. Provider-free contract tests establish
bounds and identity checks, not the correctness of the model's relevance
judgment; live acceptance is required for that.

## Additive validation diagnostics (2026-11-12)

`selection.decision` remains the effective, validated decision used for execution.
`original_decision` separately records the parsed reviewer's `selected`,
`ambiguous`, or `no_match` decision. `validation_rejection_code` is null unless
an original selection was converted to effective `no_match`; its closed values
are `identity_mismatch`, `quote_length`, and `quote_not_in_candidate` (checks in
that order). No rejected quotation or raw model response is included.
`candidate_identities` contains at most 12 family/chunk UUID pairs, in the order
actually supplied to the reviewer, with no titles or text. Python derives these
from the request; Laravel independently records the pairs from its bounded sent
request, rather than trusting echoed identities. These fields are diagnostics
only; legacy responses have unknown original decision (null), not an inferred
one. Invalid diagnostic metadata is discarded, never used to change selection.
Prompts, validation criteria, request count and effective decisions are unchanged.

## Optional C2 failure attribution (2026-11-14)

The 503 status/body remain unchanged. An additive `X-Comparison-Review-Failure`
header classifies the caught exception by type/status, never message text:
`timeout`, `connection_error`, `service_unavailable`, `authentication_failed`,
`provider_rejected`, `invalid_output`, `unknown`. No raw response crosses this
boundary. Existing comparison handling ignores the header. Only optional C2
discovery uses it to allow an outage fallback; absent/unknown metadata and invalid
output remain fail-closed. Provider rejection (including 429/quota) and
authentication failure never permit fallback. No attempts or settings change.
Deployment must include both services; an old AI service without this metadata
cannot establish that its generic reviewer 503 is an optional provider outage.
