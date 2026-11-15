# Extractive generation needs — v3

Envelope v3 requires every need to identify extractive-request-v1. The aspect is
application-compiled source text, never the model paraphrase. The receiving service
recomputes it from context_spans, question_span and grounded typed source quotes.
Mismatch rejects the request. Question-span primacy and all existing evidence
ownership, version/side, context-budget and citation validation remain unchanged.

The response echoes envelope 3; generation result contract remains version 3.
Prompt lineage is grounded-generation-needs-v13 (adapter openai-responses-needs-v5).
The models also read historical
envelope 2 without rewriting persisted answers or frozen historical examples.

The existing internal entailment call uses `need-source-support-v1`: exact answer
and source quotations, per-need evidence ownership, and separate model judgements
for same-situation support and preservation of the requirement. Its application
checks establish quotation/association/text coverage, not semantic entailment.
The public `entailment_checks` projection remains one supported verdict per part;
usage records the internal support contract and validated receipt count. The
provider call count, 1024-token review limit and retry policy are unchanged.
No historical receipt is rewritten. Deploy the producer and profile consumer
together; old new-execution profile pins are refused.
