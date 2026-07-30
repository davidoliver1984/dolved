# syntax=docker/dockerfile:1

ARG UV_IMAGE=ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim

FROM ${UV_IMAGE} AS development

ENV UV_COMPILE_BYTECODE=1
ENV UV_LINK_MODE=copy
ENV UV_PROJECT_ENVIRONMENT=/app/.venv
ENV TIKTOKEN_CACHE_DIR=/opt/tiktoken-cache
ENV PATH="/app/.venv/bin:${PATH}"

RUN groupadd --gid 10001 app \
    && useradd \
        --uid 10001 \
        --gid app \
        --create-home \
        --shell /usr/sbin/nologin \
        app

WORKDIR /app

COPY pyproject.toml uv.lock ./
RUN uv sync \
    --locked \
    --no-install-project

COPY --chown=app:app . .
RUN uv sync --locked \
    && mkdir --parents "${TIKTOKEN_CACHE_DIR}" \
    && uv run python -c "import tiktoken; tiktoken.get_encoding('o200k_base')" \
    && chown -R app:app /app "${TIKTOKEN_CACHE_DIR}"

USER app

EXPOSE 8001

CMD ["fastapi", "dev", "app/main.py", "--host", "0.0.0.0", "--port", "8001"]
