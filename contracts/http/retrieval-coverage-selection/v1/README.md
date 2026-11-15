# Grounded coverage selection v1

One authenticated Laravel → AI proposal call, purpose `retrieval.coverage-selection`.
Not eligibility, answerability, or a completeness certificate. No retries.

Bounds: three existing needs; six extractive proposed aspects; twenty unique
already-qualified physical passages; 30,000 measured input tokens including
schema/instructions; 4,096 completion tokens; at most 60 seconds within the caller's
remaining deadline. Selection retains at most five physical passages.

Each candidate carries an application-minted `need_id:side` allowlist. Laravel
independently rechecks receiving-need eligibility and hydration before and after
the call. A source appearing elsewhere does not authorise an association.
The response contains exact question/source quotes and candidate/association IDs.
Both consumers validate these against their actual request. Requested-aspect
decomposition and proposed support remain model judgments.

Typed timeout/unavailability may retain the old allocation with limitations and
attempt usage. Invalid/incomplete proposals are never consumed. Authentication,
integrity, authority, budget/deadline and unknown failures cannot permit fallback.
Safely recorded invalid model output is an item failure; fail-closed infrastructure
or integrity failures require a run stop. Neither introduces a retry.

Maximum-coverage allocation scores proposed complete support units, not passages
in isolation; comparison units require both sides. Ties use existing ranks and
stable identities. Saturated units remain explicitly unmet. No ranking or threshold
is changed. Proposed source support is not a substitute for generation validation.
