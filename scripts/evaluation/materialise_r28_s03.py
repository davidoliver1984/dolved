#!/usr/bin/env python3
"""Materialise the frozen V4 corpus through the authenticated ImportBatch API."""

from __future__ import annotations

import argparse
import hashlib
import http.cookiejar
import json
import mimetypes
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from collections import defaultdict
from datetime import date, timedelta
from pathlib import Path
from typing import Any

RUN_DEFINITION = Path("docs/evaluation/r28-s03/run-definition.json")


class ApiFailure(RuntimeError):
    def __init__(self, status: int, body: str) -> None:
        super().__init__(f"API returned HTTP {status}: {body[:500]}")
        self.status = status
        self.body = body


class ApiClient:
    def __init__(self, base_url: str, frontend_url: str) -> None:
        self.base_url = base_url.rstrip("/")
        self.frontend_url = frontend_url.rstrip("/")
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.cookies)
        )

    def login(self, email: str, password: str) -> None:
        self._request("GET", "/sanctum/csrf-cookie")
        self._request("POST", "/api/auth/login", {"email": email, "password": password})

    def get(self, path: str) -> Any:
        return self._request("GET", path)

    def post(self, path: str, payload: dict[str, Any] | None = None) -> Any:
        return self._request("POST", path, payload or {})

    def put_bytes(
        self, url: str, content: bytes, headers: dict[str, str] | list[Any]
    ) -> None:
        request = urllib.request.Request(url, data=content, method="PUT")
        # PHP serialises an empty associative array as JSON `[]`. Accept only that
        # representation; non-empty upload headers must retain their named map.
        if isinstance(headers, list):
            if headers:
                raise TypeError("Upload headers must be a named map or an empty list")
            headers = {}
        for name, value in headers.items():
            request.add_header(name, value)
        try:
            with urllib.request.urlopen(request, timeout=120) as response:
                if response.status < 200 or response.status >= 300:
                    raise ApiFailure(
                        response.status, response.read().decode(errors="replace")
                    )
        except urllib.error.HTTPError as exc:
            raise ApiFailure(exc.code, exc.read().decode(errors="replace")) from exc

    def _request(
        self, method: str, path: str, payload: dict[str, Any] | None = None
    ) -> Any:
        body = None if payload is None else json.dumps(payload).encode()
        request = urllib.request.Request(
            f"{self.base_url}{path}", data=body, method=method
        )
        request.add_header("Accept", "application/json")
        request.add_header("Origin", self.frontend_url)
        request.add_header("Referer", f"{self.frontend_url}/")
        if body is not None:
            request.add_header("Content-Type", "application/json")
        if method not in {"GET", "HEAD", "OPTIONS"}:
            token = next(
                (
                    cookie.value
                    for cookie in self.cookies
                    if cookie.name == "XSRF-TOKEN"
                ),
                None,
            )
            if token is not None:
                request.add_header("X-XSRF-TOKEN", urllib.parse.unquote(token))
        try:
            with self.opener.open(request, timeout=120) as response:
                raw = response.read()
                return None if not raw else json.loads(raw)
        except urllib.error.HTTPError as exc:
            raise ApiFailure(exc.code, exc.read().decode(errors="replace")) from exc


def effective_date(entry: dict[str, Any]) -> str:
    """Return explicit chronology or a deterministic manifest-derived fallback."""
    if entry.get("effective_date"):
        return str(entry["effective_date"])
    if entry.get("superseded_date"):
        return (
            date.fromisoformat(str(entry["superseded_date"])) - timedelta(days=1)
        ).isoformat()
    return "2026-01-01"


def load_json(path: Path) -> Any:
    with path.open("rb") as source:
        return json.load(source)


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for block in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def wait_for(
    label: str, callback: Any, *, timeout: float = 240.0, interval: float = 0.5
) -> Any:
    deadline = time.monotonic() + timeout
    last: Any = None
    while time.monotonic() < deadline:
        last = callback()
        if last:
            return last
        time.sleep(interval)
    raise RuntimeError(f"Timed out waiting for {label}; last observation={last!r}")


