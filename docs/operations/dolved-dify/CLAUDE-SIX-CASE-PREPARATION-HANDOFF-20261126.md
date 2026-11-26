# Neutral six-case preparation handoff for Claude

**Date:** 26 November 2026  
**Status:** preparation only; no cases executed and no provider called  
**Scope record:** `INVITED-PILOT-IMPROVEMENT-SCOPE-20261126.md`

## Boundary for Claude

Independently freeze the wording, expected behaviour and scoring before David releases
execution. Use exactly six cases in the categories already fixed by the scope record:
the known F20 regression, two fresh related-but-insufficient cases, two controlled
injection cases, and one ordinary answerable case. Identify F20 as known evidence, not a
fresh result. Do not disclose the fresh cases or their expectations to Codex before the
run. Do not reinterpret the original 40-question or memory evaluations.

Exactly one execution per frozen case is permitted after release. Do not regenerate,
retry, replace or tune a case. If a planted injection fixture is absent from the captured
generation input, record that case as **unexercised** and do not rerun it. Preserve any
failure and stop for David's decision.

## Prepared correction identities

Baseline:

- application revision `a36e1cc4664535510c0052ecbaa8af6ea47cfae4`
- Dify application `32199630-6a11-40a0-96ca-618560e6e394`
- published workflow `fc614ecd-9cdf-446e-96bf-d193f1a2afde`, version `4`
- active graph SHA-256
  `77948ad3d5056f701c386b46ea088f008897e0fae49257baa84c913fa53de009`
- baseline prompt SHA-256
  `1ec04255227de84b21e2fee22e49e3df037c51bae21125594f7adb45b466a26c`

Prepared, unpublished candidate:

- node artifact
  `deployment/dolved-dify/workflow-config/20261126/proposed-llm-node.json`
- artifact SHA-256
  `162874fe3da161ea01230d2833c0dec633d45d0b29a538592e52a8a62416b75a`
- proposed prompt SHA-256
  `0d9c2147a354bbd611166cac0b8bd7eda8132d8bf08bd1a2b23406921e597fae`
- no proposed workflow ID, version or graph hash exists yet because nothing was saved or
  published

The exact prompt and UI diffs, limitations and provider-free results are in the scope
record. The current prompt already contained a general absence-of-evidence rule; the
candidate makes its required decision and limitation wording explicit. This remains a
probabilistic instruction, not a compliance guarantee.

## Isolated controlled-injection arrangement

Use this arrangement only for the two injection cases, after David releases execution:

1. In a separate private Dify test workspace, create an unpublished test-only clone of
   the release candidate graph. Do not add any fixture to the client workspace or normal
   corpus.
2. Use the exact proposed system-prompt template and hash above. Keep model
   `gpt-5.6-sol`, reasoning `medium`, maximum output `4096`, service tier `default`, native
   citations on, memory enabled/window `10`, trusted-site input and all retrieval settings
   identical. Start a fresh Dify conversation for each case so the enabled memory setting
   is preserved without cross-case history.
3. Point only the clone's knowledge-retrieval dataset identity to a separate test-only
   dataset containing Claude's two frozen, non-confidential fixture documents. Keep
   General chunking, embeddings, hybrid weights `0.7/0.3`, node and dataset Top K `10`,
   threshold off and reranking off. Record the test workspace, app, workflow and dataset
   identities plus checksums of the exact fixtures before execution.
4. Each fixture must contain an inert, unique marker and a harmless planted instruction.
   It must contain no real secret, confidential client content or real external action.
   Claude owns and freezes the exact fixture and question wording; Codex does not.
5. Retain the normal retrieval record and the existing Dify runtime prompt capture. The
   exercise proof is the fixture's exact unique marker appearing in the LLM node's
   captured assembled input (`process_data.prompts`), together with the corresponding
   test-only retrieved segment. Presence only in the dataset or retrieval output is not
   sufficient proof that it reached generation.
6. If the marker is not in that captured LLM input, mark the case unexercised and stop it
   without replacement or retry.

This arrangement tests generation behaviour when malicious text is actually supplied
through an otherwise production-equivalent retrieval/context path. Because its dataset
identity is deliberately isolated, it does **not** test production-corpus retrieval
coverage and must not be reported as doing so. The other four cases use the normal frozen
corpus and release-candidate workflow.

## Release prerequisites and stop point

Before any case runs, record the final reviewed application revision and read back the
candidate Dify graph after the separately authorised supported save/publication. Confirm
that its production graph differs from version `4` only by the frozen system-prompt text,
then record the new workflow/version and canonical graph hash. Reconfirm that model,
retrieval, memory and corpus identities match the frozen baseline.

No publication, deployment, fixture ingestion, embedding, retrieval, generation or live
assessment has been performed in preparing this handoff. David must release the frozen
correction and six-case execution together.
