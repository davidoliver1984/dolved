from app.settings import Settings
from app.vector_store.protocol import VectorStore
from app.vector_store.qdrant import QdrantVectorStore


def create_vector_store(settings: Settings) -> VectorStore:
    api_key = settings.qdrant_api_key.get_secret_value().strip()
    return QdrantVectorStore.connect(
        url=settings.qdrant_url,
        api_key=api_key or None,
        timeout_seconds=settings.qdrant_timeout_seconds,
    )
