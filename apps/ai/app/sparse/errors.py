class SparseEncodingError(RuntimeError):
    code = "sparse_encoding_error"
    retryable = False


class SparseConfigurationError(SparseEncodingError):
    code = "configuration_error"


class SparseInputTooLargeError(SparseEncodingError):
    code = "input_too_large"


class InvalidSparseInputError(SparseEncodingError):
    code = "invalid_input"


class SparseProfileMismatchError(SparseEncodingError):
    code = "profile_mismatch"


class SparseProviderUnavailableError(SparseEncodingError):
    code = "provider_unavailable"
    retryable = True


class MalformedSparseResponseError(SparseEncodingError):
    code = "malformed_response"
