import hashlib
import json
from pathlib import Path

from app.retrieval.models import OperationUsage, PlannerLineage, RetrievalPlan
from app.retrieval.planner import PlanningResult, RetrievalPlanningError


class CatalogueRetrievalPlanner:
    """Question-keyed planner for the isolated deterministic test profile."""

    def __init__(self, catalogue_path: str) -> None:
        self._path = Path(catalogue_path)
        try:
            raw = self._path.read_bytes()
            payload = json.loads(raw)
            if payload.get("schema_version") != 1:
                raise ValueError("unsupported catalogue version")
            entries = payload.get("entries")
            if not isinstance(entries, list) or not entries:
                raise ValueError("catalogue entries are required")
            plans: dict[str, RetrievalPlan] = {}
            for entry in entries:
                question = entry.get("question") if isinstance(entry, dict) else None
                if (
                    not isinstance(question, str)
                    or not question.strip()
                    or question in plans
                ):
                    raise ValueError(
                        "catalogue questions must be unique non-empty strings"
                    )
                plans[question] = RetrievalPlan.model_validate(entry.get("plan"))
        except (OSError, ValueError, TypeError, json.JSONDecodeError) as exception:
            raise RetrievalPlanningError(
                "The deterministic retrieval catalogue is invalid.",
                category="deterministic_catalogue_invalid",
                systemic=True,
            ) from exception
        self._plans = plans
        canonical = json.dumps(
            payload,
            ensure_ascii=False,
            allow_nan=False,
            separators=(",", ":"),
            sort_keys=True,
        ).encode()
        self._checksum = hashlib.sha256(canonical).hexdigest()

    def plan(self, question: str, *, evaluated_at: str) -> RetrievalPlan:
        del evaluated_at
        try:
            return self._plans[question]
        except KeyError as exception:
            raise RetrievalPlanningError(
                "The question is not authorised by the deterministic scenario catalogue.",
                category="deterministic_scenario_unknown",
            ) from exception

    def plan_with_observation(
        self, question: str, *, evaluated_at: str
    ) -> PlanningResult:
        return PlanningResult(
            plan=self.plan(question, evaluated_at=evaluated_at),
            lineage=self.lineage(),
            usage=OperationUsage(
                stage="planner",
                provider="deterministic",
                model="catalogue-retrieval-planner",
                execution="local",
                request_count=1,
                retry_count=0,
                latency_ms=0,
                cost_basis="zero_cost_local",
                cost_usd=0,
            ),
        )

    def lineage(self) -> PlannerLineage:
        values = {
            "provider": "deterministic",
            "model": "catalogue-retrieval-planner",
            "contract_schema_version": "plan-response-v2",
            "prompt_version": f"catalogue-{self._checksum}",
            "adapter_version": "catalogue-v1",
        }
        encoded = json.dumps(values, sort_keys=True, separators=(",", ":")).encode()
        return PlannerLineage(**values, fingerprint=hashlib.sha256(encoded).hexdigest())

    @property
    def catalogue_checksum(self) -> str:
        return self._checksum

    @property
    def questions(self) -> frozenset[str]:
        return frozenset(self._plans)