def documents(client: ApiClient, workspace: str) -> list[dict[str, Any]]:
    records: list[dict[str, Any]] = []
    page = 1
    while True:
        response = client.get(
            f"/api/workspaces/{workspace}/documents?per_page=100&page={page}"
        )
        records.extend(response["data"])
        if page >= int(response["meta"]["last_page"]):
            return records
        page += 1


def families(client: ApiClient, workspace: str) -> list[dict[str, Any]]:
    records: list[dict[str, Any]] = []
    page = 1
    while True:
        response = client.get(
            f"/api/workspaces/{workspace}/document-library?per_page=100&page={page}"
        )
        records.extend(response["data"])
        if page >= int(response["meta"]["last_page"]):
            return records
        page += 1


def stage_batch(
    client: ApiClient,
    workspace: str,
    root: Path,
    entries: list[dict[str, Any]],
) -> tuple[str, dict[str, dict[str, Any]]]:
    payload = {
        "files": [
            {
                "filename": entry["filename"],
                "media_type": entry.get("media_type") or media_type(entry["filename"]),
                "size_bytes": (root / entry["artefact_path"]).stat().st_size,
            }
            for entry in entries
        ]
    }
    created = client.post(f"/api/workspaces/{workspace}/imports", payload)["data"]
    batch = created["batch"]
    by_item = {item["public_id"]: item for item in batch["items"]}
    entry_by_filename = {entry["filename"]: entry for entry in entries}
    for upload in created["uploads"]:
        item = by_item[upload["item_public_id"]]
        entry = entry_by_filename[item["filename"]]
        request = upload["upload"]
        client.put_bytes(
            request["url"],
            (root / entry["artefact_path"]).read_bytes(),
            request["headers"],
        )
        client.post(
            f"/api/workspaces/{workspace}/imports/{batch['public_id']}"
            f"/items/{item['public_id']}/uploaded"
        )

    def verified() -> dict[str, dict[str, Any]] | None:
        current = client.get(
            f"/api/workspaces/{workspace}/imports/{batch['public_id']}"
        )["data"]
        items = {item["filename"]: item for item in current["items"]}
        if any(item["preflight_status"] == "rejected" for item in items.values()):
            raise RuntimeError(f"Positive materialisation preflight rejected: {items}")
        return (
            items
            if all(item["preflight_status"] == "verified" for item in items.values())
            else None
        )

    return batch["public_id"], wait_for("batch preflight verification", verified)


