import json

import httpx
from opentelemetry import trace
from opentelemetry.trace import SpanKind, Status, StatusCode
from pydantic import SecretStr, ValidationError

from app.retrieval.models import RetrievalPlan
from app.telemetry import trace_attributes


class RetrievalPlanningError(RuntimeError):
    pass


class StructuredChatRetrievalPlanner:
    """Isolated OpenAI-compatible structured-output planner adapter."""

    def __init__(
        self,
        *,
        api_url: str,
        api_key: SecretStr,
        provider_name: str,
        model: str,
        timeout_seconds: float,
        client: httpx.Client | None = None,
    ) -> None:
        if not api_key.get_secret_value().strip():
            raise RetrievalPlanningError(
                "retrieval planner credentials are unavailable"
            )
        self._api_url = api_url
        self._api_key = api_key
        self._provider_name = provider_name
        self._model = model
        self._client = client or httpx.Client(timeout=timeout_seconds)

    def plan(self, question: str, *, evaluated_at: str) -> RetrievalPlan:
        schema = RetrievalPlan.model_json_schema()
        with trace.get_tracer("maketime.python.retrieval").start_as_current_span(
            "plan retrieval intent",
            kind=SpanKind.CLIENT,
            attributes=trace_attributes(
                {
                    "gen_ai.operation.name": "retrieval_planning",
                    "gen_ai.provider.name": self._provider_name,
                    "gen_ai.request.model": self._model,
                }
            ),
            record_exception=False,
            set_status_on_exception=False,
        ) as span:
            try:
                response = self._client.post(
                    self._api_url,
                    headers={
                        "Authorization": (f"Bearer {self._api_key.get_secret_value()}")
                    },
                    json={
                        "model": self._model,
                        "messages": [
                            {
                                "role": "system",
                                "content": (
                                    "Classify only temporal retrieval intent and an "
                                    "optional location reference. Preserve the user's "
                                    "question exactly as the sole retrieval query. Ask "
                                    "for clarification rather than guessing. Evaluation "
                                    "instant: " + evaluated_at
                                ),
                            },
                            {"role": "user", "content": question},
                        ],
                        "response_format": {
                            "type": "json_schema",
                            "json_schema": {
                                "name": "retrieval_plan",
                                "strict": True,
                                "schema": schema,
                            },
                        },
                    },
                )
                response.raise_for_status()
                payload = response.json()
                content = payload["choices"][0]["message"]["content"]
                raw_plan = json.loads(content) if isinstance(content, str) else content
                plan = RetrievalPlan.model_validate(raw_plan)
                span.set_attributes(
                    trace_attributes(
                        {
                            "rag.processing.outcome": "succeeded",
                            "rag.retrieval.temporal_mode": plan.temporal_mode.value,
                        }
                    )
                )
            except (
                httpx.HTTPError,
                KeyError,
                IndexError,
                TypeError,
                ValueError,
                ValidationError,
            ) as exception:
                span.set_attributes(
                    trace_attributes(
                        {
                            "error.type": type(exception).__name__,
                            "rag.processing.outcome": "failed",
                        }
                    )
                )
                span.set_status(Status(StatusCode.ERROR))
                raise RetrievalPlanningError(
                    "retrieval planner returned no usable plan"
                ) from exception
        if plan.retrieval_queries != (question,):
            raise RetrievalPlanningError(
                "retrieval planner altered the V1 retrieval query"
            )
        return plan


class FixedRetrievalPlanner:
    """Deterministic test double; never selected as the production planner."""

    def __init__(self, plan: RetrievalPlan) -> None:
        self._plan = plan

    def plan(self, question: str, *, evaluated_at: str) -> RetrievalPlan:
        del evaluated_at
        return self._plan.model_copy(update={"retrieval_queries": (question,)})
