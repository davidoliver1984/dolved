from enum import StrEnum


class ExtractionFailureKind(StrEnum):
    TRANSIENT = "transient"
    PERMANENT = "permanent"


class ExtractionFailure(RuntimeError):
    """A typed extraction failure suitable for later lifecycle reporting."""

    def __init__(
        self,
        *,
        kind: ExtractionFailureKind,
        code: str,
        user_message: str,
    ) -> None:
        super().__init__(user_message)
        self.kind = kind
        self.code = code
        self.user_message = user_message
