#!/usr/bin/env python3
"""Apply one authorised local Collector sampling plan and acknowledge it."""

from __future__ import annotations

import argparse
import base64
import hashlib
import hmac
import json
import os
import subprocess
import urllib.request
import uuid
from datetime import UTC, datetime
from typing import Any


PREFIX = "X-Observability-Reconciliation-"


def signed_post(
    *,
    url: str,
    path: str,
    purpose: str,
    identity: dict[str, str],
    body: bytes,
    secret: bytes,
) -> dict[str, Any]:
    timestamp = str(int(datetime.now(UTC).timestamp()))
    request_id = str(uuid.uuid4())
    canonical = "\n".join(
        [
            "or1",
            purpose,
            "POST",
            path,
            identity["key_id"],
            identity["key_version"],
            identity["environment"],
            identity["policy_version"],
            identity["setting_key"],
            identity["target"],
            identity["deployment_attempt_id"],
            identity["plan_id"],
            hashlib.sha256(body).hexdigest(),
            timestamp,
            request_id,
            identity["correlation_id"],
        ]
    )
    signature = hmac.new(secret, canonical.encode(), hashlib.sha256).hexdigest()
    headers = {
        "Content-Type": "application/json",
        f"{PREFIX}Key-ID": identity["key_id"],
        f"{PREFIX}Key-Version": identity["key_version"],
        f"{PREFIX}Timestamp": timestamp,
        f"{PREFIX}Request-ID": request_id,
        f"{PREFIX}Purpose": purpose,
        f"{PREFIX}Environment": identity["environment"],
        f"{PREFIX}Policy-Version": identity["policy_version"],
        f"{PREFIX}Setting-Key": identity["setting_key"],
        f"{PREFIX}Target": identity["target"],
        f"{PREFIX}Deployment-Attempt-ID": identity["deployment_attempt_id"],
        f"{PREFIX}Plan-ID": identity["plan_id"],
        f"{PREFIX}Correlation-ID": identity["correlation_id"],
        f"{PREFIX}Signature": f"or1={signature}",
    }
    request = urllib.request.Request(url + path, data=body, headers=headers, method="POST")
    with urllib.request.urlopen(request, timeout=15) as response:
        return json.loads(response.read())


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--api-url", default="http://127.0.0.1:8000")
    parser.add_argument("--environment", required=True)
    parser.add_argument("--policy-version", required=True, type=int)
    parser.add_argument("--plan-id", required=True)
    args = parser.parse_args()
    identity = {
        "key_id": os.environ["OBSERVABILITY_RECONCILIATION_PRIMARY_KEY_ID"],
        "key_version": os.environ.get("OBSERVABILITY_RECONCILIATION_PRIMARY_KEY_VERSION", "v1"),
        "environment": args.environment,
        "policy_version": str(args.policy_version),
        "setting_key": "trace_sampling_percentage",
        "target": "collector",
        "deployment_attempt_id": str(uuid.uuid4()),
        "plan_id": str(uuid.UUID(args.plan_id)),
        "correlation_id": str(uuid.uuid4()),
    }
    secret = base64.b64decode(os.environ["OBSERVABILITY_RECONCILIATION_PRIMARY_SECRET"], validate=True)
    if len(secret) < 32:
        raise SystemExit("The reconciliation credential must contain at least 32 decoded bytes.")
    plan = signed_post(
        url=args.api_url,
        path="/api/internal/observability/reconciliation/plan",
        purpose="observability.policy.plan.read",
        identity=identity,
        body=b"[]",
        secret=secret,
    )["data"]
    if plan["setting_key"] != "trace_sampling_percentage" or plan["target"] != "collector":
        raise SystemExit("The returned plan is not a Collector sampling plan.")
    percentage = plan["desired_value"]
    if not isinstance(percentage, int | float) or not 0 <= percentage <= 100:
        raise SystemExit("The desired sampling percentage is invalid.")

    environment = os.environ.copy()
    environment["OTEL_TRACE_SAMPLING_PERCENTAGE"] = str(percentage)
    subprocess.run(
        ["docker", "compose", "up", "--detach", "--force-recreate", "otel-collector"],
        check=True,
        env=environment,
    )
    subprocess.run(
        ["docker", "compose", "exec", "-T", "otel-collector", "/otelcol", "validate", "--config=/etc/otelcol/config.yaml"],
        check=True,
    )
    container_id = subprocess.run(
        ["docker", "compose", "ps", "--quiet", "otel-collector"],
        check=True,
        capture_output=True,
        text=True,
    ).stdout.strip()
    configured_environment = subprocess.run(
        ["docker", "inspect", "--format", "{{json .Config.Env}}", container_id],
        check=True,
        capture_output=True,
        text=True,
    ).stdout
    observed = next(
        (
            value.split("=", 1)[1]
            for value in json.loads(configured_environment)
            if value.startswith("OTEL_TRACE_SAMPLING_PERCENTAGE=")
        ),
        None,
    )
    if observed != str(percentage):
        raise SystemExit("The Collector effective sampling value did not match the plan.")
    acknowledgement = json.dumps(
        {
            "outcome": "SUCCEEDED",
            "observed_value": percentage,
            "expected_digest": plan["expected_digest"],
            "effective_configuration_verified": True,
            "failure_category": None,
            "applied_at": datetime.now(UTC).replace(microsecond=0).isoformat(),
        },
        separators=(",", ":"),
    ).encode()
    result = signed_post(
        url=args.api_url,
        path="/api/internal/observability/reconciliation/acknowledgements",
        purpose="observability.policy.reconcile",
        identity=identity,
        body=acknowledgement,
        secret=secret,
    )
    print(json.dumps(result, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
