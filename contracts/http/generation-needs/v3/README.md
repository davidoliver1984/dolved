# Extractive generation needs — v3

Envelope v3 requires every need to identify extractive-request-v1. The aspect is
application-compiled source text, never the model paraphrase. The receiving service
recomputes it from context_spans, question_span and grounded typed source quotes.
Mismatch rejects the request. Question-span primacy and all existing evidence
ownership, version/side, context-budget and citation validation remain unchanged.

The response echoes envelope 3; generation result contract remains version 3.
Prompt lineage is grounded-generation-needs-v12. The models also read historical
envelope 2 without rewriting persisted answers or frozen historical examples.
