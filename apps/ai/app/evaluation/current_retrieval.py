"""Fail-closed inputs for the provider-free current-retrieval gate."""

from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Literal
from uuid import UUID, uuid5

from pydantic import Field, model_validator

from app.evaluation.canonical import content_digest
from app.evaluation.live_hybrid_retrieval import EvaluationChunk
from app.evaluation.models import (
    EvaluationCase,
    EvaluationCorpus,
    EvidenceUnit,
    Identifier,
    QuestionVariant,
    StrictModel,
)
from app.retrieval.models import RetrievalPlan, RetrievalSide, SearchScope


class DocumentBinding(StrictModel):
    evaluation_document_version_id: Identifier
    public_document_id: UUID
    qdrant_document_id: UUID


class EligibilityEntry(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    question_digest: str = Field(pattern=r"^[a-f0-9]{64}$")
    outcome: Literal[
        "evidence_found",
        "no_eligible_evidence",
        "comparison_scope_incomplete",
        "clarification_required",
    ]
    reason: str | None
    clarification_source: str | None
    resolved_location_public_id: UUID | None
    document_public_ids_by_side: dict[
        Literal["primary", "comparison"], tuple[UUID, ...]
    ]


class DigestBinding(StrictModel):
    version: str
    digest: str = Field(pattern=r"^[a-f0-9]{64}$")


class EligibilityCatalogueBinding(DigestBinding):
    document_catalog_digest: str = Field(pattern=r"^[a-f0-9]{64}$")
    organisation_digest: str = Field(pattern=r"^[a-f0-9]{64}$")


class ResolverBinding(StrictModel):
    implementation: Literal["App\\Services\\Retrieval\\EligibilityResolver"]
    boundary: Literal["evaluation:resolve-current-eligibility"]
    source_digest: str = Field(pattern=r"^[a-f0-9]{64}$")
    configuration_digest: str = Field(pattern=r"^[a-f0-9]{64}$")


class IsolationEvidence(StrictModel):
    foreign_workspace_probe_executed: Literal[True]
    cross_workspace_document_count_in_scopes: Literal[0]


class NoActiveCorpusProbe(StrictModel):
    resolver_executed: Literal[True]
    outcome: Literal["no_eligible_evidence"]
    eligible_document_count: Literal[0]


class ResolverProbes(StrictModel):
    no_active_corpus_generation: NoActiveCorpusProbe


class EligibilityArtifact(StrictModel):
    schema_version: Literal["v1"]
    contract_id: Literal["deterministic-eligibility-v1"]
    run_id: str
    repository_commit: str = Field(pattern=r"^[a-f0-9]{40}$")
    evaluated_at: str
    workspace_public_id: UUID
    plan_catalogue: DigestBinding
    eligibility_catalogue: EligibilityCatalogueBinding
    resolver: ResolverBinding
    documents: tuple[DocumentBinding, ...] = Field(min_length=1)
    entries: tuple[EligibilityEntry, ...] = Field(min_length=1)
    probes: ResolverProbes
    isolation: IsolationEvidence
    comparability_digest: str = Field(pattern=r"^[a-f0-9]{64}$")
    artifact_digest: str = Field(pattern=r"^[a-f0-9]{64}$")

    @model_validator(mode="after")
    def unique_identifiers(self) -> EligibilityArtifact:
        versions = [item.evaluation_document_version_id for item in self.documents]
        public_ids = [item.public_document_id for item in self.documents]
        qdrant_ids = [item.qdrant_document_id for item in self.documents]
        variants = [(item.case_id, item.variant_id) for item in self.entries]
        if any(
            len(values) != len(set(values))
            for values in (versions, public_ids, qdrant_ids, variants)
        ):
            raise ValueError("eligibility artifact identities must be unique")
        return self


@dataclass(frozen=True)
class CurrentRetrievalInputs:
    corpus: EvaluationCorpus
    corpus_data: dict[str, Any]
    chunks: tuple[EvaluationChunk, ...]
    resolutions: dict[tuple[str, str], EligibilityEntry]
    scopes: dict[tuple[str, str], tuple[SearchScope, ...]]
    eligibility_correct: dict[tuple[str, str], bool]
    expected_eligibility: dict[tuple[str, str], dict[str, Any]]
    plan_questions: frozenset[str]
    plan_catalogue_checksum: str
    lineage: dict[str, Any]


def load_current_retrieval_inputs(
    *,
    snapshot_path: Path,
    document_catalog_path: Path,
    organisation_path: Path,
    source_root: Path,
    checksums_path: Path,
    plan_catalogue_path: Path,
    eligibility_artifact_path: Path,
    repository_commit: str,
) -> CurrentRetrievalInputs:
    snapshot = _object(snapshot_path)
    catalogue = _object(document_catalog_path)
    organisation = _object(organisation_path)
    checksums = _object(checksums_path)
    plans = _object(plan_catalogue_path)
    artifact_data = _object(eligibility_artifact_path)
    artifact = EligibilityArtifact.model_validate(artifact_data)

    _verify_artifact_digest(artifact_data)
    _verify_comparability_digest(artifact_data)
    if artifact.repository_commit != repository_commit:
        raise ValueError("eligibility artifact repository revision mismatch")
    if artifact.plan_catalogue.digest != content_digest(plans):
        raise ValueError("eligibility artifact plan catalogue digest mismatch")
    if artifact.eligibility_catalogue.document_catalog_digest != content_digest(
        catalogue
    ):
        raise ValueError("eligibility artifact document catalogue digest mismatch")
    if artifact.eligibility_catalogue.organisation_digest != content_digest(
        organisation
    ):
        raise ValueError("eligibility artifact organisation digest mismatch")
    eligibility_catalogue_body = {
        "version": artifact.eligibility_catalogue.version,
        "document_catalog_digest": artifact.eligibility_catalogue.document_catalog_digest,
        "organisation_digest": artifact.eligibility_catalogue.organisation_digest,
    }
    if artifact.eligibility_catalogue.digest != content_digest(
        eligibility_catalogue_body
    ):
        raise ValueError("eligibility catalogue digest mismatch")
    if checksums.get("benchmark_id") != "dolved-care-engineering":
        raise ValueError("compiled checksum catalogue identity mismatch")
    expected_catalogue_digest = checksums.get("files", {}).get("document-catalog.json")
    if expected_catalogue_digest != _sha256(document_catalog_path):
        raise ValueError("document catalogue source checksum mismatch")
    if checksums.get("files", {}).get("organisation.json") != _sha256(
        organisation_path
    ):
        raise ValueError("organisation source checksum mismatch")

    corpus_data = _evaluation_corpus(snapshot)
    corpus = EvaluationCorpus.model_validate(corpus_data)
    variants = {
        (case.case_id, variant.variant_id): variant.question
        for case in corpus.cases
        for variant in case.variants
    }
    plan_variants: dict[tuple[str, str], str] = {}
    plan_questions: set[str] = set()
    for item in plans.get("expectations", []):
        identity = (item["case_id"], item["variant_id"])
        question = item["question"]
        if identity in plan_variants or question in plan_questions:
            raise ValueError("authored plan identities and questions must be unique")
        contract = dict(item["contract"])
        if contract.pop("contract_version", None) != 2:
            raise ValueError("authored plan contract version must be 2")
        RetrievalPlan.model_validate({"retrieval_queries": [question], **contract})
        plan_variants[identity] = question
        plan_questions.add(question)
    entry_variants = {
        (item.case_id, item.variant_id): item.question_digest
        for item in artifact.entries
    }
    if (
        variants.keys() != plan_variants.keys()
        or variants.keys() != entry_variants.keys()
    ):
        raise ValueError("plan, corpus and eligibility variant populations differ")
    for identity, question in variants.items():
        if plan_variants[identity] != question:
            raise ValueError(f"authored plan question mismatch: {identity}")
        if entry_variants[identity] != hashlib.sha256(question.encode()).hexdigest():
            raise ValueError(f"eligibility question digest mismatch: {identity}")

    binding_by_version = {
        item.evaluation_document_version_id: item for item in artifact.documents
    }
    catalogue_versions = {
        version["version_id"]
        for family in catalogue.get("families", [])
        for version in family.get("versions", [])
    }
    if binding_by_version.keys() != catalogue_versions:
        raise ValueError(
            "eligibility document mapping does not cover the catalogue exactly"
        )
    public_ids = {item.public_document_id for item in artifact.documents}
    for entry in artifact.entries:
        scoped = {
            document_id
            for ids in entry.document_public_ids_by_side.values()
            for document_id in ids
        }
        if not scoped.issubset(public_ids):
            raise ValueError("eligibility entry contains an unmapped document")

    chunks = _source_chunks(
        catalogue=catalogue,
        source_root=source_root,
        checksum_files=checksums["files"],
        bindings=binding_by_version,
    )
    expected = {
        (case["case_id"], variant["variant_id"]): {
            "eligibility": case["eligibility_expectation"],
            "outcome": case["outcome_expectation"]["outcome"],
        }
        for case in snapshot.get("cases", [])
        for variant in case.get("variants", [])
    }
    mapping_digest = content_digest(
        [item.model_dump(mode="json") for item in artifact.documents]
    )
    return CurrentRetrievalInputs(
        corpus=corpus,
        corpus_data=corpus_data,
        chunks=chunks,
        resolutions={
            (item.case_id, item.variant_id): item for item in artifact.entries
        },
        scopes={
            (item.case_id, item.variant_id): scopes_for(item, artifact.documents)
            for item in artifact.entries
        },
        eligibility_correct={
            (item.case_id, item.variant_id): eligibility_is_correct(
                item, expected[(item.case_id, item.variant_id)], artifact.documents
            )
            for item in artifact.entries
        },
        expected_eligibility=expected,
        plan_questions=frozenset(plan_questions),
        plan_catalogue_checksum=content_digest(plans),
        lineage={
            "planner": {
                "provider": "deterministic",
                "model": "authored-engineering-plans-v2",
                "catalogue_version": artifact.plan_catalogue.version,
            },
            "eligibility_artifact_contract": artifact.contract_id,
            "eligibility_artifact_digest": artifact.artifact_digest,
            "eligibility_comparability_digest": artifact.comparability_digest,
            "eligibility_catalogue_version": artifact.eligibility_catalogue.version,
            "eligibility_catalogue_digest": artifact.eligibility_catalogue.digest,
            "eligibility_resolver_source_digest": artifact.resolver.source_digest,
            "eligibility_configuration_digest": artifact.resolver.configuration_digest,
            "eligibility_evaluated_at": artifact.evaluated_at,
            "eligibility_document_mapping_digest": mapping_digest,
        },
    )


def scopes_for(
    entry: EligibilityEntry,
    documents: tuple[DocumentBinding, ...],
) -> tuple[SearchScope, ...]:
    qdrant_by_public = {
        item.public_document_id: item.qdrant_document_id for item in documents
    }
    return tuple(
        SearchScope(
            side=(
                RetrievalSide.PRIMARY if side == "primary" else RetrievalSide.COMPARISON
            ),
            eligible_document_ids=tuple(qdrant_by_public[item] for item in ids),
        )
        for side, ids in entry.document_public_ids_by_side.items()
        if ids
    )


def eligibility_is_correct(
    entry: EligibilityEntry,
    expected: dict[str, Any],
    bindings: tuple[DocumentBinding, ...],
) -> bool:
    version_by_public = {
        str(item.public_document_id): item.evaluation_document_version_id
        for item in bindings
    }
    actual = {
        (side.upper(), version_by_public[str(public_id)])
        for side, ids in entry.document_public_ids_by_side.items()
        for public_id in ids
    }
    wanted = {
        (item["side"], item["document_version_id"])
        for item in expected["eligibility"].get("eligible_versions", [])
    }
    excluded = {
        item["document_version_id"]
        for item in expected["eligibility"].get("excluded_versions", [])
    }
    actual_versions = {version for _side, version in actual}
    if expected["outcome"] == "CLARIFICATION_REQUIRED":
        return entry.outcome == "clarification_required" and not actual
    expected_outcome = expected["eligibility"].get("expected_outcome")
    if expected_outcome == "ELIGIBLE_SCOPE_READY":
        return (
            entry.outcome == "evidence_found"
            and wanted.issubset(actual)
            and actual_versions.isdisjoint(excluded)
        )
    return actual_versions.isdisjoint(excluded)


def _evaluation_corpus(snapshot: dict[str, Any]) -> dict[str, Any]:
    cases = []
    for case in snapshot.get("cases", []):
        cases.append(
            EvaluationCase(
                case_id=case["case_id"],
                variants=tuple(
                    QuestionVariant.model_validate(item) for item in case["variants"]
                ),
                slices=tuple(case["slices"]),
                evidence_units=tuple(
                    EvidenceUnit.model_validate(
                        {
                            key: value
                            for key, value in item.items()
                            if key in EvidenceUnit.model_fields
                        }
                    )
                    for item in case["retrieval_expectation"]["evidence_units"]
                ),
                expected_temporal_mode=case["planner_expectation"]["temporal_mode"],
                expected_outcome=case["outcome_expectation"]["outcome"],
            ).model_dump(mode="json")
        )
    return {
        "schema_version": "v2",
        "corpus_version": "2",
        "title": "Dolved Care engineering V2 isolated current retrieval",
        "matching_algorithm": "canonical-token-coverage-v1",
        "cases": cases,
    }


def _source_chunks(
    *,
    catalogue: dict[str, Any],
    source_root: Path,
    checksum_files: dict[str, str],
    bindings: dict[str, DocumentBinding],
) -> tuple[EvaluationChunk, ...]:
    chunks = []
    for family in catalogue["families"]:
        for version in family["versions"]:
            relative = version["source_path"]
            path = source_root / relative
            if _sha256(path) != checksum_files.get(relative):
                raise ValueError(f"source document checksum mismatch: {relative}")
            binding = bindings[version["version_id"]]
            chunks.append(
                EvaluationChunk(
                    candidate_id="source." + version["version_id"],
                    chunk_id=uuid5(
                        binding.qdrant_document_id, "source-document-whole-v1"
                    ),
                    document_id=binding.qdrant_document_id,
                    document_family_id=family["family_id"],
                    document_version_id=version["version_id"],
                    text=path.read_text(),
                )
            )
    return tuple(chunks)


def _verify_artifact_digest(value: dict[str, Any]) -> None:
    body = dict(value)
    recorded = body.pop("artifact_digest", None)
    if recorded != content_digest(body):
        raise ValueError("eligibility artifact digest mismatch")


def _verify_comparability_digest(value: dict[str, Any]) -> None:
    recorded = value.get("comparability_digest")
    body = {
        key: value[key]
        for key in (
            "schema_version",
            "contract_id",
            "evaluated_at",
            "plan_catalogue",
            "eligibility_catalogue",
            "resolver",
            "documents",
            "entries",
            "probes",
            "isolation",
        )
    }
    if recorded != content_digest(body):
        raise ValueError("eligibility comparability digest mismatch")


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def _object(path: Path) -> dict[str, Any]:
    value = json.loads(path.read_text())
    if not isinstance(value, dict):
        raise TypeError(f"JSON input must be an object: {path}")
    return value
