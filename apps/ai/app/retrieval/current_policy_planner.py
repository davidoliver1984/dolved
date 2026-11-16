"""Profile-specific schema, using the existing two-attempt planner transport."""

from app.retrieval.current_policy import (
    ADAPTER_VERSION,
    CONTRACT,
    PROMPT_VERSION,
    SCOPE_PROMPT,
)
from app.retrieval.needs_planner import PROMPT, StructuredChatNeedsPlanner
from app.retrieval.needs_schema import provider_needs_schema


def policy_schema(catalogue):
    schema = provider_needs_schema(catalogue)
    schema["properties"]["schema_version"] = {"type": "string", "const": CONTRACT}
    for branch in schema["$defs"]["EvidenceNeed"]["anyOf"]:
        branch["properties"].update(
            {
                "requested_information": {
                    "type": "string",
                    "enum": ["current_policy", "history", "mixed", "unclear"],
                },
                "current_spans": {
                    "type": "array",
                    "maxItems": 4,
                    "items": {"$ref": "#/$defs/QuestionSpan"},
                },
                "history_spans": {
                    "type": "array",
                    "maxItems": 4,
                    "items": {"$ref": "#/$defs/QuestionSpan"},
                },
            }
        )
        branch["required"] += [
            "requested_information",
            "current_spans",
            "history_spans",
        ]
    return schema


class CurrentPolicyPlanner(StructuredChatNeedsPlanner):
    CURRENT_POLICY = True

    def __init__(self, **kwargs):
        super().__init__(**kwargs)
        self._contract_schema_version = CONTRACT
        self._prompt_version = PROMPT_VERSION
        self._adapter_version = ADAPTER_VERSION

    def planner_prompt(self) -> str:
        return (
            PROMPT.replace(
                "Use evidence-needs-plan-v4.", "Use current-policy-needs-v1."
            )
            + SCOPE_PROMPT
        )
