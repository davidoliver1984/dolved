class ChunkingError(Exception):
    """Base class for typed chunking failures."""


class UnrepresentableContentError(ChunkingError):
    """Raised when chunkable content cannot be represented without loss."""
