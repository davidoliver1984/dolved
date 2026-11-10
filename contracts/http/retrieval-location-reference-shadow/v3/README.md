# Location-reference shadow review v3

This signed, disabled-by-default diagnostic contract preserves the workspace-
scoped candidate lists, exact/alias fast path, and strict separation from
authoritative eligibility. Request and response use `contract_version: 3`.

The model may return `resolved_site` only with a location ID from the supplied
workspace list, `not_a_site` only with `reference_type: document_or_record`
anchored to a supplied document-family ID and title substring, or
`unresolved_or_ambiguous`. The model schema cannot express an incidental,
answer-value, or organisation non-site classification. The retained
`competing_target_quote` field must be null for every v3 selection.
Before accepting a document reading, Python and Laravel each reject it as
`validation_rejected:site_document_conflict` if the same reference is an exact
name/alias or a whole-word prefix of any authorised location candidate. This
is a diagnostic fail-closed result; the existing authoritative resolver still
supplies its site match. A document title containing a real site name cannot
silently win over that site match.

Before this reviewer is called, a separate shadow-only deterministic layer
recognises a versioned closed list of standalone generic reference phrases.
Its result is logged as `generic_reference`; it never changes eligibility or
the user-facing answer. Unknown proper-noun site phrases never match this
list by substring. The organisation-name exemption and existing exact/alias
lookup remain authoritative and unchanged.

Invalid output fails closed with the existing bounded failure diagnostics;
raw provider responses are logged only on failure. The shadow event is
`retrieval.location_reference.shadow.v3`. No shadow result is an eligibility
input, and this contract does not authorize cutover.
