import hashlib
import json
from pathlib import Path

from app.conversation.models import ContextualisationRequest, ContextualisationResult
from app.conversation.openai_adapter import ContextualisationError


class CatalogueQueryContextualizer:
    """Exact-input contextualiser for the isolated deterministic E2E profile."""

    def __init__(self, catalogue_path: str) -> None:
        try:
            payload = json.loads(Path(catalogue_path).read_bytes())
            if payload.get("schema_version") != 1:
                raise ValueError("unsupported catalogue version")
            entries = payload.get("entries")
            if not isinstance(entries, list) or not entries:
                raise ValueError("catalogue entries are required")
            scenarios: dict[str, dict[str, object]] = {}
            for entry in entries:
                inputs = (
                    entry.get("contextualisation_inputs", [])
                    if isinstance(entry, dict)
                    else []
                )
                if not isinstance(inputs, list):
                    raise TypeError("contextualisation inputs must be a list")
                for item in inputs:
                    message = item.get("message") if isinstance(item, dict) else None
                    result = item.get("result") if isinstance(item, dict) else None
                    if (
                        not isinstance(message, str)
                        or not message.strip()
                        or message in scenarios
                        or not isinstance(result, dict)
                    ):
                        raise ValueError(
                            "contextualisation inputs must be unique and complete"
                        )
                    scenarios[message] = result
            if not scenarios:
                raise ValueError("contextualisation scenarios are required")
        except (OSError, TypeError, ValueError, json.JSONDecodeError) as exception:
            raise ContextualisationError(
                "The deterministic contextualisation catalogue is invalid."
            ) from exception
        canonical = json.dumps(
            payload,
            ensure_ascii=False,
            allow_nan=False,
            separators=(",", ":"),
            sort_keys=True,
        ).encode()
        self._scenarios = scenarios
        self._version = (
            f"catalogue-{hashlib.sha256(canonical).hexdigest()}:deterministic-v1"
        )

    def contextualize(
        self, request: ContextualisationRequest
    ) -> ContextualisationResult:
        try:
            scenario = self._scenarios[request.current_message]
        except KeyError as exception:
            raise ContextualisationError(
                "The message is not authorised by the deterministic scenario catalogue."
            ) from exception
        used_prior_context = scenario.get("used_prior_context")
        ordinals = scenario.get("used_turn_ordinals", [])
        if not isinstance(used_prior_context, bool) or not isinstance(ordinals, list):
            raise ContextualisationError(
                "The deterministic contextualisation scenario is invalid."
            )
        if used_prior_context and not request.history:
            raise ContextualisationError(
                "The deterministic contextualisation scenario requires prior context."
            )
        try:
            return ContextualisationResult.model_validate(
                {
                    "status": scenario.get("status"),
                    "resolved_query": scenario.get("resolved_query"),
                    "used_prior_context": used_prior_context,
                    "interpretation_metadata": (
                        {"used_turn_ordinals": ordinals} if used_prior_context else None
                    ),
                    "clarification_question": scenario.get("clarification_question"),
                    "contextualiser_version": self._version,
                    "usage": {
                        "execution": "local",
                        "provider": "deterministic",
                        "model": "catalogue-contextualiser",
                        "request_count": 0,
                        "retry_count": 0,
                        "cost_basis": "zero_cost_local",
                        "cost_usd": 0,
                    },
                }
            )
        except ValueError as exception:
            raise ContextualisationError(
                "The deterministic contextualisation scenario is invalid."
            ) from exception
