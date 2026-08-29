from __future__ import annotations

from typing import Any, Literal
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field


class ImmutableModel(BaseModel):
    model_config = ConfigDict(frozen=True, extra="forbid")


class CloneManifestReference(ImmutableModel):
    bucket: str = Field(min_length=1)
    object_key: str = Field(min_length=1, max_length=1024)
    checksum_sha256: str = Field(pattern=r"^[0-9a-f]{64}$")
    entry_count: int = Field(ge=0)
    schema_version: Literal["document-content-clone-manifest-v1"]


class ContentCloneVectorRequest(ImmutableModel):
    contract_version: Literal[1]
    request_id: UUID
    operation_id: UUID
    workspace_id: UUID
    source_document_id: UUID
    target_document_id: UUID
    source_event_id: UUID
    target_event_id: UUID
    lease_generation: int = Field(ge=1)
    lease_token: str = Field(min_length=1)
    manifest: CloneManifestReference
    pipeline_fingerprint: str = Field(pattern=r"^[0-9a-f]{64}$")
    pipeline_components: dict[str, Any]


class ContentCloneVectorResult(ImmutableModel):
    request_id: UUID
    complete: bool
    point_count: int = Field(ge=0)
    point_manifest_digest: str = Field(pattern=r"^[0-9a-f]{64}$")


class ContentCloneCleanupRequest(ImmutableModel):
    contract_version: Literal[1]
    request_id: UUID
    operation_id: UUID
    workspace_id: UUID
    target_document_id: UUID
    target_event_id: UUID
    pipeline_components: dict[str, Any]


class ContentCloneCleanupResult(ImmutableModel):
    request_id: UUID
    absent: bool
