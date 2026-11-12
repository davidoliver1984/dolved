# Application-owned reference classification v1

Laravel retains `applicability_reference_classifications` on executed evidence
needs for disclosure, and `reference_classifications` in the eligibility trace.
Each entry contains the original `reference` and a closed `kind`: `site`,
`organisation`, `document`, or `unresolved`. These are not planner fields and are
not sent in the generation needs contract. No provider or prompt change.

Exact workspace site/alias matches take precedence over organisation/title
matches; ambiguity remains unresolved. Whole-reference organisation identities
come from workspace.name and its existing legal/organisation-suffix short form,
with bounded article/preposition, possessive and staff/employees/sites wrappers.
No arbitrary partial-name match or cross-workspace identity is used. No tenant
organisation-alias store currently exists. Whole catalogue titles come from the
same non-tombstoned workspace family catalogue used by the planner. There is no
independent document-title field. No acronym inference or substring title match.
Unmatched references retain existing location heuristics and non-blocking results.

Only a nonempty set of positively identified organisation/document references
suppresses unresolved named-location disclosure. Missing legacy metadata, unknown
types and mixed unresolved references do not suppress it. Scope facts, authoritative
version selection, tenancy, citation ownership and evidence ranking are unchanged.
