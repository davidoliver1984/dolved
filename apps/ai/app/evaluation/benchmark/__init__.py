"""Versioned engineering benchmark compilation."""

from app.evaluation.benchmark.v2 import compile_benchmark as compile_v2_benchmark
from app.evaluation.benchmark.v3 import compile_benchmark as compile_v3_benchmark

__all__ = ["compile_v2_benchmark", "compile_v3_benchmark"]
