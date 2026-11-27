# Dify current-question insertion correction

Status: published and provider-free verified on 25 November 2026 as Dify workflow
version number 4.

`published-llm-node.before.json` is a safe snapshot of the active published LLM
node read from the hosted Dify database on 25 November 2026. The retained original
application package remains untouched.

`published-llm-node.corrected.json` is the deployable node configuration. Its only
change is removal of the explicit user-role `{{#sys.query#}}` prompt entry. With
memory enabled, stock Dify 1.19.0 appends the current query after retained history;
keeping both insertion paths duplicates the question.

Precise configuration diff:

```diff
       {
         "id": "aebb0ed0-999e-4d2e-90e4-7a990da833cd",
         "role": "system",
         "text": "<unchanged production instructions>"
-      },
-      {
-        "id": "90b4deac-f32e-42c8-8616-943f3ebf3794",
-        "role": "user",
-        "text": "{{#sys.query#}}"
       }
```

The model, model parameters, system instructions, retrieval context selector,
memory window, vision setting and every retrieval setting remain unchanged.

The correction was saved and published through stock Dify 1.19.0's own workflow
services. Hosted read-back proved the active graph differs from the previous graph
only by this removal. The active graph SHA-256 is
`77948ad3d5056f701c386b46ea088f008897e0fae49257baa84c913fa53de009`.

No live model, retrieval, embedding, generation or evaluation call was made. A later
separately authorised captured clean turn remains necessary to verify live prompt
assembly before the evaluation freeze.
