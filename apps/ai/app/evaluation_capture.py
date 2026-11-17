"""Opt-in, workspace-bound diagnostic sidecar; never part of application outcomes.

The operator mounts only this run's evidence directory at /evaluation-evidence.
No caller-controlled paths, exception prose or headers are retained. Current-policy
generation rejection may retain a bounded, redacted model-output string only.
"""

import json
import os
import re
from contextlib import contextmanager
from contextvars import ContextVar
from dataclasses import dataclass, field
from datetime import UTC, datetime
from pathlib import Path
from uuid import UUID, uuid4

WORKSPACE = "5323cd6b-8b7a-40f9-98b0-9413c75f643e"
CAPTURE_ROOT = Path("/evaluation-evidence")
_ACTIVE: ContextVar[Capture | None] = ContextVar("evaluation_capture", default=None)

# Recursive allowlist, bounded independently of model/schema acceptance.
PLAN_KEYS = frozenset(
    {
        "needs",
        "need_id",
        "central",
        "operation",
        "question_span",
        "context_spans",
        "turn_id",
        "quote",
        "authority",
        "fact_reference",
        "kind",
        "source",
        "value",
        "precision",
        "relation",
        "period_start",
        "period_end",
        "start",
        "end",
        "subject",
        "subject_status",
        "family_ids",
        "family_id",
        "document_id",
        "document_version_id",
        "binding",
        "bindings",
        "catalogue_binding",
        "applicability",
        "claim_under_test",
        "type",
        "scope_question",
        "at",
        "decision",
        "schema_version",
        "requested_information",
        "current_spans",
        "history_spans",
        "supports_need_id",
    }
)
GENERATION_KEYS = frozenset(
    {
        "temporal_uses",
        "part_index",
        "need_id",
        "target_id",
        "question_quote",
        "evidence_ids",
        "answer_parts",
        "text",
        "aspect_links",
        "answer_part_indices",
        "unsupported_aspect_indices",
    }
)


def _text(value: str, limit: int = 4000) -> str:
    value = value[:limit]
    value = re.sub(r"(?i)\b(?:bearer|basic)\s+\S+", "[REDACTED]", value)
    value = re.sub(r"\b(?:sk-|AKIA)[A-Za-z0-9_-]+", "[REDACTED]", value)
    value = re.sub(
        r"(?i)(?:password|api[_-]?key|secret|token)\s*[:=]\s*\S+", "[REDACTED]", value
    )
    return "".join(c for c in value if c in "\n\t" or ord(c) >= 32)


def _allow(value, keys, depth=0):
    if depth > 10:
        return "[DEPTH_BOUND]"
    if isinstance(value, dict):
        return {k: _allow(v, keys, depth + 1) for k, v in value.items() if k in keys}
    if isinstance(value, (list, tuple)):
        return [_allow(v, keys, depth + 1) for v in value[:32]]
    if isinstance(value, str):
        return _text(value)
    if value is None or isinstance(value, (bool, int)):
        return value
    return "[UNAVAILABLE_TYPE]"


@dataclass
class Capture:
    directory: Path
    request_id: str
    stage: str
    attempt: int = 0
    proposal: dict | None = None
    validator_input: dict | None = None
    normalized: int | None = None
    relation_normalized: int | None = None
    events: int = 0
    scope_events: list = field(default_factory=list)
    scope_events_truncated: bool = False
    invocation_id: str = field(default_factory=lambda: str(uuid4()))

    def write(self, event: dict):
        if self.events >= 16:
            return
        self.events += 1
        record = {
            "capture_version": "evaluation-attempt-v1",
            "workspace_id": WORKSPACE,
            "request_id": self.request_id,
            "invocation_id": self.invocation_id,
            "captured_at": datetime.now(UTC).isoformat(),
            "event_sequence": self.events,
            "stage": self.stage,
            "attempt": self.attempt,
            **event,
        }
        data = json.dumps(record, ensure_ascii=True, allow_nan=False).encode()
        if len(data) > 131072:
            record["proposed"] = None
            record["validator_input"] = None
            record["scope_diagnostics"] = []
            record["scope_diagnostics_truncated"] = True
            record["unavailable"] = ["proposal_exceeded_capture_bound"]
            data = json.dumps(record).encode()
        try:
            directory = os.open(
                self.directory, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW
            )
            try:
                fd = os.open(
                    f"{self.request_id}-{uuid4()}.json",
                    os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW,
                    0o600,
                    dir_fd=directory,
                )
                with os.fdopen(fd, "wb") as stream:
                    stream.write(data + b"\n")
            finally:
                os.close(directory)
        except OSError:
            # Diagnostic I/O must not replace a decision or create a retry. Preflight
            # and post-run reconciliation detect missing sidecars; never log data.
            return


