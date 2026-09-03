# Fresh-task handoff: independently author the V4 evaluation population

Use a genuinely fresh task that has not seen Dolved output, calibration or
held-out material, corpus-authoring conversations, prompts, plans, semantic
masters, messiness labels, comparison contracts, intended traps or generator
implementation details.

Give that task only:

`tests/evaluation/authoring-views/dolved-care-v4/v1/question-author-view.tar.gz`

and these neutral contract files:

- `docs/evaluation/r28-s01/access-manifest.json`
- `contracts/evaluation/v4/independent-authoring-output.schema.json`
- `docs/evaluation/r28-s01/authoring-coverage-contract.json`
- `docs/evaluation/r28-s01/authoring-output-contract.md`
- `scripts/evaluation/r28_authoring_access.py`
- `scripts/evaluation/r28_access_guard.py`
- `scripts/evaluation/validate_r28_authoring_output.py`

Expected SHA-256:

`8f73c9c12a843be9641698f39db60243a977e6c1c700a3f89f72dbbb890e44b9`

## Exact prompt

> You are the independent question author for Dolved Evaluation Corpus V4.
> Inspect only the supplied restricted author-view archive. Do not inspect the
> repository, the source checkpoint archive, any calibration or held-out data,
> previous answers/results, corpus-authoring material, semantic masters,
> generator code, or comparison/trap contracts. If any additional material is
> visible, stop and report contamination.
>
> Follow contract `dolved-v4-independent-authoring-output-v1`, aggregate
> SHA-256 `c7e4f6bce57be48e69bb6f3c57e6cb34f5130859efd782e9ad5db7503a163e3c`.
> Author exactly 72 semantic cases and exactly 144 total utterances: every case
> must have exactly two independently written, textually distinct wording
> variants named `v1` and `v2`. Each case must include a stable case ID, one or
> more natural employee questions, its evaluation layer and slices, applicable
> organisation/location/date context, exact expected outcome, independently
> quoted source evidence with restricted-view path and source SHA-256, and a
> concise rationale. Never infer evidence that is not visibly present.
> All 144 utterance texts must be globally distinct after Unicode NFC,
> leading/trailing whitespace trimming, internal Unicode-whitespace collapse to
> one ASCII space, and Unicode case-folding. This comparison must not rewrite
> the submitted text. Record `authored_at_utc` as a valid UTC date-time ending
> in `Z` or `+00:00`.
>
> Cover ordinary wording, concise/vague phrasing, typos and aliases, multi-part
> questions, current/historical/valid-at-date/comparison requests, changed
> contacts/numbers/dates/responsibilities/escalations, renamed/reordered/added/
> removed content, local/inherited/global applicability, legitimate no-answer
> and clarification, near duplicates, competing documents, long documents,
> chunk-boundary evidence, PDF/DOCX/TXT, tables/awkward layouts, cross-tenant
> canaries and realistic prompt injection. Treat formatting-only differences as
> unavailable unless visible source content independently supports a question.
>
> Keep primary, foreign-tenant and security-test cases separately identified.
> Do not include negative/import fixtures; they are outside this restricted
> view and governed separately. Do not run Dolved or alter an expectation based
> on system behaviour. Return the population plus a coverage matrix and an
> author declaration listing every input path accessed and confirming the
> prohibited sources were not accessed.
> Record each external neutral input with its exact repository-relative path.
> Record every archive member actually opened as the archive path, `!/`, and
> its complete rooted member name, exactly as defined by the output contract.
>
> Write only to a fresh directory matching
> `/tmp/dolved-r28-v4-authoring/AUTHOR-V4-YYYYMMDD-XXXXXXXX/`. Leave the
> repository unchanged. That directory must contain exactly `population.json`,
> `coverage-matrix.json`, `author-declaration.json`, `authoring-report.md` and
> `checksums.sha256`, in the exact structures required by the supplied schema,
> coverage contract and validator.
>
> Validate with:
> `apps/ai/.venv/bin/python scripts/evaluation/validate_r28_authoring_output.py --output-dir /tmp/dolved-r28-v4-authoring/AUTHOR-V4-YYYYMMDD-XXXXXXXX --restricted-view tests/evaluation/authoring-views/dolved-care-v4/v1/question-author-view.tar.gz`
> If validation fails, correct only your authored output and rerun it. Passing
> structural validation is not independent audit or acceptance. Stop after a
> passing result; do not inspect the wider repository.

The neutral contract is supplied separately, so the restricted archive remains
unchanged. It contains no questions, expected evidence, judgements or hidden
authoring material and is within the approved restricted-input boundary.

R28-S01 pauses after this handoff. David must launch the fresh task and return
its artefacts for a different fresh audit task. This task must not author or
audit the population itself.
