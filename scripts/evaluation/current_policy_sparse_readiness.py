"""Offline infrastructure preflight; never submits a question or encodes a corpus.

Run in an isolated no-egress container against the deployment's exact cache and
configured model/revision, before any future smoke authorisation is consumed.
No download fallback. A fresh container must pass again; an old receipt is not proof.
"""
import hashlib
import json
import os
from pathlib import Path
import socket

os.environ["HF_HUB_OFFLINE"] = "1"
os.environ["TRANSFORMERS_OFFLINE"] = "1"
attempts = []


def denied(*args, **kwargs):
    attempts.append("network_attempt_denied")
    raise OSError("Offline readiness forbids network access")


socket.socket.connect = denied
socket.socket.connect_ex = denied
socket.create_connection = denied

from app.settings import Settings  # noqa: E402
from app.sparse.factory import create_sparse_encoder  # noqa: E402

settings = Settings()
cache = Path(settings.sparse_embedding_cache_dir)


def digest(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


record = {key: getattr(settings, key) for key in (
    "sparse_embedding_provider", "sparse_embedding_model",
    "sparse_embedding_source_repository", "sparse_embedding_model_revision",
    "sparse_embedding_cache_dir",
)}
record["cache_files"] = {
    str(p.relative_to(cache)): digest(p)
    for p in sorted(cache.rglob("*")) if p.is_file() and not p.is_symlink()
}
try:
    encoder = create_sparse_encoder(settings)
    encoder._get_engine()
    record["loaded"] = True
except Exception as error:
    record.update(loaded=False, failure_type=type(error).__name__)
record.update(network_attempts=attempts, offline=True, encoded_questions=0)
print(json.dumps(record, indent=2))
raise SystemExit(0 if record["loaded"] and not attempts else 1)