@contextmanager
def capture_scope(settings, workspace_id, request_id, stage):
    capture = None
    destination = settings.evaluation_capture_destination
    if (
        settings.evaluation_capture_enabled
        and str(workspace_id) == WORKSPACE
        and destination
    ):
        path = Path(destination)
        try:
            valid = (
                path.is_absolute()
                and path.is_dir()
                and not path.is_symlink()
                and path.resolve().is_relative_to(CAPTURE_ROOT.resolve())
                and str(UUID(str(request_id))) == str(request_id)
            )
        except ValueError, OSError:
            valid = False
        if valid:
            capture = Capture(path, str(request_id), stage)
    token = _ACTIVE.set(capture)
    try:
        yield
    finally:
        _ACTIVE.reset(token)


def planner_begin(attempt):
    if capture := _ACTIVE.get():
        capture.attempt = attempt
        capture.proposal = None
        capture.validator_input = None
        capture.normalized = None
        capture.relation_normalized = None
        capture.scope_events = []
        capture.scope_events_truncated = False


def scope_diagnostic(
    stage,
    outcome,
    need=None,
    *,
    reason=None,
    path=None,
    parsed=False,
    errors=(),
    origin=None,
    roots=(),
    cleared_count=None,
):
    """Observe existing validation only; no new validation or application result."""
    if not (capture := _ACTIVE.get()):
        return
    if len(capture.scope_events) >= 32:
        capture.scope_events_truncated = True
        return
    row = {
        "stage": stage,
        "outcome": outcome,
        "reason": reason,
        "field": _text(path, 200) if path else None,
        "need_id": _text(str(need.get("need_id")), 100) if need else None,
        "originating_need_id": _text(origin, 100) if origin else None,
        "root_need_ids": [_text(x, 100) for x in sorted(roots)[:3]],
    }
    if cleared_count is not None:
        row["cleared_span_count"] = max(0, min(4, int(cleared_count)))
    if need is not None:
        fields = {}
        invalid_fields = {str(e["loc"][0]) for e in errors if e.get("loc")}
        for key in (
            "requested_information",
            "current_spans",
            "history_spans",
            "question_span",
            "context_spans",
            "supports_need_id",
        ):
            value = need.get(key)
            safe = _allow(value, PLAN_KEYS)
            state = (
                "absent"
                if key not in need
                else "null"
                if value is None
                else "malformed"
                if key in invalid_fields
                else "validly_parsed"
                if parsed
                else "not_validated"
            )
            fields[key] = {
                "state": state,
                "value": safe,
                "bounded_or_redacted": safe != value,
            }
        row["fields"] = fields
        row["schema_errors"] = [
            {
                "code": _text(str(e["type"]), 100),
                "field": _text(".".join(str(x) for x in e["loc"]), 200),
            }
            for e in errors[:16]
        ]
        row["schema_errors_truncated"] = len(errors) > 16
    capture.scope_events.append(row)


def planner_proposal(decoded):
    if capture := _ACTIVE.get():
        capture.proposal = (
            _allow(decoded, PLAN_KEYS) if isinstance(decoded, dict) else None
        )


def planner_validator_input(decoded):
    if capture := _ACTIVE.get():
        capture.validator_input = (
            _allow(decoded, PLAN_KEYS) if isinstance(decoded, dict) else None
        )


def calendar_normalized(count):
    if capture := _ACTIVE.get():
        capture.normalized = count


def relation_normalized(count):
    if capture := _ACTIVE.get():
        capture.relation_normalized = count


