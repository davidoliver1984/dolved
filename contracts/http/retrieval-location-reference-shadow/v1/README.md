# Location-reference shadow review v1

This signed internal `retrieval.location-reference-shadow` request is a diagnostic
prototype. It is disabled by default (`RETRIEVAL_LOCATION_REFERENCE_SHADOW=false`).
Even when enabled, the existing `EligibilityResolver` result alone determines
eligibility and the user-facing response. The review result is logged safely
beside that result; it cannot widen workspace, location, family or temporal scope.

The application invokes the review only after a failed exact location-name/alias
match. It sends the exact applicability reference, the grounded need's question
span and rewritten aspect, at most 200 locations with up to 20 aliases each,
and at most 500 non-tombstoned document-family titles. All candidates are loaded
from the already-authorised workspace. A missing question span or exceeded bound
skips review. User text, aliases and titles are untrusted input to the reviewer.

The response is one of `resolved_site`, `not_a_site`, or
`unresolved_or_ambiguous`. A site result must name an ID in the exact location
candidate set and repeat the exact reference quote. A non-site result must
repeat that quote. A document/record role must name a supplied document-family
ID and cite an exact 8–300-character substring of that candidate's title; other
non-site roles must cite a question substring containing the reference. An
unresolved result carries only a bounded reason. Python checks candidate
membership and literal quote grounding; Laravel also rebuilds the candidate
sets from the authorised workspace and rechecks returned IDs. This does not
prove semantic classification quality. Malformed, invented, incomplete or
unavailable review is logged as `unavailable`, never used as eligibility.

The reviewer makes one bounded model attempt (`max_completion_tokens=4096`, low
reasoning effort) when reached. The signed Laravel-to-AI transport may have its
normal bounded transport retry. The model cannot determine actual document
authority or policy applicability. Provider-free fake-adapter tests prove
contract handling and unchanged authoritative outcomes; they do not establish
the model's classification accuracy. No live provider use is authorised by this
prototype.
