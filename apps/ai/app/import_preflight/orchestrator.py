import hashlib
import io
import zipfile
from dataclasses import dataclass
from typing import Any

import httpx
import pypdfium2 as pdfium  # type: ignore[import-untyped]

from app.import_preflight.client import ImportPreflightClient


@dataclass(frozen=True)
class ImportPreflightOutcome:
    code: str
    acknowledge: bool


class ImportPreflightOrchestrator:
    def __init__(
        self,
        *,
        client: ImportPreflightClient,
        timeout_seconds: float,
        http_client: httpx.Client | None = None,
    ) -> None:
        self._client = client
        self._http = http_client or httpx.Client(timeout=timeout_seconds)

    def process(self, event: dict[str, Any]) -> ImportPreflightOutcome:
        try:
            response = self._http.get(str(event["staged_object"]["read_url"]))
            response.raise_for_status()
        except httpx.TimeoutException:
            self._client.fail(event, "read_timeout")
            return ImportPreflightOutcome("read_timeout", True)
        except httpx.HTTPError:
            self._client.fail(event, "source_unavailable")
            return ImportPreflightOutcome("source_unavailable", True)

        source = response.content
        result = self._inspect(source, str(event["declared_media_type"]))
        self._client.complete(event, result)
        return ImportPreflightOutcome(str(result["result"]), True)

    @staticmethod
    def _inspect(source: bytes, declared_media_type: str) -> dict[str, Any]:
        detected = ImportPreflightOrchestrator._detect_media_type(source)
        if (
            declared_media_type
            == "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            and source.startswith(b"PK")
        ):
            detected = declared_media_type
        if (
            detected == "application/x-ole-storage"
            and declared_media_type
            == "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        ):
            return {"result": "encrypted", "diagnostic_code": "office_encrypted"}
        if detected != declared_media_type:
            return {
                "result": "mime_mismatch",
                "diagnostic_code": "declared_type_mismatch",
            }

        if detected == "application/pdf":
            try:
                document = pdfium.PdfDocument(source)
                document.close()
            except pdfium.PdfiumError as exception:
                if "password" in str(exception).lower():
                    return {
                        "result": "password_protected",
                        "diagnostic_code": "pdf_password_required",
                    }
                return {
                    "result": "corrupt_structure",
                    "diagnostic_code": "invalid_container",
                }
        elif (
            detected
            == "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        ):
            try:
                with zipfile.ZipFile(io.BytesIO(source)) as package:
                    if "word/document.xml" not in package.namelist():
                        return {
                            "result": "corrupt_structure",
                            "diagnostic_code": "invalid_container",
                        }
                    if package.testzip() is not None:
                        return {
                            "result": "corrupt_structure",
                            "diagnostic_code": "invalid_container",
                        }
            except zipfile.BadZipFile, RuntimeError:
                return {
                    "result": "corrupt_structure",
                    "diagnostic_code": "invalid_container",
                }
        elif detected.startswith("text/"):
            try:
                source.decode("utf-8")
            except UnicodeDecodeError:
                return {
                    "result": "corrupt_structure",
                    "diagnostic_code": "invalid_container",
                }

        return {
            "result": "readable",
            "diagnostic_code": "readable",
            "source_checksum_sha256": hashlib.sha256(source).hexdigest(),
            "media_type": detected,
            "size_bytes": len(source),
        }

    @staticmethod
    def _detect_media_type(source: bytes) -> str:
        if source.startswith(b"%PDF-"):
            return "application/pdf"
        if source.startswith(bytes.fromhex("d0cf11e0a1b11ae1")):
            return "application/x-ole-storage"
        if source.startswith(b"PK"):
            try:
                with zipfile.ZipFile(io.BytesIO(source)) as package:
                    if "word/document.xml" in package.namelist():
                        return "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            except zipfile.BadZipFile:
                pass
            return "application/zip"
        try:
            source.decode("utf-8")
        except UnicodeDecodeError:
            return "application/octet-stream"
        return "text/plain"