def planner_end(status, validation=None):
    if capture := _ACTIVE.get():
        if (
            status == "rejected"
            and capture.scope_events
            and capture.scope_events[-1]["outcome"] == "started"
        ):
            capture.scope_events[-1] = {
                **capture.scope_events[-1],
                "outcome": "failed",
                "validation": safe_validation(validation),
            }
        capture.write(
            {
                "status": status,
                "proposed": capture.proposal if status == "rejected" else None,
                "validator_input": capture.validator_input
                if status == "rejected"
                else None,
                "validation": safe_validation(validation),
                "scope_diagnostics": capture.scope_events,
                "scope_diagnostics_truncated": capture.scope_events_truncated,
                "explanation": "Planner validation rejected the proposed fields."
                if status == "rejected"
                else None,
                "calendar_notation_normalization_count": capture.normalized,
                "leading_relation_conversion_count": capture.relation_normalized,
                "unavailable": (
                    ["parsed_plan"]
                    if status == "rejected" and capture.proposal is None
                    else []
                )
                + (
                    ["validator_input"]
                    if status == "rejected" and capture.validator_input is None
                    else []
                )
                + (
                    ["calendar_notation_normalization_count"]
                    if capture.normalized is None
                    else []
                )
                + (
                    ["leading_relation_conversion_count"]
                    if capture.relation_normalized is None
                    else []
                ),
            }
        )


def safe_validation(validation):
    if not isinstance(validation, dict):
        return None
    # Only application-generated closed diagnostics, never exception text.
    return {
        key: value
        for key in ("stage", "field", "code")
        if isinstance(value := validation.get(key), str)
        and re.fullmatch(r"[a-zA-Z0-9_.-]{1,100}", value)
    }


def generation_rejected(attempt, parsed, validation, exception=None):
    if capture := _ACTIVE.get():
        capture.attempt = attempt
        if hasattr(parsed, "model_dump"):
            parsed = parsed.model_dump(mode="json")
        from pydantic import ValidationError

        from app.generation.diagnostics import RULES

        messages = (
            [
                e["msg"].removeprefix("Value error, ")
                for e in exception.errors(include_input=False, include_context=False)
            ]
            if isinstance(exception, ValidationError)
            else [str(exception)]
            if exception is not None
            else []
        )
        explanation = next(
            (message for message in messages if message in RULES),
            "Application validation rejected this attempt; consult the recorded closed condition.",
        )
        capture.write(
            {
                "status": "rejected",
                "proposed": _allow(parsed, GENERATION_KEYS)
                if isinstance(parsed, dict)
                else None,
                "validation": safe_validation(validation),
                "explanation": explanation,
                "unavailable": []
                if isinstance(parsed, dict)
                else ["parsed_generation_output"],
            }
        )


def current_policy_citations_normalized(response, canonical, changes):
    """Bounded sidecar, not a claim of membership or semantic correctness."""
    if capture := _ACTIVE.get():
        raw = response.output_text
        canonical_text = canonical.model_dump_json()
        capture.attempt = 1
        capture.write(
            {
                "status": "normalised",
                "reason": "exact_duplicate_evidence_ids",
                "citation_normalisation": changes,
                "raw_model_output": _text(
                    raw.encode("utf-8")[:16384].decode("utf-8", errors="ignore"), 16384
                ),
                "canonical_output": _text(
                    canonical_text.encode("utf-8")[:16384].decode(
                        "utf-8", errors="ignore"
                    ),
                    16384,
                ),
                "raw_output_truncated": len(raw.encode("utf-8")) > 16384,
                "canonical_output_truncated": len(canonical_text.encode("utf-8"))
                > 16384,
                "raw_output_redacted": True,
                "membership_validation": "not_yet_checked",
            }
        )


def current_policy_rejected(response, stage, code, field="output"):
    """Sidecar only: never return raw output or diagnostics to the API caller."""
    if capture := _ACTIVE.get():
        raw = getattr(response, "output_text", None)
        status = getattr(response, "status", None)
        details = getattr(response, "incomplete_details", None)
        reason = getattr(details, "reason", None)
        capture.attempt = 1
        capture.write(
            {
                "status": "rejected",
                "validation": safe_validation(
                    {"stage": stage, "field": field, "code": code}
                ),
                "completion": {
                    "status": status
                    if status
                    in {
                        "completed",
                        "incomplete",
                        "failed",
                        "cancelled",
                        "queued",
                        "in_progress",
                    }
                    else "unavailable",
                    "incomplete_reason": reason
                    if reason in {"max_output_tokens", "content_filter"}
                    else None,
                },
                "raw_model_output": _text(
                    raw.encode("utf-8")[:16384].decode("utf-8", errors="ignore"), 16384
                )
                if isinstance(raw, str)
                else None,
                "raw_output_truncated": len(raw.encode("utf-8")) > 16384
                if isinstance(raw, str)
                else False,
                "raw_output_redacted": True,
                "unavailable": [] if isinstance(raw, str) else ["raw_model_output"],
            }
        )
