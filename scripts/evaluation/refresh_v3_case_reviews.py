"""Refresh digest bindings for already-reviewed Benchmark V3 cases."""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any

AI_ROOT = Path(__file__).resolve().parents[2] / "apps/ai"
sys.path.insert(0, str(AI_ROOT))

from app.evaluation.benchmark.common import (
    canonical_bytes,
    content_digest,
    digest_bytes,
)
from app.evaluation.benchmark.v3 import source_catalog_digest


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--benchmark-root", type=Path, required=True)
    parser.add_argument("--review-version", required=True)
    parser.add_argument("--reviewer-identity", required=True)
    parser.add_argument("--reviewed-at", required=True)
    parser.add_argument("--review-note", required=True)
    return parser.parse_args()


def load_json(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text())


def main() -> None:
    values = arguments()
    root: Path = values.benchmark_root
    cases = {
        case["case_id"]: case
        for source_path in sorted((root / "cases").glob("*.json"))
        for case in load_json(source_path)["cases"]
    }
    reviews = sorted((root / "reviews/cases").glob("*.json"))
    if {load_json(path)["case_id"] for path in reviews} != set(cases):
        raise ValueError("review identities do not match authored cases")

    catalog_digest = source_catalog_digest(root, root / "document-catalog.json")
    for review_path in reviews:
        review = load_json(review_path)
        case = cases[review["case_id"]]
        if case["authoring_status"] != "REVIEWED":
            raise ValueError(f"case is not reviewed: {case['case_id']}")
        review.update(
            {
                "review_version": values.review_version,
                "case_sha256": digest_bytes(canonical_bytes(case)),
                "source_catalog_digest": catalog_digest,
                "source_lineage_digest": content_digest(case["source_lineage"]),
                "evidence_unit_ids_digest": content_digest(
                    sorted(
                        evidence["evidence_id"]
                        for evidence in case["retrieval_expectation"]["evidence_units"]
                    )
                ),
                "reviewer_identity": values.reviewer_identity,
                "reviewed_at": values.reviewed_at,
                "review_note": values.review_note,
            }
        )
        review_path.write_text(json.dumps(review, indent=2, ensure_ascii=False) + "\n")


if __name__ == "__main__":
    main()
