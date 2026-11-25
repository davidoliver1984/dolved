# Dify current-question insertion correction

Status: prepared locally; not imported or published.

`published-llm-node.before.json` is a safe snapshot of the active published LLM
node read from the hosted Dify database on 25 November 2026. The hosted workflow
was not changed and the retained original application package remains untouched.

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

Deployment requires a supported Dify workflow edit/import followed by publication.
That action is deliberately outside this correction batch's authority.
