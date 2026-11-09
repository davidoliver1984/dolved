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