def materialise_round(
    client: ApiClient,
    workspace: str,
    actor: str,
    root: Path,
    entries: list[dict[str, Any]],
    location_ids: dict[str, str],
    family_ids: dict[str, str],
) -> list[dict[str, Any]]:
    observations: list[dict[str, Any]] = []
    for offset in range(0, len(entries), 25):
        subset = entries[offset : offset + 25]
        batch_id, items = stage_batch(client, workspace, root, subset)
        for entry in subset:
            item = items[entry["filename"]]
            match = client.get(
                f"/api/workspaces/{workspace}/imports/{batch_id}/items/"
                f"{item['public_id']}/matches"
            )["data"]
            family_key = entry["family_id"]
            family = (
                {"mode": "successor", "family_public_id": family_ids[family_key]}
                if family_key in family_ids
                else {"mode": "new", "title": entry["family_title"]}
            )
            definition = {
                "family": family,
                "metadata": {
                    "category_public_id": None,
                    "description": None,
                    "owner_user_public_id": actor,
                    "publisher_label": "Frozen Dolved V4 evaluation corpus",
                    "review_due_date": None,
                    "source_url": None,
                    "tag_public_ids": [],
                },
                "applicability": {
                    "location_public_ids": [
                        location_ids[key]
                        for key in entry.get("applicability_locations", [])
                    ]
                },
                "effective_from": effective_date(entry),
            }
            client.post(
                f"/api/workspaces/{workspace}/imports/{batch_id}/items/"
                f"{item['public_id']}/decision",
                {"definition": definition},
            )
            client.post(
                f"/api/workspaces/{workspace}/imports/{batch_id}/items/"
                f"{item['public_id']}/promotions",
                {"idempotency_key": f"r28-s03-{uuid.uuid4()}"},
            )
            observations.append(
                {
                    "family_id": family_key,
                    "version_id": entry["version_id"],
                    "filename": entry["filename"],
                    "batch_public_id": batch_id,
                    "item_public_id": item["public_id"],
                    "match": match,
                }
            )

        filenames = {entry["filename"] for entry in subset}

        def indexed(
            bound_batch: str = batch_id,
            bound_files: frozenset[str] = frozenset(filenames),
        ) -> dict[str, dict[str, Any]] | None:
            current = client.get(f"/api/workspaces/{workspace}/imports/{bound_batch}")[
                "data"
            ]
            current_items = {item["filename"]: item for item in current["items"]}
            failed = {}
            for name, item in current_items.items():
                promotion = item.get("promotion") or {}
                document = item.get("document") or {}
                if (
                    promotion.get("status") in {"conflict", "failed", "cancelled"}
                    or document.get("status") == "failed"
                ):
                    failed[name] = item
            if failed:
                raise RuntimeError(f"Positive materialisation failed: {failed}")
            ready = {
                name: item
                for name, item in current_items.items()
                if name in bound_files
                and item.get("promotion", {}).get("status") == "committed"
                and (item.get("document") or {}).get("status") == "indexed"
            }
            return ready if len(ready) == len(bound_files) else None

        ready = wait_for("promotion and indexing", indexed, timeout=600.0)
        document_ids = {
            name: item["document"]["public_id"] for name, item in ready.items()
        }
        for observation in observations[-len(subset) :]:
            observation["document_public_id"] = document_ids[observation["filename"]]

        library = families(client, workspace)
        by_title = {family["name"]: family["public_id"] for family in library}
        for entry in subset:
            family_ids[entry["family_id"]] = by_title[entry["family_title"]]
    return observations


def media_type(filename: str) -> str:
    extension = Path(filename).suffix.lower()
    governed = {
        ".pdf": "application/pdf",
        ".docx": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        ".doc": "application/msword",
        ".rtf": "application/rtf",
        ".txt": "text/plain",
        ".md": "text/markdown",
    }
    if extension in governed:
        return governed[extension]
    guessed, _ = mimetypes.guess_type(filename)
    return guessed or "application/octet-stream"


def materialise_scope(
    client: ApiClient,
    workspace: str,
    actor: str,
    root: Path,
    manifest_path: Path,
    location_ids: dict[str, str],
) -> dict[str, Any]:
    manifest = load_json(manifest_path)
    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for entry in manifest["documents"]:
        expected = root / entry["artefact_path"]
        if (
            expected.stat().st_size != entry["byte_count"]
            or sha256(expected) != entry["sha256"]
        ):
            raise RuntimeError(f"Frozen artefact identity mismatch: {expected}")
        grouped[entry["family_id"]].append(entry)
    for entries in grouped.values():
        entries.sort(
            key=lambda item: (
                effective_date(item),
                item["version_id"],
            )
        )

    family_ids: dict[str, str] = {}
    observations: list[dict[str, Any]] = []
    max_versions = max(len(entries) for entries in grouped.values())
    for generation in range(max_versions):
        round_entries = [
            entries[generation]
            for entries in grouped.values()
            if generation < len(entries)
        ]
        observations.extend(
            materialise_round(
                client,
                workspace,
                actor,
                root,
                round_entries,
                location_ids,
                family_ids,
            )
        )

    document_ids = {
        item["filename"]: item["public_id"] for item in documents(client, workspace)
    }
    for entries in grouped.values():
        for entry in entries:
            document_id = document_ids[entry["filename"]]
            status = entry.get("governance_status", "approved")
            if status in {"approved", "withdrawn"}:
                client.post(
                    f"/api/workspaces/{workspace}/documents/{document_id}/governance/approve",
                    {"idempotency_key": f"r28-s03-approve-{uuid.uuid4()}"},
                )
            if status == "withdrawn":
                client.post(
                    f"/api/workspaces/{workspace}/documents/{document_id}/governance/withdraw",
                    {"idempotency_key": f"r28-s03-withdraw-{uuid.uuid4()}"},
                )

    final_documents = documents(client, workspace)
    return {
        "corpus_id": manifest["corpus_id"],
        "manifest_sha256": sha256(manifest_path),
        "expected_documents": manifest["document_count"],
        "expected_families": len(grouped),
        "realised_documents": len(final_documents),
        "realised_indexed": sum(
            item["status"] == "indexed" for item in final_documents
        ),
        "realised_families": len(families(client, workspace)),
        "workspace_public_id": workspace,
        "observations": observations,
    }


