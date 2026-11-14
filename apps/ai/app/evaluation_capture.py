"""Opt-in, workspace-bound diagnostic sidecar; never part of application outcomes.

The operator mounts only this run's evidence directory at /evaluation-evidence.
No caller-controlled paths, exception prose, headers or provider bodies are retained.
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


def _text(value: str) -> str:
    value = value[:4000]
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
    events: int = 0
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


def planner_end(status, validation=None):
    if capture := _ACTIVE.get():
        capture.write(
            {
                "status": status,
                "proposed": capture.proposal if status == "rejected" else None,
                "validator_input": capture.validator_input
                if status == "rejected"
                else None,
                "validation": safe_validation(validation),
                "explanation": "Planner validation rejected the proposed fields."
                if status == "rejected"
                else None,
                "calendar_notation_normalization_count": capture.normalized,
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
