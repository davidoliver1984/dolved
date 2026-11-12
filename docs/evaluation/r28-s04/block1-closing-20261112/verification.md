# Provider-free verification before closing checkpoint

Clean runtime baseline: `6bcc554c503980599b95580d558f353199fb62fc`; the
subsequent `3c6f3b1` changes only the recorded approval. Three pre-existing tracked
edits remain excluded (ADR-0039 and the noninteractive full-run harness/test edits).

Final exact-name comparison: AI baseline 1,241 pass / 38 fail / 4 skip;
changed 1,267 pass / the same 38 fail / 4 skip. Laravel baseline 837 pass /
2 fail / 9 skip; changed 843 pass / the same 2 fail / 9 skip. Zero new **or
resolved** failure names. These are nonzero-exit suites, not an all-green claim.
Raw XML and full failing-name sets are retained in `verification/` and reproduced
by `verification/compare-suites.py`.

The first complete clean-HEAD Laravel run also encountered an intermittent
`SemanticRetrievalTest::test_organisation_identity_is_not_a_location_and_registered_site_suffixes_resolve_before_hierarchy`
sealed-snapshot-immutability error. The unchanged repeated baseline did not;
both raw results are retained. Two wrong-working-directory launches stopped
before execution/collection and are separately named infrastructure logs. The
intermediate AI suite exposed obsolete single-request test doubles; those existing
schema tests now explicitly isolate an accepted reviewer. They are **not** proof
of semantic detection. The new check tests exercise actual mocked HTTP responses,
closed feedback, receipt propagation, two-attempt exhaustion, malformed/transport
fail-closed behavior and all four counterexample rejection paths. The real model's
semantic detection is reserved for the frozen checkpoint, not claimed from mocks.

Shared Python/PHP calendar examples cover leap February, December/January,
London-vs-UTC month/quarter edges, DST offsets, inclusive last-day interval wire
format and exclusive window ends. Follow-up tests anchor an earlier quoted phrase
to this question's evaluated_at. Duration/quantity cannot absorb relative calendars;
undated personal events remain ungrounded. Laravel production-orchestrator tests
prove exhausted clause_omitted/meaning_changed/scope_added becomes the existing
honest no-answer, not execution or a fabricated date clarification.

The existing full-suite allocation/renderer tests exercise budget counts and
disclosure. No allocation or retrieval thresholds changed. Live disclosure remains
unverified until one of the two frozen budget inputs actually hits the linked-gap
branch. Clarification-only plans invoke no semantic check because they execute no
needs; such a route is not proof of semantic-check acceptance.

Changed Python Ruff checks and targeted mypy pass. Mypy's original cache produced
an internal error; a fresh `/tmp` cache ran successfully after correcting three
type annotations. PHP changed files formatted by Pint. Governed harness tests pass
with fixed input/probe hashes and the original b01-06 injection-workspace scope.

Commands: `docker compose run --rm --no-deps` with repo mounts `/repo` and
`/workspace`, per-process Git `safe.directory=/repo`; AI working directory
`/repo/apps/ai`, `/app/.venv/bin/python -m pytest tests -q --junitxml=...`;
Laravel default `/app`, `php -d memory_limit=512M vendor/bin/phpunit --log-junit ...`.
Harness: host standard-library `python3 -m unittest discover -s scripts/evaluation
-p test_run_r28_r06_governed.py`. No provider call during verification.