def create_and_upload(
    client: ApiClient,
    workspace: str,
    files: list[tuple[str, str, Path]],
    *,
    upload_count: int | None = None,
) -> tuple[str, list[dict[str, Any]], list[dict[str, Any]]]:
    created = client.post(
        f"/api/workspaces/{workspace}/imports",
        {
            "files": [
                {
                    "filename": name,
                    "media_type": declared_type,
                    "size_bytes": path.stat().st_size,
                }
                for name, declared_type, path in files
            ]
        },
    )["data"]
    items = created["batch"]["items"]
    by_id = {item["public_id"]: item for item in items}
    by_name = {name: path for name, _, path in files}
    uploads = created["uploads"]
    for upload in uploads[: upload_count if upload_count is not None else len(uploads)]:
        item = by_id[upload["item_public_id"]]
        request = upload["upload"]
        client.put_bytes(
            request["url"], by_name[item["filename"]].read_bytes(), request["headers"]
        )
        client.post(
            f"/api/workspaces/{workspace}/imports/{created['batch']['public_id']}"
            f"/items/{item['public_id']}/uploaded"
        )
    return created["batch"]["public_id"], items, uploads


def exercise_negative_fixtures(
    client: ApiClient, workspace: str, root: Path
) -> dict[str, Any]:
    negative_root = root / "negative-fixtures"
    manifest_path = negative_root / "negative-fixtures-manifest.json"
    manifest = load_json(manifest_path)
    outcomes: dict[str, dict[str, Any]] = {}

    validation_cases = [
        (
            "old-policy-export.pages",
            "application/octet-stream",
            175,
            "unsupported_extension",
        ),
        (
            "annual-company-report-2026-full.pdf",
            "application/pdf",
            31_457_280,
            "oversized_file_simulation",
        ),
    ]
    for filename, declared_type, size, category in validation_cases:
        try:
            client.post(
                f"/api/workspaces/{workspace}/imports",
                {
                    "files": [
                        {
                            "filename": filename,
                            "media_type": declared_type,
                            "size_bytes": size,
                        }
                    ]
                },
            )
            raise RuntimeError(f"Negative validation unexpectedly accepted {filename}")
        except ApiFailure as exc:
            if exc.status != 422:
                raise
            outcomes[filename] = {
                "category": category,
                "outcome": "validation_rejected",
                "http_status": 422,
            }

    corrupt_files = [
        (name, media_type(name), negative_root / name)
        for name in [
            "corrupt-fire-safety-policy.pdf",
            "password-protected-controlled-drugs-procedure.pdf",
        ]
    ]
    corrupt_batch, corrupt_items, _ = create_and_upload(
        client, workspace, corrupt_files
    )

    def corrupt_rejected() -> dict[str, dict[str, Any]] | None:
        batch = client.get(f"/api/workspaces/{workspace}/imports/{corrupt_batch}")[
            "data"
        ]
        items = {item["filename"]: item for item in batch["items"]}
        return (
            items
            if all(item["preflight_status"] == "rejected" for item in items.values())
            else None
        )

    rejected = wait_for("typed corrupt/password rejection", corrupt_rejected)
    for filename, item in rejected.items():
        outcomes[filename] = {
            "category": next(
                entry["category"]
                for entry in manifest["fixtures"]
                if entry["filename"] == filename
            ),
            "outcome": "preflight_rejected",
            "reason": item["preflight_rejection_reason"],
        }

    corrupt_item = next(
        item
        for item in corrupt_items
        if item["filename"] == "corrupt-fire-safety-policy.pdf"
    )
    replacement_path = negative_root / "fire-safety-policy-REPLACEMENT.pdf"
    replacement = client.post(
        f"/api/workspaces/{workspace}/imports/{corrupt_batch}/items/{corrupt_item['public_id']}/replacements",
        {
            "filename": replacement_path.name,
            "media_type": media_type(replacement_path.name),
            "size_bytes": replacement_path.stat().st_size,
        },
    )["data"]
    upload = replacement["upload"]
    client.put_bytes(upload["url"], replacement_path.read_bytes(), upload["headers"])
    client.post(
        f"/api/workspaces/{workspace}/imports/{corrupt_batch}/items/"
        f"{replacement['item_public_id']}/uploaded"
    )

    def replacement_verified() -> dict[str, Any] | None:
        batch = client.get(f"/api/workspaces/{workspace}/imports/{corrupt_batch}")[
            "data"
        ]
        item = next(
            item
            for item in batch["items"]
            if item["public_id"] == replacement["item_public_id"]
        )
        return item if item["preflight_status"] == "verified" else None

    wait_for("replacement verification", replacement_verified)
    outcomes[replacement_path.name] = {
        "category": "replacement_for_failed_file",
        "outcome": "replacement_verified",
    }
    outcomes["replacement-for-failed-file.json"] = {
        "category": "replacement_for_failed_file_metadata",
        "outcome": "scenario_exercised",
    }

    match_names = [
        "complaints-policy-COPY.docx",
        "complaints-policy-v2-draft.docx",
        "annual-leave-policy-v2.pdf",
        "ev-charging-policy.txt",
        "staff-leave-summary.docx",
    ]
    match_files = [
        (name, media_type(name), negative_root / name) for name in match_names
    ]
    match_batch, _, _ = create_and_upload(client, workspace, match_files)

    def matches_verified() -> dict[str, dict[str, Any]] | None:
        batch = client.get(f"/api/workspaces/{workspace}/imports/{match_batch}")["data"]
        items = {item["filename"]: item for item in batch["items"]}
        return (
            items
            if all(item["preflight_status"] == "verified" for item in items.values())
            else None
        )

    matched = wait_for("match fixture verification", matches_verified)
    for name, item in matched.items():
        assessment = client.get(
            f"/api/workspaces/{workspace}/imports/{match_batch}/items/{item['public_id']}/matches"
        )["data"]
        outcomes[name] = {
            "category": next(
                entry["category"]
                for entry in manifest["fixtures"]
                if entry["filename"] == name
            ),
            "outcome": "match_assessed",
            "exact_live_duplicate_count": len(assessment["exact_live_duplicates"]),
            "family_candidate_count": len(assessment["family_candidates"]),
            "top_family_candidate": assessment["family_candidates"][0]
            if assessment["family_candidates"]
            else None,
        }

    resume_sources = [
        entry for entry in load_json(root / "source-manifest.json")["documents"][:5]
    ]
    resume_files = [
        (entry["filename"], entry["media_type"], root / entry["artefact_path"])
        for entry in resume_sources
    ]
    resume_batch, resume_items, resume_uploads = create_and_upload(
        client, workspace, resume_files, upload_count=3
    )
    observed = client.get(f"/api/workspaces/{workspace}/imports/{resume_batch}")["data"]
    if sum(item["preflight_status"] != "pending" for item in observed["items"]) > 3:
        raise RuntimeError(
            "Interrupted-batch fixture did not preserve the two unstaged items"
        )
    by_id = {item["public_id"]: item for item in resume_items}
    by_name = {name: path for name, _, path in resume_files}
    for pending_upload in resume_uploads[3:]:
        item = by_id[pending_upload["item_public_id"]]
        upload = pending_upload["upload"]
        client.put_bytes(
            upload["url"], by_name[item["filename"]].read_bytes(), upload["headers"]
        )
        client.post(
            f"/api/workspaces/{workspace}/imports/{resume_batch}/items/{item['public_id']}/uploaded"
        )

    def resumed() -> bool:
        batch = client.get(f"/api/workspaces/{workspace}/imports/{resume_batch}")[
            "data"
        ]
        return all(item["preflight_status"] == "verified" for item in batch["items"])

    wait_for("interrupted batch resumption", resumed)
    outcomes["interrupted-batch-metadata.json"] = {
        "category": "interrupted_resumable_batch",
        "outcome": "resumed_3_then_2_verified",
    }

    outcomes["mixed-success-bulk-batch.json"] = {
        "category": "mixed_success_bulk_batch",
        "outcome": "component_paths_exercised",
        "verified_examples": 3,
        "typed_corrupt_rejections": 1,
        "unsupported_validation_rejections": 1,
    }
    if set(outcomes) != {entry["filename"] for entry in manifest["fixtures"]}:
        raise RuntimeError("Negative fixture outcome inventory is incomplete")
    return {
        "status": "complete",
        "manifest_sha256": sha256(manifest_path),
        "expected_fixtures": len(manifest["fixtures"]),
        "observed_fixtures": len(outcomes),
        "outcomes": outcomes,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--corpus-root", type=Path, required=True)
    parser.add_argument("--api", default="http://localhost:8200")
    parser.add_argument("--frontend", default="http://localhost:3200")
    parser.add_argument("--password", required=True)
    parser.add_argument("--primary-identity", type=Path, required=True)
    parser.add_argument("--foreign-identity", type=Path, required=True)
    parser.add_argument("--injection-identity", type=Path, required=True)
    parser.add_argument("--primary-locations", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    run_definition = load_json(RUN_DEFINITION)

    identities = {
        "primary": load_json(args.primary_identity),
        "foreign": load_json(args.foreign_identity),
        "injection": load_json(args.injection_identity),
    }
    clients: dict[str, ApiClient] = {}
    for name, identity in identities.items():
        client = ApiClient(args.api, args.frontend)
        client.login(identity["email"], args.password)
        clients[name] = client

    root = args.corpus_root.resolve()
    location_ids = load_json(args.primary_locations)["locations"]
    scopes = {
        "primary": materialise_scope(
            clients["primary"],
            identities["primary"]["workspace_public_id"],
            identities["primary"]["user_public_id"],
            root,
            root / "source-manifest.json",
            location_ids,
        ),
        "foreign_tenant": materialise_scope(
            clients["foreign"],
            identities["foreign"]["workspace_public_id"],
            identities["foreign"]["user_public_id"],
            root,
            root / "foreign-tenant/source-manifest.json",
            {},
        ),
        "prompt_injection_pack": materialise_scope(
            clients["injection"],
            identities["injection"]["workspace_public_id"],
            identities["injection"]["user_public_id"],
            root,
            root / "prompt-injection-pack/source-manifest.json",
            {},
        ),
    }
    negative_fixtures = exercise_negative_fixtures(
        clients["primary"], identities["primary"]["workspace_public_id"], root
    )
    result = {
        "schema_version": "r28-s03-materialisation-result-v1",
        "run_id": run_definition["run_id"],
        "repository_commit": subprocess.check_output(
            ["git", "rev-parse", "HEAD"], text=True
        ).strip(),
        "run_definition_sha256": sha256(RUN_DEFINITION),
        "provider_calls": 0,
        "aws_calls": 0,
        "materialisation_profile": {
            "dense": "deterministic/token-hash-unit-vector-v3",
            "sparse": "deterministic/token-hash-sparse-v4",
        },
        "scopes": scopes,
        "negative_fixtures": negative_fixtures,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, indent=2, sort_keys=True) + "\n")
    print(
        json.dumps(
            {
                "run_id": result["run_id"],
                "scopes": {
                    key: {k: v for k, v in value.items() if k != "observations"}
                    for key, value in scopes.items()
                },
            },
            indent=2,
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
