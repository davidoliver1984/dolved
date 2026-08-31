# RAG Platform Implementation Guide

> **Purpose:** This is the single, durable build guide for the RAG Platform.
>
> Update this file as implementation progresses. Do not create a new implementation
> document for every phase. Architectural decisions that require justification belong
> in `docs/adr/`; the overall milestone view belongs in `PROJECT_ROADMAP.md`.

## Document ownership

- `PROJECT_ROADMAP.md` explains **what** will be built and in what order.
- `IMPLEMENTATION_GUIDE.md` explains **how** to build and verify it.
- `tasks.json` is the sole source of truth for the current engineering session.
- `docs/adr/` records important architectural decisions and their trade-offs.
- Application-specific setup belongs in the relevant application README only when it
  becomes necessary for operating that application.

The former duplicate tracker at `docs/rag-platform-tasks.json` was retired on
2026-07-28 to prevent divergent session state. References to it later in this guide
or in historical journals record commands and verification that actually occurred;
they are not instructions to recreate or maintain a second tracker.

## Working conventions

Each implementation stage should contain:

1. Objective
2. Engineering rationale
3. Commands
4. Expected changes
5. Verification
6. Commit boundary

Commands are run from the repository root unless explicitly stated otherwise.

After each completed stage:

1. run the stage acceptance checks and relevant repository-wide checks;
2. update this implementation record;
3. commit the completed stage;
4. create an annotated `phase-N-sNN` Git tag at that commit; and
5. push both the commit and stage tag to the configured remote.

After the final stage in a phase, also run the phase acceptance gate and create
the annotated `phase-N` tag at the accepted phase-completion commit. The final
stage therefore receives its stage tag and, once the whole phase gate passes,
the phase tag.

The explicit per-stage convention applies from Stage 9.1 onward. Existing
historical tags remain unchanged: phases and stages completed before this rule
was clarified are not retroactively tagged where no accurate boundary exists.

---

# Phase 0 — Repository Foundation

## Objective

Create the monorepo contract before generating applications.

## Expected structure

```text
rag-platform/
├── apps/
├── contracts/
│   ├── events/
│   └── http/
├── docs/
│   ├── adr/
│   ├── journal/
│   └── IMPLEMENTATION_GUIDE.md
├── infrastructure/
│   └── terraform/
├── scripts/
├── tests/
│   └── end-to-end/
├── .editorconfig
├── .env.example
├── .gitignore
├── LICENSE
├── Makefile
├── PROJECT_ROADMAP.md
└── README.md
```

## Verification

```bash
git status
find . -maxdepth 3 -type d | sort
```

## Commit

```bash
git add .
git commit -m "Initialise monorepo structure"
```

---

# Phase 1 — Application Scaffolding

Applications live under `apps/`:

```text
apps/
├── web/    # Next.js
├── api/    # Laravel
└── ai/     # Python
```

Each application must build independently before Docker Compose is introduced.

---

## Stage 1.1 — Scaffold Next.js

### Objective

Generate the TypeScript frontend at `apps/web`.

### Why a disposable container is used

The scaffolding tool runs inside a pinned Node environment rather than relying on a
host-installed Node or npm version. The generated application is written into the
bind-mounted repository and the temporary container is removed afterwards.

### Important UID/GID note

Do **not** run `create-next-app` using only:

```bash
--user "$(id -u):$(id -g)"
```

An arbitrary numeric UID may not exist in the container's `/etc/passwd`. Node tools
that call `os.userInfo()` can then fail with:

```text
uv_os_get_passwd returned ENOENT
```

Setting only `NPM_CONFIG_CACHE` fixes npm cache permissions, but it does not create a
valid operating-system user record.

For this one-off scaffolding command, run as the container's root user and restore
ownership of the generated directory before the container exits.

### Pre-flight check

Confirm you are at the repository root:

```bash
pwd
test -d apps
test -f PROJECT_ROADMAP.md
```

If a failed attempt left a partial `apps/web` directory, inspect it first:

```bash
find apps/web -maxdepth 2 -print 2>/dev/null
```

Remove it only when it is clearly an incomplete scaffold:

```bash
rm -rf apps/web
```

### Canonical scaffolding command

```bash
docker run --rm \
  -e HOST_UID="$(id -u)" \
  -e HOST_GID="$(id -g)" \
  -v "$PWD:/workspace" \
  -w /workspace \
  node:24-alpine \
  sh -lc '
    npx create-next-app@16.2.11 apps/web \
      --typescript \
      --eslint \
      --app \
      --src-dir \
      --import-alias "@/*" \
      --use-npm \
      --no-tailwind \
      --yes

    chown -R "${HOST_UID}:${HOST_GID}" apps/web
  '
```

### Why the package version is pinned

`create-next-app@16.2.11` records the exact scaffolding tool used. Using `@latest`
would allow the generated structure and defaults to change unexpectedly in the future.

The runtime image is currently pinned to the Node 24 release line. A later repository
hardening pass may pin the image by patch version or digest once the application
Dockerfile is established.

### Expected changes

```text
apps/web/
├── public/
├── src/
│   └── app/
├── eslint.config.mjs
├── next.config.ts
├── package-lock.json
├── package.json
└── tsconfig.json
```

Exact generated files may vary slightly with the pinned Create Next App release.

### Verify ownership

```bash
ls -ld apps/web
ls -l apps/web/package.json
```

The files should be editable by the current host user.

### Verify the generated application

Run the checks in a disposable Node container:

```bash
docker run --rm \
  -v "$PWD/apps/web:/app" \
  -w /app \
  node:24-alpine \
  npm run lint
```

Build the application:

```bash
docker run --rm \
  -v "$PWD/apps/web:/app" \
  -w /app \
  node:24-alpine \
  npm run build
```

### Inspect before committing

```bash
git status
git diff --stat
cat apps/web/package.json
```

### Commit boundary

```bash
git add apps/web
git commit -m "Scaffold Next.js web application"
```

---

## Stage 1.2 — Scaffold Laravel API

Stage 1.2 — Scaffold Laravel API

Objective

Generate a Laravel 13 application at apps/api using a disposable Composer/PHP container.

The host machine does not need PHP or Composer installed.

Engineering decisions

* The application belongs at apps/api, not at the repository root.
* Laravel is pinned to major version 13.
* Composer runs inside a disposable container.
* The container runs as root while scaffolding.
* Ownership of the generated files is restored to the host user before the container exits.
* PostgreSQL and Docker Compose configuration will be introduced later.
* The freshly generated application must work independently before it is containerised.

Pre-flight checks

Run these commands from the repository root:

pwd
test -d apps
test -d apps/web
test -f PROJECT_ROADMAP.md

Confirm that apps/api does not already contain an application:

find apps/api -maxdepth 2 -print 2>/dev/null

If a previous failed attempt created an incomplete apps/api directory, inspect it carefully before removing it:

rm -rf apps/api

Do not remove it if it contains work that needs to be retained.

Canonical scaffolding command

Run this from the repository root:

docker run --rm \
  --entrypoint sh \
  -e HOST_UID="$(id -u)" \
  -e HOST_GID="$(id -g)" \
  -v "$PWD:/workspace" \
  -w /workspace \
  composer:2.10.2 \
  -lc '
    set -eu
    composer create-project \
      laravel/laravel \
      apps/api \
      "13.*" \
      --prefer-dist \
      --no-interaction
    chown -R "${HOST_UID}:${HOST_GID}" apps/api
  '

Command breakdown

docker run --rm

Runs a temporary container and removes it after completion.

--entrypoint sh

Overrides the Composer image’s normal entrypoint so that the complete shell script can run.

-e HOST_UID="$(id -u)"
-e HOST_GID="$(id -g)"

Passes the current host user’s numeric UID and GID into the container.

-v "$PWD:/workspace"

Mounts the repository root at /workspace inside the container.

-w /workspace

Makes the mounted repository the container’s working directory.

composer:2.10.2

Uses a specific Composer image version rather than a floating latest tag.

set -eu

Stops the script when a command fails or when an undefined variable is used.

composer create-project laravel/laravel apps/api "13.*"

Creates the Laravel application at apps/api and constrains Laravel to major version 13.

chown -R "${HOST_UID}:${HOST_GID}" apps/api

Returns ownership of all generated files to the host user.

Expected changes

The generated structure should include:

apps/api/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── artisan
├── composer.json
├── composer.lock
├── phpunit.xml
└── .env.example

Laravel may also create a local .env file and an SQLite database during installation.

Verify file ownership

ls -ld apps/api
ls -l apps/api/composer.json

The directory and files should be owned by the current host user rather than root.

Inspect the installed versions

docker run --rm \
  --entrypoint sh \
  -v "$PWD/apps/api:/app" \
  -w /app \
  composer:2.10.2 \
  -lc '
    php --version
    composer --version
    php artisan --version
  '

Expected Laravel output:

Laravel Framework 13.x.x

The exact patch version may differ while remaining within Laravel 13.

Inspect the application

docker run --rm \
  --entrypoint sh \
  -v "$PWD/apps/api:/app" \
  -w /app \
  composer:2.10.2 \
  -lc '
    php artisan about
    php artisan route:list
  '

This confirms that Laravel can bootstrap and that its initial routes can be loaded.

Run the test suite

docker run --rm \
  --entrypoint sh \
  -v "$PWD/apps/api:/app" \
  -w /app \
  composer:2.10.2 \
  -lc '
    php artisan test
  '

Do not commit the scaffold until the generated test suite passes.

Review generated environment files

Inspect the generated environment configuration:

grep -E '^(APP_ENV|APP_URL|DB_CONNECTION|DB_DATABASE)=' apps/api/.env

At this stage:

* Do not configure DB_HOST=postgres.
* Do not add Docker Compose service names.
* Do not configure shared root environment variables.
* Do not convert the application to PostgreSQL yet.

The goal is to prove that the generated Laravel application works independently before infrastructure concerns are introduced.

Review repository changes

git status
git diff --stat

Check that:

* files exist only under apps/api;
* no unrelated root files were changed;
* no root-owned files were created;
* Laravel 13 was installed;
* the generated tests pass.

Acceptance criteria

* apps/api/composer.json exists.
* apps/api/composer.lock exists.
* apps/api/artisan exists.
* php artisan --version reports Laravel 13.
* php artisan about succeeds.
* php artisan route:list succeeds.
* php artisan test passes.
* The application remains independent of Docker Compose.
* PostgreSQL has not yet been introduced.
* Generated files are owned by the host user.
* No unrelated repository files were changed.

Commit boundary

Only after all acceptance criteria pass:

git add apps/api
git commit -m "Scaffold Laravel API application"

⸻

## Stage 1.3 — Scaffold Python AI Service

Stage 1.3 — Scaffold Python AI Service

Objective

Create the Python AI service at apps/ai using Python 3.14, FastAPI and uv.

The host machine does not need Python, pip, uv or a virtual environment installed.

Engineering decisions

* Python is pinned to the Python 3.14 release line.
* uv manages the project, dependency resolution and lockfile.
* FastAPI provides the HTTP interface for the AI service.
* Pydantic Settings manages typed runtime configuration.
* Ruff provides linting and formatting.
* MyPy provides static type checking.
* Pytest provides automated testing.
* Application code lives under an explicit app/ package.
* Dependencies are resolved during scaffolding without retaining a Linux virtual environment on the macOS host.
* The AI service must work independently before being containerised or connected to Laravel.

Why uv

uv provides:

* Python project initialisation;
* dependency management;
* lockfile generation;
* virtual-environment management;
* reproducible command execution;
* official Docker images;
* support for Python version pinning.

It replaces the need to combine several separate tools such as pip, venv,
pip-tools and manually maintained requirements files.

Pre-flight checks

Run these commands from the repository root:

pwd
test -d apps
test -d apps/web
test -d apps/api
test -f PROJECT_ROADMAP.md

Confirm that apps/ai does not already contain an application:

find apps/ai -maxdepth 3 -print 2>/dev/null

If a failed attempt created an incomplete directory, inspect it before removing it:

rm -rf apps/ai

Do not remove it if it contains work that needs to be retained.

Canonical scaffolding command

Run this from the repository root:

docker run --rm \
  -e HOST_UID="$(id -u)" \
  -e HOST_GID="$(id -g)" \
  -e UV_CACHE_DIR=/tmp/uv-cache \
  -e UV_LINK_MODE=copy \
  -v "$PWD:/workspace" \
  -w /workspace \
  ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim \
  sh -lc '
    set -eu
    uv init \
      --app \
      --python 3.14 \
      --no-workspace \
      apps/ai
    cd apps/ai
    rm -f main.py
    mkdir -p app tests
    touch app/__init__.py
    touch tests/__init__.py
    cat > app/main.py <<'"'"'PY'"'"'
from fastapi import FastAPI
app = FastAPI(
    title="RAG Platform AI Service",
    version="0.1.0",
)
@app.get("/health", tags=["health"])
async def health() -> dict[str, str]:
    return {"status": "ok"}
PY
    cat > app/settings.py <<'"'"'PY'"'"'
from functools import lru_cache
from pydantic_settings import BaseSettings, SettingsConfigDict
class Settings(BaseSettings):
    service_name: str = "rag-platform-ai"
    environment: str = "local"
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )
@lru_cache
def get_settings() -> Settings:
    return Settings()
PY
    cat > tests/test_health.py <<'"'"'PY'"'"'
from fastapi.testclient import TestClient
from app.main import app
client = TestClient(app)
def test_health_returns_ok() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
PY
    cat > .env.example <<'"'"'ENV'"'"'
SERVICE_NAME=rag-platform-ai
ENVIRONMENT=local
ENV
    uv add \
      --no-sync \
      "fastapi[standard]" \
      pydantic-settings
    uv add \
      --dev \
      --no-sync \
      httpx \
      mypy \
      pytest \
      pytest-asyncio \
      ruff
    chown -R "${HOST_UID}:${HOST_GID}" /workspace/apps/ai
  '

Why --no-sync is used

uv add normally creates or updates a project virtual environment.

The scaffolding command runs inside Linux while the repository is bind-mounted from
macOS. Retaining that Linux .venv on the host would be incorrect because virtual
environments are platform-specific.

--no-sync updates:

* pyproject.toml;
* dependency declarations;
* uv.lock;

without retaining a project virtual environment.

Disposable verification containers will create their own Linux virtual environment.

Why --no-workspace is used

This repository is a multi-language monorepo, not currently a uv workspace.

The option prevents uv from walking up the directory tree and attempting to attach the
AI service to a parent Python workspace if a root pyproject.toml is introduced later.

Expected structure

apps/ai/
├── app/
│   ├── __init__.py
│   ├── main.py
│   └── settings.py
├── tests/
│   ├── __init__.py
│   └── test_health.py
├── .env.example
├── .gitignore
├── .python-version
├── README.md
├── pyproject.toml
└── uv.lock

No .venv directory should remain after scaffolding.

Verify generated files

find apps/ai -maxdepth 3 -type f | sort

Confirm the Python version pin:

cat apps/ai/.python-version

Expected:

3.14

Inspect the project configuration:

cat apps/ai/pyproject.toml

Confirm that it contains:

* Python 3.14 compatibility;
* FastAPI;
* Pydantic Settings;
* Ruff;
* MyPy;
* Pytest;
* Pytest Asyncio;
* HTTPX.

Verify file ownership

ls -ld apps/ai
ls -l apps/ai/pyproject.toml
ls -l apps/ai/uv.lock

The files should be owned by the current host user rather than root.

Verify Python and uv versions

docker run --rm \
  ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim \
  sh -lc '
    python --version
    uv --version
  '

Expected version families:

Python 3.14.x
uv 0.11.31

Synchronise dependencies in a disposable environment

The anonymous volume mounted at /app/.venv prevents a Linux virtual environment from
being written into the macOS repository:

docker run --rm \
  -e UV_CACHE_DIR=/tmp/uv-cache \
  -e UV_LINK_MODE=copy \
  -v "$PWD/apps/ai:/app" \
  -v /app/.venv \
  -w /app \
  ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim \
  uv sync --locked

Run Ruff linting

docker run --rm \
  -e UV_CACHE_DIR=/tmp/uv-cache \
  -e UV_LINK_MODE=copy \
  -e UV_LINK_MODE=copy \
  -v "$PWD/apps/ai:/app" \
  -v /app/.venv \
  -w /app \
  ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim \
  sh -lc '
    uv sync --locked
    uv run ruff check .
  '

Verify Ruff formatting

docker run --rm \
  -e UV_CACHE_DIR=/tmp/uv-cache \
  -e UV_LINK_MODE=copy \
  -v "$PWD/apps/ai:/app" \
  -v /app/.venv \
  -w /app \
  ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim \
  sh -lc '
    uv sync --locked
    uv run ruff format --check .
  '

Run MyPy

docker run --rm \
  -e UV_CACHE_DIR=/tmp/uv-cache \
  -e UV_LINK_MODE=copy \
  -v "$PWD/apps/ai:/app" \
  -v /app/.venv \
  -w /app \
  ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim \
  sh -lc '
    uv sync --locked
    uv run mypy app tests
  '

Run the test suite

docker run --rm \
  -e UV_CACHE_DIR=/tmp/uv-cache \
  -e UV_LINK_MODE=copy \
  -v "$PWD/apps/ai:/app" \
  -v /app/.venv \
  -w /app \
  ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim \
  sh -lc '
    uv sync --locked
    uv run pytest
  '

Expected result:

1 passed

Start the service independently

docker run --rm \
  -p 8001:8001 \
  -e UV_CACHE_DIR=/tmp/uv-cache \
  -e UV_LINK_MODE=copy \
  -v "$PWD/apps/ai:/app" \
  -v /app/.venv \
  -w /app \
  ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim \
  sh -lc '
    uv sync --locked
    uv run fastapi dev app/main.py \
      --host 0.0.0.0 \
      --port 8001
  '

In a second terminal, verify the health endpoint:

curl --fail http://localhost:8001/health

Expected response:

{"status":"ok"}

Stop the development server with Ctrl+C.

Review repository changes

git status
git diff --stat
git diff -- apps/ai/pyproject.toml

Check that:

* files were created only under apps/ai;
* no .venv directory is tracked;
* no unrelated root files changed;
* the lockfile exists;
* linting passes;
* formatting verification passes;
* MyPy passes;
* tests pass;
* the health endpoint responds successfully.

Acceptance criteria

* apps/ai/pyproject.toml exists.
* apps/ai/uv.lock exists.
* apps/ai/.python-version specifies Python 3.14.
* apps/ai/app/main.py exists.
* apps/ai/app/settings.py exists.
* apps/ai/tests/test_health.py exists.
* uv sync --locked succeeds.
* Ruff linting passes.
* Ruff formatting verification passes.
* MyPy passes.
* Pytest passes.
* The /health endpoint returns HTTP 200.
* No project .venv remains in the repository.
* Generated files are owned by the host user.
* The service remains independent of Docker Compose.
* No Laravel, PostgreSQL, queue or vector-database integration has been added yet.
* No unrelated repository files were changed.

Commit boundary

Only after all acceptance criteria pass:

git add apps/ai
git commit -m "Scaffold Python AI service"

⸻
Phase 2 — Independent Containerisation

Phase objective

Create an independently buildable development image for each application before connecting them through Docker Compose.

Each image must:

* use an intentional base-image version;
* install dependencies reproducibly from committed lockfiles;
* run as a non-root runtime user where practical;
* expose the application on a documented internal port;
* support local development;
* build independently from the repository root;
* avoid dependencies on host-installed language runtimes.

⸻

## Stage 2.1 — Containerise Next.js Web Application

### Objective

Create independently buildable development and production images for the Next.js
application at `apps/web`.

The host machine does not need Node.js or npm installed.

### Status

Completed and verified.

### Engineering decisions

* Use the Node 24 Alpine release line.
* Use one multi-stage Dockerfile with separate dependency, development, build and
  production stages.
* Install dependencies with `npm ci` from the committed `package-lock.json`.
* Run the development server on port 3000 and bind it to `0.0.0.0`.
* Use the built-in `node` user for both development and production processes.
* Enable Next.js standalone output for a smaller production runtime image.
* Copy only the public assets, traced standalone server and static build assets into
  the production image.
* Keep Docker Compose hostnames and service dependencies out of the image.
* Keep development and production behaviour selectable through Docker build targets.

### Why a multi-stage Dockerfile is used

The development target contains the complete dependency tree and runs `next dev`.
This supports local development and will later support source bind mounts through
Docker Compose.

The builder target creates the optimized Next.js build. The production target starts
again from the Node base image and receives only the files needed by the standalone
server. Build tooling, source files and development dependencies are therefore not
copied into the final runtime image.

This provides a usable development image now without requiring the production image
to be redesigned later.

### Pre-flight checks

Run from the repository root:

```bash
pwd
test -f apps/web/package.json
test -f apps/web/package-lock.json
test -f apps/web/next.config.ts
docker version
```

Confirm that the application still passes its scaffold checks before containerisation:

```bash
docker run --rm \
  -v "$PWD/apps/web:/app" \
  -w /app \
  node:24-alpine \
  npm run lint
```

### Files created and changed

```text
apps/web/
├── .dockerignore       # Excludes host and generated files from the build context
├── Dockerfile          # Development, build and production image targets
└── next.config.ts      # Enables Next.js standalone output
```

### Dockerfile implementation

Create `apps/web/Dockerfile`:

```dockerfile
# syntax=docker/dockerfile:1

ARG NODE_VERSION=24-alpine

FROM node:${NODE_VERSION} AS dependencies
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci \
    && chown -R node:node /app

FROM dependencies AS development
ENV NODE_ENV=development
COPY --chown=node:node . .
RUN mkdir -p .next \
    && chown -R node:node .next
USER node
EXPOSE 3000
CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0"]

FROM dependencies AS builder
COPY . .
RUN npm run build

FROM node:${NODE_VERSION} AS production
WORKDIR /app
ENV NODE_ENV=production
ENV HOSTNAME=0.0.0.0
ENV PORT=3000

COPY --from=builder --chown=node:node /app/public ./public
COPY --from=builder --chown=node:node /app/.next/standalone ./
COPY --from=builder --chown=node:node /app/.next/static ./.next/static

USER node
EXPOSE 3000
CMD ["node", "server.js"]
```

### Docker ignore implementation

Create `apps/web/.dockerignore`:

```text
.git
.gitignore
.next
node_modules
npm-debug.log*
README.md
```

Excluding `node_modules` prevents host or previously generated dependencies from
entering the Linux image. Excluding `.next` ensures every image build creates its own
platform-correct output.

### Next.js configuration

Update `apps/web/next.config.ts`:

```typescript
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
};

export default nextConfig;
```

`output: "standalone"` instructs Next.js to trace the production server's runtime
dependencies into `.next/standalone`.

### Build the development image

Run from the repository root:

```bash
docker build \
  --target development \
  --tag rag-platform-web:development \
  apps/web
```

This executes `npm ci` inside the image and produces the development target without
requiring a host installation of Node.js.

### Run and verify the development image

Start the container:

```bash
docker run \
  --detach \
  --name rag-platform-web-development \
  --publish 127.0.0.1:3000:3000 \
  rag-platform-web:development
```

Verify the HTTP response and runtime user:

```bash
curl --fail --silent --show-error \
  --output /dev/null \
  --write-out 'HTTP %{http_code}\n' \
  http://127.0.0.1:3000/

docker inspect \
  --format 'user={{.Config.User}} running={{.State.Running}}' \
  rag-platform-web-development
```

Expected output:

```text
HTTP 200
user=node running=true
```

Stop and remove the verification container:

```bash
docker stop rag-platform-web-development
docker rm rag-platform-web-development
```

### Permission issue found during verification

The first development container exited before it could serve HTTP. `WORKDIR /app`
created the application directory as root, while the development server ran as the
non-root `node` user. Next.js could not create its `.next` development cache.

The shared dependency-stage command was changed from:

```dockerfile
RUN npm ci
```

to:

```dockerfile
RUN npm ci \
    && chown -R node:node /app
```

After rebuilding, the development container returned HTTP 200 and continued to run as
the `node` user.

### Run linting inside the development image

```bash
docker run --rm \
  rag-platform-web:development \
  npm run lint
```

Result: ESLint completed successfully with exit code 0.

### Build the production image

```bash
docker build \
  --target production \
  --tag rag-platform-web:production \
  apps/web
```

The builder stage ran `npm run build`. Next.js compiled the application, completed its
TypeScript checks and generated the static routes successfully.

### Run and verify the production image

```bash
docker run \
  --detach \
  --rm \
  --name rag-platform-web-production \
  --publish 127.0.0.1:3000:3000 \
  rag-platform-web:production
```

Verify the response and runtime user:

```bash
curl --fail --silent --show-error \
  --output /dev/null \
  --write-out 'HTTP %{http_code}\n' \
  http://127.0.0.1:3000/

docker inspect \
  --format 'user={{.Config.User}} running={{.State.Running}}' \
  rag-platform-web-production
```

Expected output:

```text
HTTP 200
user=node running=true
```

Stop the container:

```bash
docker stop rag-platform-web-production
```

Because it was started with `--rm`, Docker removes it after it stops.

### Dependency security check

Check production dependencies:

```bash
docker run --rm \
  rag-platform-web:development \
  npm audit --omit=dev --audit-level=high
```

At implementation time, npm reported three high-severity findings inherited through
Next.js:

* PostCSS vulnerabilities affecting the version bundled by Next.js.
* Sharp vulnerabilities inherited from its bundled libvips version.

npm proposed `npm audit fix --force`, but the proposed resolution would downgrade
Next.js from version 16 to version 9. That is a breaking and invalid resolution, so it
was not applied. Reassess these transitive dependencies when a compatible stable
Next.js release includes patched versions.

### Review repository changes

```bash
git status
git diff --check
git diff --stat
git diff -- apps/web/Dockerfile
git diff -- apps/web/.dockerignore
git diff -- apps/web/next.config.ts
```

Check that:

* containerisation changes are limited to the web application and this guide;
* no `.next` or `node_modules` directory is tracked;
* no generated runtime files became root-owned on the host;
* both image targets build;
* both containers respond successfully;
* both runtime processes use the non-root `node` user;
* linting and the optimized Next.js build pass.

### Acceptance criteria

* `apps/web/Dockerfile` exists.
* `apps/web/.dockerignore` exists.
* `apps/web/next.config.ts` enables standalone output.
* Dependencies are installed using `npm ci`.
* Development and production targets build independently.
* The development container responds on port 3000.
* The standalone production container responds on port 3000.
* Both runtime targets use the non-root `node` user.
* ESLint passes inside the development image.
* The Next.js TypeScript and production build checks pass.
* No Docker Compose service names or dependencies have been introduced.
* Known dependency audit findings are recorded rather than changed with an unsafe
  forced downgrade.

### Commit boundary

Only after all acceptance criteria pass:

```bash
git add \
  apps/web/Dockerfile \
  apps/web/.dockerignore \
  apps/web/next.config.ts \
  IMPLEMENTATION_GUIDE.md

git commit -m "Containerise Next.js web application"
```

⸻

## Stage 2.2 — Containerise Laravel API Application

### Objective

Create an independently buildable Laravel 13 development image at `apps/api`.

The image must install its locked Composer dependencies, run without host PHP or
Composer, serve HTTP on port 8000 and remain independent of Docker Compose and
PostgreSQL.

### Status

Completed and verified.

### Engineering decisions

* Use the PHP 8.4 CLI Alpine release line.
* Use Composer 2.10.2 copied from its official image.
* Install dependencies from `composer.lock`.
* Use `php artisan serve` for the development container.
* Defer PHP-FPM and its required web-server pairing to production hardening.
* Install the XML, internationalisation, multibyte string, process-control and SQLite
  extensions used by Laravel and the current test suite.
* Remove Alpine build dependencies after compiling PHP extensions.
* Run Laravel as the built-in non-root `www-data` user.
* Preserve write access to `storage/` and `bootstrap/cache/`.
* Exclude the host `.env` and create only an empty, non-secret placeholder in the
  image.
* Generate an ephemeral application key at container startup unless `APP_KEY` is
  supplied externally.
* Use in-memory SQLite, array-backed cache and sessions, synchronous queues and stderr
  logging until Compose introduces shared infrastructure.

### Why PHP 8.4 is used

Laravel 13 requires PHP 8.3 or later, but the committed `composer.lock` also contains
development and testing packages requiring PHP 8.4.1 or later. PHP 8.4 therefore
satisfies the complete locked dependency graph rather than only the framework's
minimum runtime requirement.

### Why `artisan serve` is used

This phase proves that each application can build and run independently for local
development. PHP-FPM does not serve HTTP by itself and would require an additional
Nginx, Apache or equivalent web-server container.

Introducing that production topology now would add operational configuration before
the API contains application functionality. The production server architecture will
be selected during production container hardening.

### Files created

```text
apps/api/
├── .dockerignore
├── Dockerfile
└── docker-entrypoint.sh
```

### Dockerfile implementation

Create `apps/api/Dockerfile`:

```dockerfile
# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4-cli-alpine

FROM composer:2.10.2 AS composer

FROM php:${PHP_VERSION} AS development

RUN apk add --no-cache \
        icu-libs \
        libxml2 \
        oniguruma \
        sqlite-libs \
    && apk add --no-cache --virtual .build-dependencies \
        icu-dev \
        libxml2-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" \
        dom \
        intl \
        mbstring \
        pcntl \
        pdo_sqlite \
        xml \
        xmlwriter \
    && apk del .build-dependencies

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY --chown=www-data:www-data . .
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN composer dump-autoload --optimize \
    && touch .env \
    && chown -R www-data:www-data \
        .env \
        storage \
        bootstrap/cache \
        vendor

ENV APP_ENV=local
ENV APP_DEBUG=true
ENV LOG_CHANNEL=stderr
ENV DB_CONNECTION=sqlite
ENV DB_DATABASE=:memory:
ENV SESSION_DRIVER=array
ENV CACHE_STORE=array
ENV QUEUE_CONNECTION=sync

USER www-data

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

Copying `composer.json` and `composer.lock` before the application source allows
Docker to reuse the dependency layer when only application code changes.

Composer scripts are deferred until after the application source exists because
Laravel's package-discovery script needs `artisan` and the application bootstrap
files.

### Entrypoint implementation

Create `apps/api/docker-entrypoint.sh`:

```sh
#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    export APP_KEY
fi

exec "$@"
```

Laravel requires an application encryption key even when database-backed services are
disabled. The entrypoint creates a cryptographically random, process-local key for
independent development and test runs. An externally supplied `APP_KEY` takes
precedence, allowing Compose and deployed environments to provide a persistent secret.

The key is not written to an image layer or committed file.

### Docker ignore implementation

Create `apps/api/.dockerignore`:

```text
.env
.git
.gitattributes
.gitignore
.phpunit.result.cache
Dockerfile
node_modules
npm-debug.log*
storage/logs/*
vendor
```

The host `.env` is excluded because it can contain secrets and machine-specific
configuration. Host `vendor` dependencies are excluded so Composer always installs a
Linux-compatible dependency tree from the committed lockfile.

### Build the image

Run from the repository root:

```bash
docker build \
  --target development \
  --tag rag-platform-api:development \
  apps/api
```

The verified build installed Laravel 13.21.1 with PHP 8.4.23 and Composer 2.10.2.
Exact patch versions may advance while remaining inside their selected release lines.

### Verify platform requirements

```bash
docker run --rm \
  rag-platform-api:development \
  composer check-platform-reqs
```

Result: the locked PHP version and required extensions were satisfied.

### Inspect Laravel inside the image

```bash
docker run --rm \
  rag-platform-api:development \
  php artisan about

docker run --rm \
  rag-platform-api:development \
  php artisan route:list
```

`php artisan about` confirmed Laravel 13, PHP 8.4, local environment configuration,
SQLite, array-backed cache and sessions, synchronous queues and stderr logging.

`php artisan route:list` loaded all scaffold routes successfully.

### Run the tests

```bash
docker run --rm \
  rag-platform-api:development \
  php artisan test --display-warnings
```

Verified result:

```text
Tests: 2 passed (2 assertions)
```

### Environment issues found during verification

The first test run failed because `.env` was correctly excluded and Laravel had no
application encryption key.

Storing even a development key with a Dockerfile `ENV` instruction was rejected
because Docker's build checks correctly treat `APP_KEY` as secret material.

The first entrypoint revision used `php artisan key:generate --show`. It created a
valid ephemeral key, but Laravel's Dotenv reader still probed the absent `.env` file.
PHPUnit 12 surfaced that suppressed file read as a test warning.

The final implementation:

* generates the ephemeral key directly with PHP `random_bytes(32)`;
* creates an empty `.env` placeholder inside the image;
* never copies the host `.env`;
* allows an externally supplied `APP_KEY` to override the generated key.

The final test run completed with no failures or warnings.

### Run and verify the container

Start Laravel independently:

```bash
docker run \
  --detach \
  --name rag-platform-api-development \
  --publish 127.0.0.1:8000:8000 \
  rag-platform-api:development
```

Verify the application and framework health route:

```bash
curl --fail --silent --show-error \
  --output /dev/null \
  --write-out 'HTTP %{http_code}\n' \
  http://127.0.0.1:8000/

curl --fail --silent --show-error \
  --output /dev/null \
  --write-out 'HTTP %{http_code}\n' \
  http://127.0.0.1:8000/up
```

Expected output:

```text
HTTP 200
HTTP 200
```

Verify the runtime user and writable Laravel directories:

```bash
docker inspect \
  --format 'user={{.Config.User}} running={{.State.Running}}' \
  rag-platform-api-development

docker exec rag-platform-api-development \
  sh -lc 'test -w storage && test -w bootstrap/cache && echo runtime-directories-writable'
```

Expected output:

```text
user=www-data running=true
runtime-directories-writable
```

Stop and remove the verification container:

```bash
docker stop rag-platform-api-development
docker rm rag-platform-api-development
```

### Dependency security check

```bash
docker run --rm \
  rag-platform-api:development \
  composer audit --locked
```

Verified result:

```text
No security vulnerability advisories found.
```

### Review repository changes

```bash
git status
git diff --check
git diff --stat
sed -n '1,240p' apps/api/Dockerfile
sed -n '1,160p' apps/api/docker-entrypoint.sh
sed -n '1,160p' apps/api/.dockerignore
```

Check that:

* changes are limited to the API container files and this guide;
* the host `.env` and `vendor` directory are not added to the image context;
* dependencies come from `composer.lock`;
* the image contains no committed application key;
* the container runs as `www-data`;
* Laravel's runtime directories are writable;
* tests, HTTP checks and the Composer audit pass;
* no PostgreSQL or Docker Compose coupling has been introduced.

### Acceptance criteria

* `apps/api/Dockerfile` exists.
* `apps/api/.dockerignore` exists.
* `apps/api/docker-entrypoint.sh` exists.
* PHP 8.4 satisfies Laravel 13 and the complete locked dependency graph.
* Composer 2.10.2 installs dependencies from `composer.lock`.
* Required PHP extensions are installed and build dependencies are removed.
* The image builds independently.
* Composer platform requirement checks pass.
* `php artisan about` and `php artisan route:list` succeed.
* Both scaffold tests pass without warnings.
* Composer reports no known dependency security advisories.
* The application and `/up` route return HTTP 200 on port 8000.
* The runtime process uses the non-root `www-data` user.
* `storage/` and `bootstrap/cache/` are writable.
* The host `.env` is excluded and no application key is stored in an image layer.
* No PostgreSQL or Compose coupling has been introduced.

### Commit boundary

Only after all acceptance criteria pass:

```bash
git add \
  apps/api/Dockerfile \
  apps/api/.dockerignore \
  apps/api/docker-entrypoint.sh \
  IMPLEMENTATION_GUIDE.md

git commit -m "Containerise Laravel API application"
```

⸻

## Stage 2.3 — Containerise Python AI Service

### Objective

Create an independently buildable development image for the FastAPI service at
`apps/ai`.

The image must use the locked Python environment, run without host Python or uv,
provide the existing health endpoint on port 8001 and remain independent of Laravel,
queues, model providers and vector storage.

### Status

Completed and verified.

### Engineering decisions

* Use the pinned `ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim` image.
* Install dependencies from `uv.lock` with `uv sync --locked`.
* Store the Linux virtual environment only inside the image at `/app/.venv`.
* Compile Python bytecode during dependency installation.
* Use Docker layer caching by installing locked dependencies before copying source.
* Create a dedicated non-root `app` user and group with UID/GID 10001.
* Give the non-root user ownership of the complete application directory so Ruff,
  MyPy, Pytest and FastAPI can create development caches.
* Run FastAPI's development server on container port 8001.
* Keep production worker count, timeouts and process management deferred until
  production hardening.
* Add `httpx2` as a locked development dependency to match the current FastAPI and
  Starlette test-client transport.

### Why the uv image is used

The selected image contains both Python 3.14 and the pinned uv version used during
scaffolding. `uv sync --locked` treats `uv.lock` as the reproducibility boundary and
fails rather than silently resolving a different dependency graph.

The virtual environment is created inside the Linux image. No Linux `.venv` is
retained on the macOS host.

### Files created and changed

```text
apps/ai/
├── .dockerignore
├── Dockerfile
├── pyproject.toml      # Adds the current FastAPI test transport
└── uv.lock             # Records the resolved httpx2 dependency graph
```

### Dockerfile implementation

Create `apps/ai/Dockerfile`:

```dockerfile
# syntax=docker/dockerfile:1

ARG UV_IMAGE=ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim

FROM ${UV_IMAGE} AS development

ENV UV_COMPILE_BYTECODE=1
ENV UV_LINK_MODE=copy
ENV UV_PROJECT_ENVIRONMENT=/app/.venv
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
    && chown -R app:app /app

USER app

EXPOSE 8001

CMD ["fastapi", "dev", "app/main.py", "--host", "0.0.0.0", "--port", "8001"]
```

The first `uv sync` installs the locked dependency graph before source code is copied,
allowing Docker to cache the expensive dependency layer. The second sync installs the
local project after its source and metadata are present.

### Docker ignore implementation

Create `apps/ai/.dockerignore`:

```text
.env
.git
.gitignore
.mypy_cache
.pytest_cache
.ruff_cache
.venv
__pycache__
*.py[cod]
README.md
```

This keeps secrets, host virtual environments, caches and compiled host bytecode out
of the Linux build context.

### Test-client dependency update

The initial Pytest run passed but emitted a Starlette deprecation warning stating that
the test client should use `httpx2`.

Update the locked development dependencies from `apps/ai`:

```bash
uv add --dev httpx2
```

This added `httpx2`, `httpcore2` and their required trust-store package to `uv.lock`.
The original `httpx` package remains because FastAPI's standard dependency set and
FastAPI CLI still require it.

Running uv directly on the host temporarily created `apps/ai/.venv`. That platform-
specific environment was moved out of the repository immediately. The project
continues to create and use its development virtual environment only inside Docker.

### Build the image

Run from the repository root:

```bash
docker build \
  --target development \
  --tag rag-platform-ai:development \
  apps/ai
```

The verified build used Python 3.14.6 and resolved 59 locked packages. Exact Python
patch versions may advance while remaining inside the selected release line and image
tag.

### Ownership issue found during verification

The first quality-check run failed because `WORKDIR /app` created `/app` as root.
Although source files and `.venv` had been assigned to the `app` user, Ruff, MyPy and
Pytest could not create cache directories directly under `/app`.

The final build command assigns the complete application directory:

```dockerfile
RUN uv sync --locked \
    && chown -R app:app /app
```

After rebuilding, all quality tools ran successfully as the non-root user.

### Run quality checks inside the image

```bash
docker run --rm \
  rag-platform-ai:development \
  ruff check .

docker run --rm \
  rag-platform-ai:development \
  ruff format --check .

docker run --rm \
  rag-platform-ai:development \
  mypy app tests

docker run --rm \
  rag-platform-ai:development \
  pytest

docker run --rm \
  rag-platform-ai:development \
  uv lock --check

docker run --rm \
  rag-platform-ai:development \
  uv pip check
```

Verified results:

```text
Ruff: all checks passed
Ruff format: 5 files already formatted
MyPy: no issues found in 5 source files
Pytest: 1 passed with no warnings
uv lock: resolved locked dependencies without changes
uv pip check: all 57 installed packages are compatible
```

### Run and verify the service

The canonical independent run command is:

```bash
docker run \
  --detach \
  --name rag-platform-ai-development \
  --publish 127.0.0.1:8001:8001 \
  rag-platform-ai:development
```

During implementation, host port 8001 was already occupied by another local process.
That process was left untouched. The verification container was instead started with
temporary host port 18001 while retaining its required internal port:

```bash
docker run \
  --detach \
  --name rag-platform-ai-development \
  --publish 127.0.0.1:18001:8001 \
  rag-platform-ai:development
```

Verify the health response:

```bash
curl --retry 10 \
  --retry-delay 1 \
  --retry-connrefused \
  --fail \
  --silent \
  --show-error \
  http://127.0.0.1:18001/health
```

Expected response:

```json
{"status":"ok"}
```

Verify the runtime user:

```bash
docker inspect \
  --format 'user={{.Config.User}} running={{.State.Running}}' \
  rag-platform-ai-development
```

Expected output:

```text
user=app running=true
```

Stop and remove the verification container:

```bash
docker stop rag-platform-ai-development
docker rm rag-platform-ai-development
```

### Review repository changes

```bash
git status
git diff --check
git diff --stat
sed -n '1,200p' apps/ai/Dockerfile
sed -n '1,120p' apps/ai/.dockerignore
git diff -- apps/ai/pyproject.toml
git diff -- apps/ai/uv.lock
```

Check that:

* changes are limited to AI container and dependency files plus this guide;
* no host `.venv`, cache or bytecode directory is tracked;
* the image resolves dependencies only from `uv.lock`;
* all quality, typing, test and dependency-consistency checks pass;
* the service responds successfully on internal port 8001;
* the runtime process uses the dedicated non-root `app` user;
* no Laravel, model-provider, queue or vector-store integration has been introduced.

### Acceptance criteria

* `apps/ai/Dockerfile` exists.
* `apps/ai/.dockerignore` exists.
* Python 3.14 and uv 0.11.31 are provided by the pinned base image.
* Dependencies are installed with `uv sync --locked`.
* The Linux virtual environment remains inside the image.
* The image builds independently.
* Ruff linting and formatting checks pass.
* MyPy passes.
* Pytest passes without warnings.
* The lockfile and installed dependency graph are consistent.
* `/health` returns `{"status":"ok"}` from container port 8001.
* The process runs as the dedicated non-root `app` user.
* No Laravel, model-provider, queue or vector-store integration has been introduced.

### Commit boundary

Only after all acceptance criteria pass:

```bash
git add \
  apps/ai/Dockerfile \
  apps/ai/.dockerignore \
  apps/ai/pyproject.toml \
  apps/ai/uv.lock \
  IMPLEMENTATION_GUIDE.md

git commit -m "Containerise Python AI service"
```

⸻

Phase 3 — Docker Compose Platform

Phase objective

Connect the independently working application containers through the root compose.yaml.

⸻

## Stage 3.1 — Compose Application Services

### Objective

Create the initial root `compose.yaml` and run the independently verified web, API and
AI development images as one platform.

### Status

Completed and verified.

### Engineering decisions

* Use the service names `web`, `api` and `ai` as Compose DNS names.
* Build each service from its existing `development` target.
* Publish host ports through variables with local defaults.
* Use source bind mounts for live development.
* Use named volumes for Linux-specific dependencies and generated build state.
* Start dependent services only after their upstream health checks pass.
* Use runtime-native health checks so no extra curl package is added to an image.
* Enable Docker's lightweight init process for signal forwarding and child cleanup.
* Keep database services out until Stage 3.2.

### Networking contract

Inside the Compose network:

```text
web -> http://api:8000
api -> http://ai:8001
```

From the host:

```text
Web: http://localhost:3000
API: http://localhost:8000
AI:  http://localhost:8001
```

Browser-facing URLs use `localhost` because a browser runs outside Docker. Server-side
container communication uses Compose service names.

### Why dependency volumes are used

Source bind mounts provide immediate access to code changes, but they also replace the
application directory copied into the image. Separate named volumes preserve the
Linux dependencies generated during image builds:

```text
web_node_modules -> /app/node_modules
web_next         -> /app/.next
api_vendor       -> /app/vendor
ai_venv          -> /app/.venv
```

This prevents macOS dependencies from replacing Linux dependencies inside containers.

### Environment contract update

Add the AI port and browser-facing API URL to the root `.env.example`:

```dotenv
AI_PORT=8001
NEXT_PUBLIC_API_URL=http://localhost:8000
```

### Compose implementation

Create `compose.yaml` at the repository root:

```yaml
name: ${COMPOSE_PROJECT_NAME:-rag-platform}

services:
  web:
    build:
      context: ./apps/web
      target: development
    image: rag-platform-web:development
    init: true
    ports:
      - "${WEB_PORT:-3000}:3000"
    environment:
      API_INTERNAL_URL: http://api:8000
      NEXT_PUBLIC_API_URL: ${NEXT_PUBLIC_API_URL:-http://localhost:8000}
    volumes:
      - ./apps/web:/app
      - web_node_modules:/app/node_modules
      - web_next:/app/.next
    depends_on:
      api:
        condition: service_healthy
    healthcheck:
      test:
        - CMD
        - node
        - -e
        - "fetch('http://127.0.0.1:3000').then(response => process.exit(response.ok ? 0 : 1)).catch(() => process.exit(1))"
      interval: 5s
      timeout: 3s
      retries: 10
      start_period: 15s

  api:
    build:
      context: ./apps/api
      target: development
    image: rag-platform-api:development
    init: true
    ports:
      - "${API_PORT:-8000}:8000"
    environment:
      AI_SERVICE_URL: http://ai:8001
      FRONTEND_URL: ${FRONTEND_URL:-http://localhost:3000}
    volumes:
      - ./apps/api:/app
      - api_vendor:/app/vendor
    depends_on:
      ai:
        condition: service_healthy
    healthcheck:
      test:
        - CMD
        - php
        - -r
        - "exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);"
      interval: 5s
      timeout: 3s
      retries: 10
      start_period: 10s

  ai:
    build:
      context: ./apps/ai
      target: development
    image: rag-platform-ai:development
    init: true
    ports:
      - "${AI_PORT:-8001}:8001"
    environment:
      ENVIRONMENT: local
      SERVICE_NAME: rag-platform-ai
    volumes:
      - ./apps/ai:/app
      - ai_venv:/app/.venv
    healthcheck:
      test:
        - CMD
        - python
        - -c
        - "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8001/health', timeout=2)"
      interval: 5s
      timeout: 3s
      retries: 10
      start_period: 10s

volumes:
  web_node_modules:
  web_next:
  api_vendor:
  ai_venv:
```

### Resolve the existing port conflict

Host port 8001 was initially occupied by an earlier disposable RAG FastAPI container.
Inspection confirmed that it bind-mounted this repository's `apps/ai` directory and
used the scaffolding development command.

Stop that project-local container without affecting unrelated containers:

```bash
docker stop pensive_ellis
```

The container had `AutoRemove` enabled, so Docker removed it after stopping.

### Validate the Compose model

```bash
docker compose config --quiet
docker compose config --services
docker compose config --volumes
```

Verified services:

```text
ai
api
web
```

Verified volumes:

```text
api_vendor
ai_venv
web_node_modules
web_next
```

### Build and start the platform

```bash
docker compose build
docker compose up --detach --wait --wait-timeout 120
```

All three images built successfully through Compose.

### Next.js cache-volume issue found during verification

AI and API became healthy, but the first web container exited with:

```text
EACCES: permission denied, mkdir '/app/.next/dev'
```

The new `web_next` volume was mounted as root because `.next` did not exist in the
development image. The non-root `node` process therefore could not create its cache.

Create the mount point with the correct ownership in the web development target:

```dockerfile
RUN mkdir -p .next \
    && chown -R node:node .next
```

Recreate only the failed container and its newly generated empty cache volume:

```bash
docker compose rm --force web
docker volume rm rag-platform_web_next
docker compose build web
docker compose up --detach --wait --wait-timeout 120
```

The recreated web container became healthy.

### Verify platform health

```bash
docker compose ps

curl --fail --silent --show-error \
  --output /dev/null \
  --write-out 'web HTTP %{http_code}\n' \
  http://127.0.0.1:3000/

curl --fail --silent --show-error \
  --output /dev/null \
  --write-out 'api HTTP %{http_code}\n' \
  http://127.0.0.1:8000/up

curl --fail --silent --show-error \
  http://127.0.0.1:8001/health
```

Verified results:

```text
web HTTP 200
api HTTP 200
{"status":"ok"}
```

### Verify Compose DNS

Verify web-to-API communication:

```bash
docker compose exec --no-TTY web \
  node -e "fetch('http://api:8000/up').then(r=>{console.log('web-to-api',r.status);process.exit(r.ok?0:1)}).catch(e=>{console.error(e);process.exit(1)})"
```

Verify API-to-AI communication:

```bash
docker compose exec --no-TTY api \
  php -r '$body=file_get_contents("http://ai:8001/health"); echo "api-to-ai ".$body.PHP_EOL;'
```

Verified results:

```text
web-to-api 200
api-to-ai {"status":"ok"}
```

### Verify logs

```bash
docker compose logs --no-color --tail 12
```

Logs were readable and labelled with `web-1`, `api-1` and `ai-1`.

### Acceptance criteria

* Root `compose.yaml` exists and validates.
* All three images build through Compose.
* All three services start and become healthy.
* Source bind mounts support development.
* Linux dependency and cache paths use named volumes.
* Web, API and AI host endpoints respond successfully.
* Web reaches API using `api:8000`.
* API reaches AI using `ai:8001`.
* Health checks use tools already present in each runtime.
* Runtime logs remain attributable by service.
* No database or external infrastructure is required.
* No host-installed language runtime is required.

### Commit boundary

Only after all acceptance criteria pass:

```bash
git add \
  compose.yaml \
  .env.example \
  apps/web/Dockerfile \
  IMPLEMENTATION_GUIDE.md

git commit -m "Add application services to Docker Compose"
```

⸻

## Stage 3.2 — Add PostgreSQL

### Objective

Add a persistent PostgreSQL development service before connecting Laravel.

### Status

Completed and verified.

### Architectural decision

PostgreSQL 18 was selected explicitly and recorded before implementation:

```text
docs/adr/0001-use-postgresql-18.md
```

ADR 0001 records the PostgreSQL 17 alternative, the decision to pin patch version
18.4, the support horizon and the PostgreSQL 18 Docker volume-path change.

### Engineering decisions

* Pin the image to `postgres:18.4-alpine`.
* Use the Compose service name `postgres` and internal port 5432.
* Mount the named volume at `/var/lib/postgresql`, as required by the PostgreSQL 18
  Docker Official Image.
* Publish host access only on `127.0.0.1`.
* Keep database name, username and password in environment variables.
* Use `pg_isready` for the health check.
* Preserve data when containers and the Compose network are recreated.
* Do not install pgvector; vector storage remains a future ADR.

### Compose service

Add to `compose.yaml`:

```yaml
  postgres:
    image: postgres:18.4-alpine
    ports:
      - "127.0.0.1:${POSTGRES_PORT:-5432}:5432"
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-rag_platform}
      POSTGRES_USER: ${POSTGRES_USER:-rag_platform}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-local-development-only}
    volumes:
      - postgres_data:/var/lib/postgresql
    healthcheck:
      test:
        - CMD-SHELL
        - "pg_isready --username=$$POSTGRES_USER --dbname=$$POSTGRES_DB"
      interval: 5s
      timeout: 3s
      retries: 10
      start_period: 10s
    stop_grace_period: 30s
```

Add the named volume:

```yaml
volumes:
  postgres_data:
```

The doubled dollar signs defer variable expansion to the container's health-check
shell rather than allowing Compose to interpolate them.

### Resolve the host PostgreSQL port conflict

The first startup attempt failed because host port 5432 was already in use.

Inspect the listener:

```bash
lsof -nP -iTCP:5432 -sTCP:LISTEN
```

The listener was an existing host PostgreSQL process owned by the current user. It was
left untouched.

Change the local and example host mapping:

```dotenv
POSTGRES_PORT=5433
```

The final addressing contract is:

```text
Containers: postgres:5432
Host:       127.0.0.1:5433
```

### Start PostgreSQL

```bash
docker compose config --quiet
docker compose pull postgres
docker compose up --detach --wait --wait-timeout 120 postgres
```

PostgreSQL became healthy.

### Verify version and storage layout

```bash
docker compose exec --no-TTY postgres sh -lc \
  'psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
    --set=ON_ERROR_STOP=1 \
    --command="SELECT current_setting('\''server_version'\'') AS version, current_database() AS database;" \
    --command="SHOW data_directory;"'
```

Verified result:

```text
version:        18.4
database:       rag_platform
data_directory: /var/lib/postgresql/18/docker
```

### Verify persistence

Create a temporary persistence marker:

```bash
docker compose exec --no-TTY postgres sh -lc \
  'psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
    --set=ON_ERROR_STOP=1 \
    --command="CREATE TABLE compose_persistence_check (value text NOT NULL);" \
    --command="INSERT INTO compose_persistence_check VALUES ('\''survives-recreation'\'');"'
```

Recreate only the PostgreSQL container:

```bash
docker compose up \
  --detach \
  --force-recreate \
  --wait \
  --wait-timeout 120 \
  postgres
```

Read and remove the marker:

```bash
docker compose exec --no-TTY postgres sh -lc \
  'psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
    --set=ON_ERROR_STOP=1 \
    --command="SELECT value FROM compose_persistence_check;" \
    --command="DROP TABLE compose_persistence_check;"'
```

The row returned `survives-recreation`, proving that the named volume survived
container recreation. The temporary table was then removed.

### Acceptance criteria

* ADR 0001 records the database-version decision.
* PostgreSQL 18.4 starts and becomes healthy.
* The database uses `/var/lib/postgresql/18/docker`.
* The named volume is mounted at the PostgreSQL 18 parent path.
* Data survives container recreation.
* Host access uses non-conflicting port 5433 bound to loopback.
* Container access uses `postgres:5432`.
* No real credentials are committed.
* No pgvector extension has been introduced.
* The host does not require this project's PostgreSQL version to be installed.

### Commit boundary

```bash
git add \
  compose.yaml \
  .env.example \
  docs/adr/README.md \
  docs/adr/0001-use-postgresql-18.md \
  IMPLEMENTATION_GUIDE.md

git commit -m "Add PostgreSQL development service"
```

⸻

## Stage 3.3 — Integrate Laravel with PostgreSQL

### Objective

Configure the Laravel development service to use PostgreSQL through Compose while
keeping automated tests isolated on in-memory SQLite.

### Status

Completed and verified.

### Add PDO PostgreSQL

The runtime requires `libpq`; extension compilation requires only the narrower
`libpq-dev` client headers.

Add to the Alpine runtime packages in `apps/api/Dockerfile`:

```dockerfile
libpq
```

Add to the removable build dependencies:

```dockerfile
libpq-dev
```

Add to `docker-php-ext-install`:

```dockerfile
pdo_pgsql
```

An initial attempt used the broader `postgresql-dev` package. Alpine then pulled the
full server-development toolchain, including LLVM and Clang. It was replaced with
`libpq-dev`, which is sufficient to compile PDO PostgreSQL.

### Configure the API service

Add to the API environment in `compose.yaml`:

```yaml
DB_CONNECTION: pgsql
DB_HOST: postgres
DB_PORT: 5432
DB_DATABASE: ${POSTGRES_DB:-rag_platform}
DB_USERNAME: ${POSTGRES_USER:-rag_platform}
DB_PASSWORD: ${POSTGRES_PASSWORD:-local-development-only}
```

Add PostgreSQL to the API health dependencies:

```yaml
depends_on:
  ai:
    condition: service_healthy
  postgres:
    condition: service_healthy
```

Update `apps/api/.env.example`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=rag_platform
DB_USERNAME=rag_platform
DB_PASSWORD=local-development-only
```

These are local example values, not production credentials.

### Build and restart the dependency chain

```bash
docker compose build api
docker compose up --detach --wait --wait-timeout 120 api web
```

### Verify database drivers

```bash
docker compose exec --no-TTY api php -m | rg 'pdo_pgsql|pdo_sqlite'
docker compose exec --no-TTY api php artisan about --only=drivers
```

Verified modules:

```text
pdo_pgsql
pdo_sqlite
```

The Laravel runtime reported `pgsql`, while PHPUnit continues to set
`DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` in `phpunit.xml`.

### Run migrations

```bash
docker compose exec --no-TTY api php artisan migrate --force
docker compose exec --no-TTY api php artisan migrate:status
```

Verified migrations:

```text
0001_01_01_000000_create_users_table  Ran
0001_01_01_000001_create_cache_table  Ran
0001_01_01_000002_create_jobs_table   Ran
```

### Verify test isolation

```bash
docker compose exec --no-TTY api php artisan test --display-warnings
```

Verified result:

```text
Tests: 2 passed (2 assertions)
```

The PostgreSQL migration table remained at three records after the test run, confirming
that the test configuration did not migrate or reset the development database.

### Verify database failure behaviour

Stop PostgreSQL temporarily:

```bash
docker compose stop postgres
```

Attempt a Laravel database command:

```bash
docker compose exec --no-TTY api php artisan db:show --database=pgsql
```

Laravel failed explicitly with PostgreSQL SQLSTATE `08006` and identified the
unavailable `postgres` host. Sensitive password values were not printed.

Restore the dependency chain:

```bash
docker compose up --detach --wait --wait-timeout 120 postgres api web
```

All services returned to healthy.

### Migration workflow

Apply outstanding development migrations:

```bash
docker compose exec api php artisan migrate
```

Inspect migration state:

```bash
docker compose exec api php artisan migrate:status
```

Roll back the most recent batch:

```bash
docker compose exec api php artisan migrate:rollback
```

Destructive database reset commands will be introduced behind explicit Make targets
in Phase 4 rather than normalized as routine commands here.

### Acceptance criteria

* PDO PostgreSQL and PDO SQLite are both installed.
* Laravel uses `postgres:5432` in Compose.
* The API waits for a healthy PostgreSQL service.
* All scaffold migrations run successfully.
* Migration state persists through service restarts.
* PHPUnit passes against isolated in-memory SQLite.
* PostgreSQL unavailability produces a clear connection failure.
* Database configuration is represented in `.env.example`.
* No real credentials are committed.

### Commit boundary

```bash
git add \
  apps/api/Dockerfile \
  apps/api/.env.example \
  compose.yaml \
  IMPLEMENTATION_GUIDE.md

git commit -m "Connect Laravel API to PostgreSQL"
```

⸻

## Stage 3.4 — Platform Health Verification

### Objective

Prove that web, API, AI and PostgreSQL build, start, communicate, stop and recover as
one development platform.

### Status

Completed and verified.

### Clean shutdown and complete startup

Stop and remove containers and the Compose network without deleting named volumes:

```bash
docker compose down
```

Rebuild and start the complete platform:

```bash
docker compose up \
  --detach \
  --build \
  --wait \
  --wait-timeout 180
```

Compose rebuilt all application images, recreated the network and containers, waited
through the dependency chain and reported every service healthy.

### Inspect service state

```bash
docker compose ps
```

Verified services:

```text
web       healthy  localhost:3000
api       healthy  localhost:8000
ai        healthy  localhost:8001
postgres  healthy  127.0.0.1:5433
```

### Verify host endpoints

```bash
curl --fail --silent --show-error \
  --output /dev/null \
  --write-out 'web HTTP %{http_code}\n' \
  http://127.0.0.1:3000/

curl --fail --silent --show-error \
  --output /dev/null \
  --write-out 'api HTTP %{http_code}\n' \
  http://127.0.0.1:8000/up

curl --fail --silent --show-error \
  http://127.0.0.1:8001/health
```

Verified result:

```text
web HTTP 200
api HTTP 200
{"status":"ok"}
```

### Verify service-to-service communication

```bash
docker compose exec --no-TTY web \
  node -e "fetch('http://api:8000/up').then(r=>{console.log('web-to-api',r.status);process.exit(r.ok?0:1)}).catch(e=>{console.error(e);process.exit(1)})"

docker compose exec --no-TTY api \
  php -r '$body=file_get_contents("http://ai:8001/health"); echo "api-to-ai ".$body.PHP_EOL;'
```

Verified result:

```text
web-to-api 200
api-to-ai {"status":"ok"}
```

### Verify database recovery and test isolation

```bash
docker compose exec --no-TTY api php artisan migrate:status
docker compose exec --no-TTY api php artisan test --display-warnings

docker compose exec --no-TTY postgres sh -lc \
  'psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
    --tuples-only \
    --command="SELECT current_setting('\''server_version'\''), count(*) FROM migrations;"'
```

Verified result:

```text
PostgreSQL version: 18.4
Persisted migrations: 3
Laravel tests: 2 passed
```

PostgreSQL logs reported:

```text
PostgreSQL Database directory appears to contain a database; Skipping initialization
database system is ready to accept connections
```

This confirms that the named volume was reused after the complete Compose shutdown.

### Verify logs

```bash
docker compose logs --no-color --tail 8
```

Logs remained readable and attributable to `web-1`, `api-1`, `ai-1` and `postgres-1`.

### Final running state

The verified four-service stack was left running for continued development.

To stop it without deleting data:

```bash
docker compose down
```

Do not add `--volumes` unless persistent local data is intentionally being deleted.

### Acceptance criteria

* All application images build through Compose.
* The four-service platform starts from a clean container/network state.
* Every service becomes healthy.
* Web responds on port 3000.
* API responds on port 8000.
* AI responds on port 8001.
* PostgreSQL is available internally on port 5432 and from the host on 5433.
* Web reaches API through Compose DNS.
* API reaches AI through Compose DNS.
* Laravel migrations persist after complete stack recreation.
* Laravel tests remain isolated from development PostgreSQL.
* Logs remain readable and attributable.
* `docker compose down` shuts the platform down cleanly without deleting data.

### Commit boundary

```bash
git add .
git commit -m "Verify initial Docker Compose platform"
```

⸻

Phase 4 — Developer Interface

Phase objective

Expose a stable, memorable repository interface through the root Makefile.

The interface deliberately hides the exact Compose invocations without hiding their
behaviour. Developers can use stable Make target names while the implementation
behind those targets evolves.

Before Phase 4 implementation, the architectural record was reviewed. Two
foundational decisions from earlier phases met the ADR threshold and were recorded
retrospectively:

```text
docs/adr/
├── 0002-use-three-application-service-architecture.md
└── 0003-use-container-first-local-development.md
```

Both ADRs are marked `Accepted (retrospective)` and state the phase in which the
original decision was made. ADR 0002 assigns browser presentation to Next.js, domain
authority and relational persistence to Laravel, and AI-specific processing to
FastAPI. ADR 0003 establishes Docker and Docker Compose as the canonical development
runtimes instead of requiring host Node.js, PHP or Python installations.

These are architecture records rather than substitutes for this technical execution
record. Their commands and implementation remain documented here.

⸻

## Stage 4.1 — Add Core Make Targets

### Objective

Add root commands for common build and runtime operations.

### Status

Complete.

### Engineering decisions

* Keep `/bin/bash` as the Make recipe shell.
* Make `help` the default target.
* Store `docker compose` and non-interactive `docker compose exec -T` in Make
  variables so all targets use the same invocation.
* Make the health wait configurable with `WAIT_TIMEOUT`, defaulting to 180 seconds.
* Make the log history configurable with `TAIL`, defaulting to 100 lines.
* Use `--wait` for `up`, `restart` and `bootstrap`; returning from these commands means
  the complete platform is healthy, not merely that containers were created.
* Keep `down` non-destructive by omitting `--volumes`.
* Use `--force-recreate` for `restart` so the target verifies container recreation
  rather than only restarting existing processes.
* Declare every public target phony so a repository file with the same name cannot
  suppress the command.

### Shared Make configuration

The root `Makefile` begins with:

```make
SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose
EXEC := $(COMPOSE) exec -T
WAIT_TIMEOUT ?= 180
TAIL ?= 100
```

`?=` lets a caller override the defaults:

```bash
make up WAIT_TIMEOUT=300
make logs TAIL=25
```

### Core target implementation

```make
build:
	$(COMPOSE) build

up:
	$(COMPOSE) up --detach --wait --wait-timeout $(WAIT_TIMEOUT)

down:
	$(COMPOSE) down --remove-orphans

restart:
	$(COMPOSE) up --detach --force-recreate --wait --wait-timeout $(WAIT_TIMEOUT)

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs --follow --tail $(TAIL)
```

`make logs` is intentionally foregrounded and continues following output until the
developer interrupts it. Compose's `--no-color` option is not forced because colour
and service labels are helpful during interactive use.

The help target prints grouped Environment, Database, Quality, Maintenance and Shell
commands. It documents every public target without requiring an external Make help
utility.

### Verify the public command list

```bash
make help
```

The command printed all public targets and returned successfully.

### Verify command expansion

Before running the lifecycle commands, their recipes were inspected without executing
them:

```bash
make -n build up down restart ps migrate seed clean \
  shell-web shell-api shell-ai shell-db
```

This confirmed that each public target expands to the expected Compose command.

### Verify image builds

```bash
make build
```

Docker successfully built the web, API and AI development images. Existing dependency
layers were reused where their inputs had not changed.

### Verify stop, start and recreation

The following Make goals were executed in order:

```bash
make down up restart ps
```

Results:

* `down` removed the four containers and Compose network but retained all named
  volumes.
* `up` recreated the network and containers and waited for all four health checks.
* `restart` force-recreated every service and again waited for health.
* `ps` reported `web`, `api`, `ai` and `postgres` as healthy.
* PostgreSQL remained published on `127.0.0.1:5433`; application ports remained 3000,
  8000 and 8001.
* Existing migrations remained present, confirming that `down` and `restart` retained
  database data.

The `logs` recipe wraps the same labelled `docker compose logs` behaviour verified in
Stage 3.4. The follow mode was not left running during automated verification because
it is intentionally interactive.

### Shell helpers

The developer interface also includes:

```make
shell-web:
	$(COMPOSE) exec web sh

shell-api:
	$(COMPOSE) exec api sh

shell-ai:
	$(COMPOSE) exec ai sh

shell-db:
	$(COMPOSE) exec postgres psql \
	  --username "$${POSTGRES_USER:-rag_platform}" \
	  --dbname "$${POSTGRES_DB:-rag_platform}"
```

Shell targets omit `-T` so an interactive terminal is allocated. Automated quality
and migration targets use `exec -T` so they also work in CI environments without a
TTY.

### Acceptance criteria

* `make help` documents all public targets.
* Each target wraps an inspected and tested underlying command.
* Make propagates a non-zero result from its recipe.
* Commands run from the repository root.
* Lifecycle commands wait for service health.
* Normal shutdown does not delete persistent data.
* Target names remain stable even if their Compose implementation changes later.

### Commit boundary

```bash
git add Makefile
git commit -m "Add core developer Make targets"
```

No commit was created during this stage because commits remain user-controlled.

⸻

## Stage 4.2 — Add Quality and Test Targets

### Objective

Provide repository-level commands for linting, formatting, type checking and tests.

### Status

Complete.

### Tool mapping

| Concern | Web | API | AI |
|---|---|---|---|
| Lint | ESLint | Laravel Pint check | Ruff check |
| Format | ESLint autofix | Laravel Pint | Ruff format |
| Format check | ESLint | Laravel Pint check | Ruff format check |
| Type check | TypeScript | Not configured | MyPy |
| Test | No suite yet | Laravel/PHPUnit | Pytest |

PHP static analysis is not silently implied: PHPStan or Larastan has not yet been
selected or installed. The aggregate `typecheck` target therefore runs only the
currently configured TypeScript and MyPy checks.

The web application currently has no automated test framework or test files. Adding a
framework only to make the target appear active would make a premature technology
decision. `make test-web` explicitly reports that there is no suite and exits
successfully. `make test` still runs every test suite that currently exists.

ESLint autofix is the only configured web code-fixing tool. It is not presented as a
replacement for a future dedicated formatter such as Prettier.

### Lint targets

```make
lint: lint-web lint-api lint-ai

lint-web:
	$(EXEC) web npm run lint

lint-api:
	$(EXEC) api ./vendor/bin/pint --test

lint-ai:
	$(EXEC) ai uv run ruff check .
```

### Format targets

Formatting and non-mutating formatting verification are separate:

```make
format: format-web format-api format-ai

format-web:
	$(EXEC) web npm run lint -- --fix

format-api:
	$(EXEC) api ./vendor/bin/pint

format-ai:
	$(EXEC) ai uv run ruff format .

format-check: format-check-web format-check-api format-check-ai

format-check-web:
	$(EXEC) web npm run lint

format-check-api:
	$(EXEC) api ./vendor/bin/pint --test

format-check-ai:
	$(EXEC) ai uv run ruff format --check .
```

`format-check` is suitable for CI because it does not intentionally rewrite source
files.

### Type-check targets

```make
typecheck: typecheck-web typecheck-ai

typecheck-web:
	$(EXEC) web npx tsc --noEmit

typecheck-ai:
	$(EXEC) ai uv run mypy app tests
```

`npx` resolves the project-local TypeScript binary from the container's
`node_modules`; it does not download a different compiler because TypeScript is
already a locked web dependency.

### Test targets

```make
test: test-web test-api test-ai

test-web:
	@printf '%s\n' \
	  'Web: no automated test suite is configured yet; nothing to run.'

test-api:
	$(EXEC) api php artisan test

test-ai:
	$(EXEC) ai uv run pytest
```

### Run the complete quality interface

```bash
make format format-check lint typecheck test
```

Verified results:

* ESLint completed with no errors.
* ESLint autofix completed without changing the current web source.
* Laravel Pint passed all 26 inspected PHP files.
* Ruff lint passed.
* Ruff reported all 6 Python files already formatted.
* TypeScript completed with no errors and emitted no files.
* MyPy reported no issues in 5 source files.
* Laravel ran 2 tests with 2 assertions; both passed.
* Pytest collected and passed the AI health test.
* The web target clearly reported that no automated suite is configured.

All checks executed inside their application containers. A failure in any child target
stops its aggregate Make target and returns a non-zero shell status.

### Acceptance criteria

* Each configured application check can run independently.
* `make lint` checks all applicable services.
* `make test` runs every currently configured test suite.
* The absence of a web test suite is reported rather than hidden.
* Formatting and formatting verification are separate.
* Type checking reflects the tools actually installed.
* Failures propagate to the calling shell.
* All runtime-dependent targets execute inside containers.

### Commit boundary

```bash
git add Makefile
git commit -m "Add repository quality and test targets"
```

No commit was created during this stage.

⸻

## Stage 4.3 — Add Bootstrap and Reset Targets

### Objective

Make first-time setup and local-environment recovery predictable.

### Status

Complete.

### Safety decisions

`bootstrap` is additive and repeatable. It creates `.env` only when the file is
missing, builds and starts the platform, waits for health and applies outstanding
migrations.

`down` and `clean` do not delete PostgreSQL data.

`reset` is explicitly destructive. It:

1. displays a warning that all Compose volumes, including PostgreSQL, will be deleted;
2. requires the user to type the exact word `RESET`;
3. cancels with a non-zero result for any other input;
4. removes the project containers, network and volumes only after confirmation;
5. runs `bootstrap` to rebuild the local environment.

No `--volumes` option is hidden behind an ordinary stop or clean command.

### Bootstrap implementation

```make
bootstrap:
	@test -f .env || { \
	  cp .env.example .env; \
	  printf '%s\n' 'Created .env from .env.example'; \
	}
	$(COMPOSE) up --detach --build \
	  --wait --wait-timeout $(WAIT_TIMEOUT)
	$(MAKE) migrate

migrate:
	$(EXEC) api php artisan migrate --force
```

The local `.env` file is ignored by Git. Existing developer settings are never
overwritten by `bootstrap`.

Laravel's `--force` option prevents migration prompts when the environment is run
non-interactively. It does not force migrations to rerun: a repeat invocation
correctly reported `Nothing to migrate`.

### Seed implementation and issue found

The first Make target wrapped the existing Laravel seeder:

```make
seed:
	$(EXEC) api php artisan db:seed --force
```

During review, the scaffolded `DatabaseSeeder` was found to call `create()` with the
fixed email `test@example.com`. The first seed would work, but the second would violate
the users table's unique email constraint. That contradicted the repeatable developer
workflow.

The seeder was changed to use the email as its natural idempotency key:

```php
User::query()->firstOrCreate(
    ['email' => 'test@example.com'],
    [
        'name' => 'Test User',
        'password' => Hash::make('password'),
    ],
);
```

Run the seed twice:

```bash
make seed
make seed
```

Then verify the result directly:

```bash
docker compose exec -T postgres psql \
  --username rag_platform \
  --dbname rag_platform \
  --tuples-only \
  --no-align \
  --command \
  "SELECT count(*) FROM users WHERE email = 'test@example.com';"
```

The result was `1`, confirming that repeated seeding does not duplicate the local test
user.

### Reset implementation

```make
reset:
	@printf '%s\n' \
	  'WARNING: this deletes all local Compose volumes, including PostgreSQL data.' \
	  'Type RESET to continue:'; \
	read -r answer; \
	if [ "$$answer" != 'RESET' ]; then \
	  printf '%s\n' 'Reset cancelled.'; \
	  exit 1; \
	fi
	$(COMPOSE) down --volumes --remove-orphans
	$(MAKE) bootstrap
```

The cancellation path was tested without deleting data:

```bash
printf 'NO\n' | make reset
```

It printed `Reset cancelled.` and Make returned a non-zero status. The destructive path
was not run against the working development project.

### Clean implementation

The first implementation deleted `.next` through `docker compose exec` while the
Next.js development server was running:

```make
clean:
	$(EXEC) web sh -c 'rm -rf .next/*'
	$(EXEC) api php artisan optimize:clear
	$(EXEC) ai sh -c \
	  'find app tests -type d -name __pycache__ -prune -exec rm -rf {} +; \
	  rm -rf .mypy_cache .pytest_cache .ruff_cache'
```

The commands returned successfully, but the final platform health check found the web
service unhealthy. Next.js Turbopack still held references to files removed from its
live cache and returned HTTP 500. Its logs included:

```text
Failed to restore task data (corrupted database or bug)
Failed to open SST file /app/.next/dev/cache/turbopack/...
No such file or directory
```

Deleting a live Next.js compiler cache was therefore rejected. The corrected target
stops only the web service, clears its named cache volume through a disposable
container, clears the API and AI caches, and force-recreates the web container before
waiting for health:

```make
clean:
	$(COMPOSE) stop web
	$(COMPOSE) run --rm --no-deps web sh -c 'rm -rf .next/*'
	$(EXEC) api php artisan optimize:clear
	$(EXEC) ai sh -c \
	  'find app tests -type d -name __pycache__ -prune -exec rm -rf {} +; \
	  rm -rf .mypy_cache .pytest_cache .ruff_cache'
	$(COMPOSE) up --detach --force-recreate --no-deps web \
	  --wait --wait-timeout $(WAIT_TIMEOUT)
```

This removes generated application caches but does not remove dependency or database
volumes. The target expects the development stack to be running because it clears API
and AI caches with `exec`. The web process never observes a partially deleted
Turbopack cache. `--no-deps` prevents the final web recreation from unnecessarily
recreating the already healthy API, AI and PostgreSQL services.

The finalized target was run again. Compose recreated only `web`, its health check
passed, and `docker compose ps` reported all four services healthy.

### Verify repeat bootstrap

Run against the working stack:

```bash
make bootstrap
make migrate
```

Compose rebuilt from cached layers, all services remained healthy and both migration
invocations reported no outstanding work.

### Verify first-time bootstrap in isolation

A second, explicitly named Compose project was created on alternate host ports so
bootstrap could be tested with brand-new volumes without touching working development
data:

```bash
env \
  COMPOSE_PROJECT_NAME=rag-platform-bootstrap-verification \
  WEB_PORT=3100 \
  API_PORT=8100 \
  AI_PORT=8101 \
  POSTGRES_PORT=5544 \
  NEXT_PUBLIC_API_URL=http://localhost:8100 \
  make bootstrap
```

The isolated project:

* built all three application images;
* created five new named volumes;
* started all four services;
* reached healthy status for every service;
* created the Laravel migration table in the new PostgreSQL database;
* ran the users, cache and jobs migrations successfully.

The disposable verification project was then removed by exact project name:

```bash
env \
  COMPOSE_PROJECT_NAME=rag-platform-bootstrap-verification \
  WEB_PORT=3100 \
  API_PORT=8100 \
  AI_PORT=8101 \
  POSTGRES_PORT=5544 \
  docker compose down --volumes --remove-orphans
```

Only the temporary project's containers, network and volumes were deleted. The normal
`rag-platform` project and its database remained running.

### README update

The root `README.md` now documents:

* the platform technology summary;
* Docker, Docker Compose and Make as host prerequisites;
* `make bootstrap` as the initial setup command;
* the common developer commands;
* the destructive nature of `make reset`;
* the currently available service URLs;
* PostgreSQL's local port 5433;
* that Qdrant and Mailpit are future-phase services rather than currently available
  endpoints.

### Acceptance criteria

* A first-time environment can be built, started, health-checked and migrated with
  `make bootstrap`.
* Repeat bootstrap and migration are safe.
* Reset behaviour is explicit and requires exact interactive confirmation.
* Routine stop and clean commands preserve PostgreSQL data.
* Container-owned dependency volumes prevent root-owned host dependencies.
* Dependency installation remains locked inside the application images.
* Database migrations and the current seed can be rerun safely.
* The repository README documents the operational interface.

### Commit boundary

```bash
git add \
  Makefile \
  README.md \
  apps/api/database/seeders/DatabaseSeeder.php \
  docs/adr/README.md \
  docs/adr/0002-use-three-application-service-architecture.md \
  docs/adr/0003-use-container-first-local-development.md \
  IMPLEMENTATION_GUIDE.md

git commit -m "Add developer interface and retrospective ADRs"
```

No commit was created during this stage.

⸻

Phase 5 — Local AWS Development

Phase objective

Introduce local AWS-compatible infrastructure without requiring real cloud resources during routine development.

⸻

## Stage 5.1 — Add LocalStack

### Objective

Add LocalStack to Docker Compose for locally emulated AWS services.

### Status

Complete.

### Initial services

Only the services required by the next application capabilities are enabled:

* S3 for document objects;
* SQS for asynchronous ingestion requests and dead-letter handling.

Redis, Qdrant and Mailpit remain later-phase services. Enabling every potential
LocalStack service was rejected because it increases startup work and obscures which
AWS contracts the platform currently depends on.

### LocalStack version and licensing investigation

The current official release was researched before implementation. LocalStack changed
from semantic versioning to calendar versioning after 4.14.0. The July 2026 immutable
release tag was pulled successfully:

```bash
docker pull localstack/localstack:2026.07.0
```

The image digest pulled during verification was:

```text
sha256:2a81e5da4c32bb53e8d86e92050a12937f9be1915c5a4afad0931f75c112fc7e
```

The first startup attempt failed:

```bash
docker compose up --detach localstack \
  --wait \
  --wait-timeout 180
```

LocalStack exited with code 55:

```text
License activation failed
No credentials were found in the environment
```

Starting with LocalStack 2026.03, even the Hobby tier requires a LocalStack account,
an assigned licence and a confidential auth token. The official `ACTIVATE_PRO=0`
compatibility setting was tested in a disposable container:

```bash
docker run --rm \
  -e ACTIVATE_PRO=0 \
  -e SERVICES=s3,sqs \
  localstack/localstack:2026.07.0
```

It also exited with code 55. No token was created, requested, stored or committed.

Three alternatives were considered:

1. require a current LocalStack account and token from every developer and CI system;
2. use the last open-source LocalStack Community release;
3. replace LocalStack with separate open-source S3 and SQS emulators.

Account-free onboarding was selected. The decision and its maintenance risk are
recorded in:

```text
docs/adr/0004-use-localstack-4-for-local-aws-emulation.md
```

### Selected image

Pull the final Community release:

```bash
docker pull localstack/localstack:4.14.0
```

The verified image digest was:

```text
sha256:3ebc37595918b8accb852f8048fef2aff047d465167edd655528065b07bc364a
```

LocalStack reported version 4.14.0, build date 2026-02-26 and build Git hash
`3d5a0c70e`.

This image is intentionally local-only and archived. It is never a production
dependency.

### Compose implementation

The service added to `compose.yaml` is:

```yaml
localstack:
  image: localstack/localstack:4.14.0
  ports:
    - "127.0.0.1:${LOCALSTACK_PORT:-4566}:4566"
  environment:
    SERVICES: s3,sqs
    SQS_ENDPOINT_STRATEGY: dynamic
    DEBUG: ${LOCALSTACK_DEBUG:-0}
    AWS_ACCESS_KEY_ID: ${AWS_ACCESS_KEY_ID:-test}
    AWS_SECRET_ACCESS_KEY: ${AWS_SECRET_ACCESS_KEY:-test}
    AWS_DEFAULT_REGION: ${AWS_DEFAULT_REGION:-us-east-1}
    DOCUMENT_UPLOAD_BUCKET: ${DOCUMENT_UPLOAD_BUCKET:-rag-platform-document-uploads-local}
    INGESTION_QUEUE: ${INGESTION_QUEUE:-rag-platform-ingestion-local}
    INGESTION_DLQ: ${INGESTION_DLQ:-rag-platform-ingestion-dlq-local}
    SQS_MAX_RECEIVE_COUNT: ${SQS_MAX_RECEIVE_COUNT:-3}
  volumes:
    - ./infrastructure/localstack/init/ready.d:/etc/localstack/init/ready.d:ro
    - ./scripts/localstack:/opt/rag-platform/localstack:ro
  tmpfs:
    - /var/lib/localstack
  healthcheck:
    test:
      - CMD-SHELL
      - >-
        awslocal s3api head-bucket --bucket "$$DOCUMENT_UPLOAD_BUCKET"
        && awslocal sqs get-queue-url --queue-name "$$INGESTION_QUEUE"
        && awslocal sqs get-queue-url --queue-name "$$INGESTION_DLQ"
    interval: 5s
    timeout: 5s
    retries: 20
    start_period: 20s
  stop_grace_period: 15s
```

Important properties:

* port 4566 is bound only to loopback;
* credentials are visibly non-production placeholders;
* only S3 and SQS are enabled;
* initialization and verification scripts are read-only inside the container;
* health means the bucket and both queues exist, not merely that the HTTP gateway
  opened;
* `dynamic` SQS endpoints use the requesting hostname, so SDK calls from another
  container receive a usable `http://localstack:4566/...` queue URL;
* LocalStack data is explicitly ephemeral.

### Persistence test and correction

The first configuration used:

```yaml
environment:
  PERSISTENCE: "1"
volumes:
  - localstack_data:/var/lib/localstack
```

A temporary object was written through Boto3, LocalStack was force-recreated and the
object was read again. The read failed with `NoSuchKey`. Inspection showed no service
state under `/var/lib/localstack`.

Official plan documentation confirms that local state persistence is a paid
Base/Ultimate feature. Community 4.14 accepts the environment variable but does not
persist the S3 object state used by this project.

The ineffective flag and named volume were removed. Because the image itself declares
`/var/lib/localstack` as a volume, Compose initially retained the previous named
volume during recreation. The service now mounts that path as `tmpfs` to make
ephemeral behaviour explicit and prevent Docker from silently creating a persistent
volume.

Recreate with new volume semantics:

```bash
docker compose up \
  --detach \
  --force-recreate \
  --renew-anon-volumes \
  --no-deps \
  localstack \
  --wait \
  --wait-timeout 180
```

The obsolete `rag-platform_localstack_data` volume was then removed. It contained
only the failed Phase 5 cache/state experiment and was not application data.

This intentionally differs from the early architecture screenshot that listed a
`localstack-data` volume. Keeping that volume would falsely suggest that Community
4.14 preserves local AWS data. Buckets and queues are recreated; S3 objects and queue
messages are disposable.

### Service and container names

Compose service keys follow the architecture naming:

```text
web
api
ai
postgres
localstack
```

Future phases add `redis`, `qdrant` and `mailpit`.

No `container_name` values such as `rag-web` are hard-coded. Compose-generated names
include the project and replica number, for example `rag-platform-web-1`. This allows
parallel isolated projects, as used by the bootstrap verification, and preserves the
ability to scale services. Internal DNS remains the short stable service name such as
`api`, `ai`, `postgres` or `localstack`.

### Root environment contract

The following local-only defaults were added to `.env.example`:

```dotenv
LOCALSTACK_PORT=4566

AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
AWS_DEFAULT_REGION=us-east-1
LOCALSTACK_DEBUG=0

DOCUMENT_UPLOAD_BUCKET=rag-platform-document-uploads-local
INGESTION_QUEUE=rag-platform-ingestion-local
INGESTION_DLQ=rag-platform-ingestion-dlq-local
SQS_MAX_RECEIVE_COUNT=3
```

The same non-secret values were added to the ignored local `.env`. No production AWS
credential is present.

### Standard application AWS dependencies

LocalStack-specific SDKs were deliberately avoided.

Laravel's standard Flysystem S3 adapter was installed:

```bash
docker compose exec -T api composer require \
  league/flysystem-aws-s3-v3:^3.0 \
  --with-all-dependencies \
  --no-interaction
```

This locked:

```text
league/flysystem-aws-s3-v3  3.35.2
aws/aws-sdk-php              3.389.0
aws/aws-crt-php              1.2.7
mtdowling/jmespath.php       2.9.2
symfony/filesystem           8.1.0
```

Python's standard AWS SDK was installed:

```bash
docker compose exec -T ai uv add 'boto3>=1.42.0'
```

The resolved runtime versions were:

```text
boto3       1.43.56
botocore    1.43.56
s3transfer  0.19.2
```

Both dependency locks were regenerated by their package managers.

### Laravel environment

The API service receives:

```yaml
FILESYSTEM_DISK: s3
QUEUE_CONNECTION: sqs
AWS_ACCESS_KEY_ID: ${AWS_ACCESS_KEY_ID:-test}
AWS_SECRET_ACCESS_KEY: ${AWS_SECRET_ACCESS_KEY:-test}
AWS_DEFAULT_REGION: ${AWS_DEFAULT_REGION:-us-east-1}
AWS_BUCKET: ${DOCUMENT_UPLOAD_BUCKET:-rag-platform-document-uploads-local}
AWS_ENDPOINT: http://localstack:4566
AWS_USE_PATH_STYLE_ENDPOINT: "true"
SQS_ENDPOINT: http://localstack:4566
SQS_PREFIX: http://localstack:4566/000000000000
SQS_QUEUE: ${INGESTION_QUEUE:-rag-platform-ingestion-local}
```

Laravel's generated SQS configuration did not expose a custom SDK endpoint. The
following portable option was added to `apps/api/config/queue.php`:

```php
'endpoint' => env('SQS_ENDPOINT'),
```

In production, `SQS_ENDPOINT` is unset and the AWS SDK resolves the normal regional
AWS endpoint.

### Python environment

The AI service receives:

```yaml
AWS_ACCESS_KEY_ID: ${AWS_ACCESS_KEY_ID:-test}
AWS_SECRET_ACCESS_KEY: ${AWS_SECRET_ACCESS_KEY:-test}
AWS_DEFAULT_REGION: ${AWS_DEFAULT_REGION:-us-east-1}
AWS_ENDPOINT_URL: http://localstack:4566
DOCUMENT_UPLOAD_BUCKET: ${DOCUMENT_UPLOAD_BUCKET:-rag-platform-document-uploads-local}
INGESTION_QUEUE: ${INGESTION_QUEUE:-rag-platform-ingestion-local}
INGESTION_DLQ: ${INGESTION_DLQ:-rag-platform-ingestion-dlq-local}
```

Both `api` and `ai` declare a healthy LocalStack dependency. Laravel additionally
depends on PostgreSQL and the AI service, preserving the existing startup order.

### AWS cross-over contract

Application code crosses from local emulation to AWS through configuration:

| Setting | Local development | AWS deployment |
|---|---|---|
| SDK | AWS SDK for PHP / Boto3 | Same |
| Credentials | literal `test` placeholders | task role or managed AWS credentials |
| Region | configurable, default `us-east-1` | deployment region |
| S3 endpoint | `http://localstack:4566` | unset |
| S3 path style | `true` | normally `false` |
| SQS endpoint | `http://localstack:4566` | unset |
| Resource names | `*-local` environment values | provisioned AWS names |

No application branch checks whether it is running against LocalStack.

### Start the five-service platform

```bash
make bootstrap
```

The command rebuilt the API and AI images with their AWS dependencies, recreated the
application services, waited for all five health checks and confirmed that no Laravel
migrations were outstanding.

### Acceptance criteria

* ADR 0004 records the LocalStack version and licensing trade-off.
* LocalStack 4.14.0 starts without an account or confidential token.
* Only S3 and SQS are enabled.
* The gateway is published only on loopback.
* The LocalStack health check includes provisioned resources.
* Laravel and Python use standard AWS libraries.
* Endpoint overrides are environment configuration.
* Production can use the same adapters without LocalStack-specific code.
* Ephemeral Community state is represented honestly.
* All five services become healthy.

### Commit boundary

Stage 5.1 and 5.2 are committed together at the Phase 5 gate because the service is
not useful until its resources and both application clients have been verified.

⸻

## Stage 5.2 — Provision Local AWS Resources

### Objective

Create reproducible local S3 buckets and SQS queues.

### Status

Complete.

### Resource contract

The initial local resources are:

| Resource | Default name | Purpose |
|---|---|---|
| S3 bucket | `rag-platform-document-uploads-local` | Original document uploads |
| SQS queue | `rag-platform-ingestion-local` | Document ingestion requests |
| SQS dead-letter queue | `rag-platform-ingestion-dlq-local` | Messages exceeding retries |
| Redrive policy | maximum receive count `3` | Moves repeatedly failing messages to the DLQ |

A processed-document bucket was not created. Its storage topology belongs to the
document-domain decision in Phase 8; provisioning it now would turn a placeholder into
an accidental architecture.

All names and the maximum receive count are configurable through environment values.

### Initialization hook

The executable initialization hook is:

```text
infrastructure/localstack/init/ready.d/10-provision-aws.sh
```

LocalStack runs executable scripts mounted under `/etc/localstack/init/ready.d` after
its AWS APIs are ready.

The script:

1. supplies defaults only when environment values are absent;
2. checks for the upload bucket before creating it;
3. handles the special S3 create-bucket behaviour for `us-east-1`;
4. creates or resolves the dead-letter queue;
5. reads the DLQ ARN;
6. creates or resolves the ingestion queue;
7. sets the ingestion queue's redrive policy;
8. exits immediately on an error through `set -eu`.

The queue policy JSON is written to a temporary file inside the container because the
AWS CLI's nested JSON value otherwise requires fragile multi-layer shell escaping. The
temporary file is removed immediately after `set-queue-attributes`.

Both provisioning and verification scripts have mode 0755:

```bash
chmod 755 \
  infrastructure/localstack/init/ready.d/10-provision-aws.sh \
  scripts/localstack/verify.sh
```

### Verification script

The executable verification script is:

```text
scripts/localstack/verify.sh
```

It fails unless:

* the bucket exists;
* the ingestion queue exists;
* the DLQ exists;
* the main queue's `RedrivePolicy` points to the exact DLQ ARN;
* `maxReceiveCount` equals the configured value.

It uses `awslocal` only inside the emulator container. Application code continues to
use normal AWS SDK clients.

### Make targets

The root developer interface now includes:

```make
aws-provision:
	$(EXEC) localstack \
	  /etc/localstack/init/ready.d/10-provision-aws.sh

aws-status:
	$(EXEC) localstack \
	  /opt/rag-platform/localstack/verify.sh

shell-aws:
	$(COMPOSE) exec localstack bash
```

The targets are documented by `make help`.

### Verify initial provisioning

After LocalStack became healthy:

```bash
make aws-status
```

The output confirmed:

```text
bucket: rag-platform-document-uploads-local
queue:  rag-platform-ingestion-local
dlq:    rag-platform-ingestion-dlq-local
redrive maxReceiveCount: 3
```

### Verify provisioning idempotency

Run the provisioner and verifier again:

```bash
make aws-provision
make aws-status
```

Both commands succeeded. The existing bucket and queues were reused, and the redrive
policy remained correct.

LocalStack was also recreated without a state volume:

```bash
docker compose up \
  --detach \
  --force-recreate \
  --renew-anon-volumes \
  --no-deps \
  localstack \
  --wait \
  --wait-timeout 180

make aws-status
```

The initialization hook recreated the complete resource contract before the health
check passed.

### Verify Laravel S3 and SQS access

Laravel was tested through its normal facades:

```bash
docker compose exec -T api php artisan tinker --execute="
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;

Storage::disk('s3')->put(
    'phase-5/laravel-check.txt',
    'laravel-to-s3',
);

echo Storage::disk('s3')->get(
    'phase-5/laravel-check.txt',
).PHP_EOL;

Storage::disk('s3')->delete(
    'phase-5/laravel-check.txt',
);

echo 'deleted='.
    (Storage::disk('s3')->missing('phase-5/laravel-check.txt')
        ? 'yes'
        : 'no').
    PHP_EOL;

echo 'message_id='.
    Queue::connection('sqs')->pushRaw(
        '{\"source\":\"laravel\",\"phase\":5}',
    ).
    PHP_EOL;
"
```

Results:

```text
laravel-to-s3
deleted=yes
message_id=d99b89d6-4d37-48b2-a998-f66a117878a0
```

This proves the configured Laravel filesystem can write, read and delete S3 objects
and the Laravel queue connection can send to SQS.

### Verify Python S3 and SQS access

The first one-line diagnostic failed at Python parsing because an f-string contained
an escaped dictionary-key expression. No AWS operation executed. The output
formatting was simplified and the corrected Boto3 proof then:

1. wrote, read and deleted a test S3 object;
2. resolved the ingestion queue through the configured endpoint;
3. received Laravel's test message;
4. deleted the Laravel message;
5. sent a Python message;
6. received and deleted the Python message.

The successful result was:

```text
s3=ok
queue_url=http://localstack:4566/queue/us-east-1/000000000000/rag-platform-ingestion-local
message_id=5daa4038-efe5-4a9d-b6b8-f15359812a31
```

The `localstack` hostname in the returned URL proves that
`SQS_ENDPOINT_STRATEGY=dynamic` generated a URL reachable from the AI container.

All integration-test objects and messages were deleted after verification.

### Dependency and security verification

```bash
docker compose exec -T api composer validate --strict
docker compose exec -T api composer audit
docker compose exec -T ai uv lock --check
docker compose exec -T ai uv pip check
```

Results:

* `composer.json` is valid;
* Composer reported no security advisories;
* the uv lock is current;
* all installed Python packages are compatible.

### Run all repository quality gates

```bash
make format-check lint typecheck test
```

Results:

* ESLint passed;
* Laravel Pint passed all 26 PHP files;
* Ruff lint and format checks passed;
* TypeScript passed with no output;
* MyPy reported no issues in 5 source files;
* both Laravel tests passed;
* the Python health test passed;
* the web target continued to report honestly that no automated suite exists yet.

### Final platform state

```bash
docker compose ps
make aws-status
```

The healthy services are:

```text
web         localhost:3000
api         localhost:8000
ai          localhost:8001
postgres    127.0.0.1:5433
localstack  127.0.0.1:4566
```

The stack was left running for continued development.

### Acceptance criteria

* Resources are created automatically before LocalStack is healthy.
* Provisioning is idempotent.
* Queue and bucket names are configurable.
* The ingestion queue has a verified DLQ redrive policy.
* Resources can be verified with one Make target.
* Laravel reads and writes S3 through Flysystem.
* Laravel sends SQS messages through its normal queue connection.
* Python reads and writes S3 through Boto3.
* Python sends and receives SQS messages through Boto3.
* Cross-service queue URLs are reachable.
* Test objects and messages are cleaned up.
* No production AWS resource or credential was used.
* All repository checks pass.
* All five services are healthy.

### Phase commit and tag

Phases 2 through 5 were completed before phase-level commit/tagging was requested.
Their accumulated, verified implementation is recorded as one honest milestone:

```bash
git add \
  .env.example \
  README.md \
  IMPLEMENTATION_GUIDE.md \
  Makefile \
  apps \
  compose.yaml \
  docs/adr \
  infrastructure/localstack \
  scripts/localstack

git commit -m "Complete platform foundation through Phase 5"
git tag -a phase-5 -m "Platform foundation through Phase 5"
```

The unrelated untracked `tasks.json` project-planning file is not included without an
explicit decision to adopt and update that separate workflow.

⸻

# Phase 6 — Authentication and Identity

## Phase objective

Establish secure user identity before introducing tenant-owned documents and conversations.

---

## Stage 6.1 — Define Authentication Architecture

### Objective

Choose and document the authentication boundary between Next.js and Laravel.

### Status

Completed on 2026-07-26.

### Decision

Option A was accepted with thirteen explicit refinements and recorded before
implementation in:

```text
docs/adr/0005-use-sanctum-and-fortify-for-first-party-spa-authentication.md
```

Laravel Sanctum is used as Laravel's recommended stateful authentication approach
for a first-party SPA. The recommendation is not Next.js-specific. Laravel is the
only authentication and authorisation authority.

The browser normally calls Laravel directly. Next.js is not a mandatory
backend-for-frontend and does not duplicate Laravel authorisation. Selective
server-side fetching is permitted, and is used by the protected workspace page to
ask Laravel whether the request may use platform functionality.

Sanctum's stateful SPA mode requires the web and API hosts to share one top-level
domain. The intended production hosts are:

```text
https://app.dolved.ai
https://api.dolved.ai
```

The matching production settings are documented in `.env.example`:

```dotenv
APP_URL=https://api.dolved.ai
FRONTEND_URL=https://app.dolved.ai
NEXT_PUBLIC_API_URL=https://api.dolved.ai
CORS_ALLOWED_ORIGINS=https://app.dolved.ai
SANCTUM_STATEFUL_DOMAINS=app.dolved.ai
SESSION_DOMAIN=.dolved.ai
SESSION_SECURE_COOKIE=true
```

Local development uses `localhost:3000` and `localhost:8000`. Ports are included in
the stateful-domain list as required by Sanctum.

### Cookie and CSRF model

Two cookies have deliberately different properties:

* `rag-platform-session` contains the Laravel session identifier and is `HttpOnly`;
  frontend JavaScript cannot read it.
* `XSRF-TOKEN` is intentionally frontend-readable. The web client URL-decodes its
  value and sends it as `X-XSRF-TOKEN` on state-changing requests.

Both are sent only through requests using `credentials: "include"`. Production uses
HTTPS, `Secure` cookies and `SameSite=Lax`.

Sessions use PostgreSQL through Laravel's `database` session driver with an initial
idle lifetime of 120 minutes. A later move to Redis changes operations, not the
authentication architecture.

### Fortify boundary

Laravel Fortify remains headless. Its maintained actions, password broker,
notifications, verification requests and controllers sit beneath the application-owned
`/api/auth/*` JSON contract. Password recovery and email verification were not
reimplemented.

The exact unauthenticated allow-list is:

```text
GET  /sanctum/csrf-cookie
POST /api/auth/register
POST /api/auth/login
POST /api/auth/forgot-password
POST /api/auth/reset-password
```

Registration returns `404` when `AUTH_REGISTRATION_ENABLED=false`, preserving the
contract while allowing a later invite-only policy.

The authenticated-but-unverified allow-list is:

```text
GET  /api/auth/user
POST /api/auth/logout
GET  /api/auth/email/verify/{id}/{hash}
POST /api/auth/email/verification-notification
```

All actual platform routes use both:

```php
['auth:sanctum', 'verified']
```

`GET /api/platform/status` is the first protected proof route. Next.js redirects are
user-experience aids; Laravel still enforces both conditions.

### Identity rules

`App\Support\CanonicalEmail` owns email canonicalisation:

```text
trim whitespace
→ lowercase
→ validate
→ store the canonical value
```

The existing unique `users.email` database constraint therefore applies to the
canonical value.

The initial password rule is:

```text
minimum 12 characters
upper- and lowercase letters
at least one number
at least one symbol
```

`uncompromised()` was not enabled initially because it makes validation depend on an
external Have I Been Pwned request. That can be reconsidered when the operational
failure policy is defined.

A successful password reset:

* hashes and stores the new password;
* rotates `remember_token`;
* deletes every database session belonging to that user.

Login failures use Laravel's generic credential error. Forgotten-password requests
return the same success message for present and absent accounts. Malformed email,
weak password and duplicate-registration errors remain useful validation responses.

### ADR creation

```bash
git status --short
sed -n '1,240p' docs/adr/README.md
```

The ADR and index were written before package installation. Phase-level commit and
tagging is used, so Stage 6.1 is committed together with the implementation it
governs.

---

## Stage 6.2 — Implement Laravel Authentication

### Objective

Implement the accepted authentication contract with Sanctum, Fortify, PostgreSQL
sessions and local email capture.

### Status

Completed on 2026-07-26.

### Install Sanctum and Fortify

The packages were installed through the running API container so the host does not
need Composer:

```bash
docker compose exec api composer require laravel/sanctum laravel/fortify
```

Resolved versions:

```text
laravel/sanctum  v4.3.3
laravel/fortify  v1.37.3
```

The official installers were then run:

```bash
docker compose exec api php artisan install:api --no-interaction
docker compose exec api php artisan fortify:install --no-interaction
docker compose exec api php artisan config:publish cors --no-interaction
```

The generated scaffold included personal access tokens, two-factor authentication,
passkeys, profile updates and password changes. Those capabilities are outside the
accepted allow-list. Their generated migrations and unused actions were removed.
Sanctum remains in stateful session mode; the application does not issue personal
access tokens.

Fortify's automatic route registration was disabled with:

```php
Fortify::ignoreRoutes();
```

Only the accepted controllers were explicitly mounted in `routes/api.php` and
`routes/web.php`.

### Middleware and configuration

`bootstrap/app.php` now:

* enables Sanctum's stateful API middleware;
* applies central email canonicalisation;
* aliases the configurable open-registration middleware;
* sends unauthenticated HTML visitors to the Next.js login page.

Credentialed CORS is enabled only for configured origins. Its paths are:

```php
['api/*', 'sanctum/csrf-cookie']
```

The Compose API environment explicitly supplies:

```text
APP_URL
FRONTEND_URL
CORS_ALLOWED_ORIGINS
SANCTUM_STATEFUL_DOMAINS
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_COOKIE=rag-platform-session
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
AUTH_REGISTRATION_ENABLED
```

Rate limits are:

```text
login                 5/minute per canonical email and IP
registration          3/minute per IP
password reset link   3/minute per canonical email and IP
verification          6/minute
```

The first route-list attempt copied an option from an older Laravel CLI:

```bash
docker compose exec api php artisan route:list \
  --path=api \
  --columns=method,uri,name,middleware
```

Laravel 13 reported:

```text
The "--columns" option does not exist.
```

The supported verbose command was used instead:

```bash
docker compose exec api php artisan route:list --path=api -v
```

It showed only the eight authentication routes plus the protected platform proof
route. The signed verification route is registered in `routes/web.php` because an
email click must start the Laravel web session even when its referrer is Mailpit or an
email client.

### Fortify response customisation

Small response adapters provide the stable JSON contract:

```text
ApiLoginResponse
ApiRegisterResponse
GenericPasswordResetLinkResponse
VerifyEmailRedirectResponse
```

Successful verification redirects to:

```text
{FRONTEND_URL}/verify-email/result?status=verified
```

Password-reset notifications use Fortify's password broker and Laravel notification,
but their URL targets the Next.js reset form:

```text
{FRONTEND_URL}/reset-password?token=...&email=...
```

Verification notifications continue to generate Laravel temporary signed URLs.

### Add Mailpit

The current official Mailpit release was checked before pinning:

```text
axllent/mailpit:v1.30.0
```

The service is named `mailpit`, its web interface binds only to
`127.0.0.1:8025`, and Laravel connects internally to SMTP port `1025`.
Mailpit's documented readiness command backs the health check:

```yaml
test: [CMD, /mailpit, readyz]
```

Start the affected services:

```bash
docker compose up -d mailpit api web
```

Mailpit is local-only and ephemeral. Verification and reset messages are not sent to
an external provider.

### Container environment fault discovered by the live test

The first real registration returned `201`, but the user was absent from PostgreSQL
and Mailpit had zero messages:

```text
Mailpit messages: 0
```

Inspection showed that `php artisan serve` launched PHP's development-server child
with only `APP_ENV`. The child then loaded the scaffold `apps/api/.env`, silently
selecting SQLite and log mail instead of the Compose PostgreSQL and SMTP values.

The development image now launches PHP directly, preserving the full container
environment:

```dockerfile
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
```

The first direct-server correction used Laravel's framework `server.php` while the
working directory was still `/app`. It attempted to require `/app/index.php` and
failed. Using `public/index.php` as the router corrected the path.

That fault also exposed a weak health check: any response body, including a PHP fatal
page, counted as healthy. The health check now sends `Accept: application/json` and
requires the exact Laravel response:

```json
{"status":"up"}
```

Rebuild and recreate:

```bash
docker compose build api
docker compose up -d --force-recreate api web --wait --wait-timeout 180
```

### Test isolation correction

Compose environment variables also had precedence over ordinary PHPUnit XML values.
Before correction, the feature suite used development PostgreSQL and relied on test
transaction rollback.

Every PHPUnit environment entry now uses `force="true"`. Tests use in-memory SQLite,
array sessions, array mail and synchronous queues regardless of Compose values.

Isolation was proved by reading `users_id_seq`, running the suite, and reading it
again:

```bash
docker compose exec postgres psql \
  --username rag_platform \
  --dbname rag_platform \
  --tuples-only \
  --command "SELECT last_value FROM users_id_seq;"

make test-api format-check-api

docker compose exec postgres psql \
  --username rag_platform \
  --dbname rag_platform \
  --tuples-only \
  --command "SELECT last_value FROM users_id_seq;"
```

The value remained `9`.

### Signed-link middleware correction

The first live verification-link request returned `500`. The log showed that
`auth:sanctum` treated the direct email click as stateless because it did not carry a
first-party SPA `Origin` or `Referer`; Laravel then attempted to redirect through an
undefined local `login` route.

The signed verification endpoint was moved to the normal Laravel `web` middleware
group with `auth:web`, `signed` and throttling. It remains an authenticated Laravel
endpoint, starts the existing session regardless of email-client referrer, verifies
through Fortify and redirects to Next.js. A frontend login redirect is now configured
for unauthenticated HTML requests.

The corrected live result was:

```text
HTTP/1.1 302 Found
Location: http://localhost:3000/verify-email/result?status=verified
PostgreSQL email_verified_at set: true
```

### Backend feature tests

`tests/Feature/AuthenticationTest.php` covers:

* canonical registration and unique canonical email;
* registration enabled and disabled;
* the initial password rule;
* signed Laravel verification URL generation;
* login with a non-canonical email input;
* generic invalid-credential handling;
* current-user access before verification;
* logout;
* rejection of unverified platform access;
* acceptance of verified platform access;
* verification and redirect;
* verification resend;
* identical forgotten-password responses for known and unknown accounts;
* useful malformed-email validation;
* password reset, remember-token rotation and deletion of every database session;
* frontend-readable XSRF cookie and `HttpOnly` session configuration.

Run:

```bash
make test-api
make format-check-api
```

Result:

```text
16 tests passed
53 assertions
Laravel Pint passed 42 files
```

---

## Stage 6.3 — Implement Next.js Authentication UI

### Objective

Provide frontend registration, login, logout and protected-route behaviour.

### Status

Completed on 2026-07-26.

### Browser API client

`src/lib/api.ts` is the direct browser-to-Laravel client. For unsafe requests it:

1. calls `GET /sanctum/csrf-cookie`;
2. reads and URL-decodes `XSRF-TOKEN`;
3. sends `X-XSRF-TOKEN`;
4. sends credentials and requests JSON;
5. turns Laravel validation payloads into safe UI errors.

It never reads the `HttpOnly` session cookie.

### User experience

The default scaffold was replaced with:

```text
/
/login
/register
/forgot-password
/reset-password
/verify-email
/verify-email/result
/app
```

The account forms provide pending, success and error states with accessible status
regions. The registration and reset forms explain the password policy.

`/app` is a dynamic Server Component. It selectively forwards the incoming cookie to
Laravel's internal Compose URL and calls the protected platform endpoint:

```text
200 → render the workspace
401 → redirect to /login
403 → redirect to /verify-email
```

It supplies the configured frontend origin so Sanctum recognises the forwarded
first-party request. It does not make its own authorisation decision.

The HTTP contract documentation was corrected from a mandatory
Browser → Next.js → Laravel topology to direct browser-to-Laravel calls with an
optional selective rendering path.

### Web tests

Next.js 16.2.11's bundled documentation was read before implementation, including the
authentication, asynchronous `cookies()` and Vitest guides.

Install the documented test stack:

```bash
docker compose exec web npm install --save-dev \
  vitest \
  @vitejs/plugin-react \
  jsdom \
  @testing-library/react \
  @testing-library/dom \
  @testing-library/user-event \
  vite-tsconfig-paths
```

Current Vite reported that `vite-tsconfig-paths` is obsolete because native
`resolve.tsconfigPaths` support is available. It was removed:

```bash
docker compose exec web npm uninstall --save-dev vite-tsconfig-paths
```

The three client tests prove:

* unsafe calls bootstrap Sanctum CSRF state;
* the readable XSRF value is decoded and returned as a header;
* safe GET requests do not make a CSRF bootstrap call;
* Laravel's useful field validation is preferred over a generic message.

`make test-web` now runs `vitest run` instead of reporting that no suite exists.

```bash
make test-web lint-web typecheck-web
```

Result:

```text
3 Vitest tests passed
ESLint passed
TypeScript passed
```

### Production build corrections

The first manual build ran inside the development service with
`NODE_ENV=development`. Next.js warned about the non-production value and its error
page prerender failed inside the mixed development/production React runtime.

The first attempted Compose override used the unsupported long flag:

```text
unknown flag: --environment
```

The supported short flag produced a clean build:

```bash
docker compose stop web
docker compose run --rm --no-deps -e NODE_ENV=production web npm run build
docker compose up -d web --wait --wait-timeout 180
```

All nine application routes compiled. `/app`, `/reset-password` and the verification
result are dynamic; the remaining pages are statically rendered.

The actual standalone production image target was also built successfully:

```bash
docker build \
  --target production \
  --tag rag-platform-web:phase-6 \
  apps/web
```

This verifies the container artifact path intended for a later ECS/Fargate deployment;
it does not deploy anything to AWS.

Runtime smoke checks:

```bash
curl --fail --silent --show-error \
  --output /dev/null \
  --write-out 'home HTTP %{http_code}\n' \
  http://127.0.0.1:3000/

curl --silent --show-error \
  --output /dev/null \
  --write-out 'workspace HTTP %{http_code} redirect %{redirect_url}\n' \
  http://127.0.0.1:3000/app
```

Result:

```text
home HTTP 200
workspace HTTP 307 redirect http://127.0.0.1:3000/login
```

### Live end-to-end authentication proof

A real credentialed registration was sent through the host API after obtaining
Sanctum's CSRF cookie. It:

* returned `201`;
* persisted the canonical user in PostgreSQL;
* created a database session;
* delivered one verification email to Mailpit;
* produced a Laravel temporary signed verification URL;
* verified the database row;
* redirected to the Next.js success page.

The single integration-test user, its session and its Mailpit message were then
deleted. No external email or production system was used.

### Dependency audit

Adding test-only packages increased the unfiltered npm report to 12 high-severity
findings. The production-only check remains three high findings inherited through
Next.js 16.2.11:

```bash
docker compose exec web npm audit --omit=dev
```

The report identifies current `postcss` and `sharp` advisories. npm's proposed
`--force` resolution would downgrade Next.js to 9.3.3, a breaking and invalid fix, so
it was not applied. This remains a known upstream dependency risk to revisit when a
compatible Next.js release updates those transitive packages.

### Phase acceptance

* Laravel is the sole security authority.
* Open registration is configuration-controlled.
* Canonical email uniqueness is enforced.
* Login, logout, current user, reset and verification work.
* Password reset invalidates all sessions.
* Unverified accounts cannot use platform functionality.
* Mailpit captures local email.
* Next.js does not act as a mandatory BFF.
* Tests are isolated from development PostgreSQL.
* The production web build succeeds.
* All local services are healthy.

### Phase commit and tag

Phase 6 receives one atomic milestone after the full vertical slice is verified:

```bash
git add \
  .env.example \
  IMPLEMENTATION_GUIDE.md \
  README.md \
  apps/api \
  apps/web \
  compose.yaml \
  contracts/http/README.md \
  docs/adr \
  makefile

git commit -m "Complete Phase 6 authentication and identity"
git tag -a phase-6 -m "Authentication and identity"
```

The unrelated untracked `tasks.json` file remains excluded.

---

# Phase 7 — Multi-Tenancy

## Phase objective

Ensure every tenant-owned resource is isolated by design rather than filtered as an afterthought.

---

## Stage 7.1 — Define Tenant Model

### Objective

Choose the platform tenancy model and record its security invariants.

### Status

Completed on 2026-07-27.

### Decision

The Workspace model was accepted and recorded before any Phase 7 implementation
code in:

```text
docs/adr/0006-use-workspace-as-the-tenancy-and-isolation-boundary.md
```

A Workspace is the platform's tenant, collaboration and data-isolation boundary.
No organisation layer sits above it at this stage. Users remain global
identities (Phase 6) and may belong to multiple workspaces; the relationship is
a first-class `WorkspaceMembership` model, not an anonymous pivot, carrying one
of a fixed initial role set: `owner`, `admin`, `member`. Every workspace has
exactly one active owner membership at all times; `created_by_user_id` records
creation provenance only, not current ownership authority. Invitations are
explicitly deferred to a later session.

The relational tenancy model is pooled — one shared PostgreSQL database and
shared tables, with a mandatory non-nullable workspace foreign key on every
workspace-owned row. Tenant isolation is enforced through **defence in
depth**: workspace-scoped routes, the authenticated user, active-membership
validation, Laravel policies, explicit tenant-scoped queries, PostgreSQL
Row-Level Security and database constraints must each independently hold, not
any single mechanism alone. RLS is accepted as part of the architecture — not
a replacement for application-layer authorisation, which must remain correct
on its own even where RLS is disabled (e.g. some local development
configurations). Its implementation (policies, connection-context propagation,
a restricted non-superuser runtime database role, tests) requires a separately
scoped Phase 7 implementation session; the repository must not describe RLS as
active until then.

Workspace identity must propagate through every derived artefact and service
boundary: workspace-prefixed S3 object keys (server-controlled, not itself an
authorisation mechanism), SQS ingestion events, extracted documents, chunks and
vectors. Asynchronous workers must take workspace context from the message
itself, never from browser sessions or process-global state, and consumers
must validate that a referenced resource actually belongs to the workspace an
event names — no service may derive tenant identity implicitly, and tenant
identity crossing a service boundary is untrusted until the receiving service
validates it. Event contracts are expected to carry explicit version
information as they evolve. The Qdrant collection/sharding strategy remains
deferred to `R13-S01`; every vector record must still carry immutable
workspace identity regardless of the eventual physical layout.

Workspace configuration distinguishes platform-global catalogues (supported
embedding/generation providers and models) from per-workspace configuration
(a workspace's selected provider, model, retrieval configuration and future
credentials) — a fourth entity-classification category, alongside
platform-global, workspace-relationship and workspace-owned:
workspace-configurable.

Three independent audit layers are recorded: business audit (workspace and
membership lifecycle events), search/RAG audit (once retrieval exists), and
database audit (e.g. `pgAudit`, reserved for forensic use, not the primary
trail). Requests, events and downstream processing are intended to eventually
share a common correlation identifier for end-to-end traceability.

Registration (Phase 6) creates a global user identity only. Workspace creation
is explicit and atomic — workspace and owner membership are created together.
Workspace deletion is a lifecycle, not an immediate delete: it will
orchestrate cleanup across PostgreSQL, object storage, Qdrant and audit
records asynchronously; the concrete orchestration is deferred, but the shape
of deletion as a multi-system auditable lifecycle is accepted now.

The full set of agreed decisions, rejected alternatives and required security
invariants is recorded in ADR 0006 rather than duplicated here. ADR 0006 went
through two rounds of architecture review before acceptance — see the session
journal for what changed in each round.

### Session verification

This was an architecture-and-documentation-only session. No migrations,
models, middleware, policies, routes or frontend code were introduced. Verification
consisted of:

* inspecting `CLAUDE.md`, `CONTRIBUTING.md`, `PROJECT_ROADMAP.md`,
  `IMPLEMENTATION_GUIDE.md`, `tasks.json` and every existing file under
  `docs/adr/` before drafting, to preserve existing ADR numbering, format and
  terminology conventions;
* confirming `tasks.json` and `docs/rag-platform-tasks.json` are identical
  copies, so both stay in sync;
* checking the ADR against each item in the "Acceptance criteria" list below.

### Acceptance criteria

* Tenant terminology is consistent. — Met: "Workspace" used throughout ADR 0006.
* Ownership rules are documented. — Met: owner-membership and
  `created_by_user_id` provenance rules recorded.
* Membership and role rules are documented. — Met: `WorkspaceMembership`,
  fixed `owner`/`admin`/`member` roles.
* Cross-tenant access is explicitly forbidden. — Met: see ADR 0006 "Security
  invariants".
* Background processing carries tenant identity. — Met: async-worker and
  event-consumer propagation rules recorded.
* Vector metadata includes tenant identity. — Met: recorded as a requirement
  independent of the deferred Qdrant layout decision.
* Audit requirements are considered. — Met: business, search/RAG and database
  audit layers are distinguished, with a future shared correlation identifier
  recorded as an architectural intention.

ADR 0006 was reviewed twice (Ralph) before acceptance. Round one accepted
PostgreSQL RLS as a defence-in-depth layer (originally deferred), added the
workspace-configuration/platform-catalogue split, the three audit layers, and
reframed deletion as an asynchronous lifecycle. Round two was a polish pass:
it added the `workspace-configurable` entity-classification category, explicit
tenant-propagation and trust-boundary invariants, a correlation-ID statement,
an event-versioning consideration, a provider/model split within workspace
configuration, and a closing "correctness over convenience" principle. No
structural changes or renumbering occurred in either round.

### Commit boundary

git add docs/adr docs/journal tasks.json docs/rag-platform-tasks.json IMPLEMENTATION_GUIDE.md
git commit -m "Document multi-tenancy model"

---

## Stage 7.2 — Implement Workspaces and Memberships

### Objective

Create Workspace, WorkspaceMembership and role persistence in Laravel.

### Status

Completed on 2026-07-28.

### Scope and terminology

ADR 0006 settled `Workspace` as the tenant, collaboration and isolation boundary,
so the legacy planned `tenant` terminology was replaced by:

```text
workspaces
workspace_memberships
```

Invitations, controllers, routes, switching, policies, tenant middleware,
PostgreSQL RLS, ownership transfer, deletion orchestration, business-audit
implementation and downstream tenant propagation were explicitly outside this
bounded persistence session.

### Role model

A string-backed PHP enum defines the initial fixed roles:

```php
enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
}
```

The membership model casts `role` to `WorkspaceRole` and `joined_at` to a Laravel
datetime. The migration hardcodes the accepted strings rather than reading the
current PHP enum, so a future enum change cannot silently alter historical
clean-database behaviour.

### Relational schema

The `workspaces` table contains:

* an internal bigint primary key;
* a non-null, unique public UUID;
* name;
* a non-null, unique slug;
* `created_by_user_id` as provenance, referencing `users` with
  `ON DELETE RESTRICT`;
* timestamps.

The `workspace_memberships` table contains:

* an internal bigint primary key;
* `workspace_id`, referencing `workspaces` with `ON DELETE CASCADE`;
* `user_id`, referencing `users` with `ON DELETE RESTRICT`;
* a non-null role string with a database `CHECK` allowing only
  `owner`, `admin`, or `member`;
* non-null `joined_at`;
* timestamps.

Database integrity is protected by:

* a unique `(workspace_id, user_id)` constraint;
* a PostgreSQL-compatible partial unique index on `workspace_id` where
  `role = 'owner'`, enforcing at most one owner membership per workspace;
* unique indexes for workspace public UUID and slug;
* lookup indexes for provenance, membership-by-user, and workspace-plus-role;
* the foreign-key delete behaviour described above.

The database constraint enforces **at most** one owner. Atomic creation ensures a
new workspace starts with exactly one. Ownership transfer and last-owner removal
remain deferred transactional workflows.

### Eloquent models and factories

`WorkspaceMembership` is a normal first-class Eloquent model rather than an
anonymous pivot. Relationships added are:

* `Workspace::creator()`;
* `Workspace::memberships()`;
* `Workspace::members()`;
* `WorkspaceMembership::workspace()`;
* `WorkspaceMembership::user()`;
* `User::createdWorkspaces()`;
* `User::workspaceMemberships()`;
* `User::workspaces()`.

The convenience member/workspace relationships use `hasManyThrough`, retaining the
first-class membership model as the authoritative relationship record.

Factories were added for both models. `WorkspaceMembershipFactory` supplies explicit
`owner()`, `admin()` and `member()` states. `WorkspaceFactory::withOwner()` provides
a valid aggregate convenience state, while the bare factory remains useful for
database-boundary tests.

### Transactional workspace creation

The existing repository convention for state-changing application operations is a
focused Action, so the implementation was placed at:

```text
apps/api/app/Actions/Workspaces/CreateWorkspace.php
```

`CreateWorkspace::handle(User $creator, string $name)`:

1. begins a database transaction;
2. generates the public UUID server-side;
3. trims the validated name and generates a server-side slug;
4. creates the workspace with creation provenance;
5. creates the creator's owner membership;
6. returns the workspace with creator and membership data loaded.

If owner-membership creation throws, the transaction rolls back the workspace.
Repeated names receive deterministic numeric slug suffixes (`name`, `name-2`, and
so on), with the database unique index remaining the final integrity boundary.
`created_by_user_id` is never consulted as ownership authority.

The `Workspace` model rejects public-ID changes made through Eloquent after
creation. The database unique constraint independently protects identity
uniqueness.

### Focused tests

The new `WorkspacePersistenceTest` contains 14 tests / 44 assertions covering:

* successful atomic creation and rollback on membership failure;
* the creator becoming owner;
* enum and datetime casts;
* owner/admin/member factory states;
* every model relationship;
* duplicate-membership rejection;
* one user in multiple workspaces;
* multiple users in one workspace;
* database-level invalid-role rejection;
* database-level second-owner rejection;
* public UUID generation, uniqueness and Eloquent immutability;
* restrictive and cascading foreign-key behaviour;
* required schema and index creation.

The first focused run reported 12 passes and one failure in the combined foreign-key
test. PostgreSQL correctly aborts a transaction after a deliberate foreign-key
violation, so subsequent assertions in that same test transaction were invalid.
The restricted-user and cascading-workspace behaviours were separated into
independent tests. No application implementation change was required.

Final focused result:

```text
14 tests passed
44 assertions
```

Full Laravel result:

```text
30 tests passed
97 assertions
```

### Commands and verification

The local services were started, the development migration applied, and the focused
and full API checks run:

```bash
make up
make migrate
docker compose exec -T api php artisan test --filter=WorkspacePersistenceTest
make format-check-api
make lint-api
make test-api
```

Pint passed all 51 PHP files.

An isolated temporary PostgreSQL database named
`rag_platform_r07_s02_verify` proved clean migration behaviour:

```bash
docker compose exec -T postgres createdb \
  --username rag_platform \
  rag_platform_r07_s02_verify

docker compose exec -T \
  -e DB_DATABASE=rag_platform_r07_s02_verify \
  api php artisan migrate --force

docker compose exec -T \
  -e DB_DATABASE=rag_platform_r07_s02_verify \
  api php artisan migrate:rollback --force

docker compose exec -T \
  -e DB_DATABASE=rag_platform_r07_s02_verify \
  api php artisan migrate --force

docker compose exec -T postgres dropdb \
  --force \
  --username rag_platform \
  rag_platform_r07_s02_verify
```

PostgreSQL catalogue inspection confirmed the expected primary keys, foreign keys,
role check, non-null constraints, unique membership constraint, UUID and slug
uniqueness, supporting indexes and partial owner index. The full migration set
applied from zero, rolled back fully, left both workspace tables absent, and applied
again successfully. The temporary database was then removed.

After human approval, the complete repository boundary gate ran:

```bash
make format-check lint typecheck test ps
```

Result:

```text
Web:  ESLint passed, TypeScript passed, 3 Vitest tests passed
API:  Pint passed, 30 Laravel tests / 97 assertions passed
AI:   Ruff format and lint passed, MyPy passed, 1 Pytest test passed
All six Compose services healthy
```

The platform was stopped afterward without deleting persistent data:

```bash
make down
```

### Acceptance criteria

* A creator can atomically create a workspace and owner membership. — Met.
* Users can belong to multiple workspaces. — Met.
* Different users can belong to one workspace. — Met.
* Roles are represented by a fixed application enum and enforced by the database.
  — Met.
* Database constraints protect membership, role, owner and identity integrity.
  — Met.
* The membership is a first-class model with tested relationships. — Met.
* Clean PostgreSQL migrations and rollback are repeatable. — Met.
* Controllers, tenant selection, policies and broader tenant-boundary enforcement.
  — Deferred by the explicit R07-S02 scope.
* PostgreSQL RLS and restricted runtime roles. — Accepted by ADR 0006 but not
  implemented or claimed active; requires a separately scoped Phase 7 session.

### Commit boundary

```bash
git add \
  IMPLEMENTATION_GUIDE.md \
  apps/api \
  docs/journal/2026-07-28-r07-s02-implement-workspaces-and-memberships.md \
  docs/rag-platform-tasks.json \
  tasks.json

git commit -m "Implement workspaces and memberships" \
  -m "Implements ADR-0006."

git tag -a phase-7-s02 -m "Workspaces and memberships"
```

---

## Stage 7.3 — Add Workspace-Aware Web Experience

### Objective

Allow authenticated users to view and switch between workspaces to which they
have already been assigned, with development seed data available so the
workspace-aware interface can be exercised locally.

### Status

Completed, verified and approved on 2026-07-28.

### Acceptance criteria

- The active workspace is visible.
- Workspace switching is supported where applicable.
- Workspace-specific data refreshes after switching.
- Users cannot select or access workspaces to which they have not been assigned.
- Workspace membership is enforced by the API; browser-supplied workspace
  identifiers are never trusted without server-side verification.
- Development seed data creates at least two workspaces and suitable
  memberships so workspace visibility and switching can be verified locally.
- Seed data uses synthetic users and must not contain real credentials or
  personal information.
- Running the development seeder is repeatable and does not create duplicate
  workspaces or memberships.
- Granular workspace management (creation, membership management, ownership
  transfer and role administration) is outside the scope of this stage.

### Implementation

Laravel now exposes two verified, session-authenticated workspace endpoints:

```text
GET /api/workspaces
GET /api/workspaces/{workspacePublicId}
```

The list query starts from the authenticated user's memberships and returns only
assigned workspaces. The detail query combines the authenticated user and requested
immutable public UUID in one membership-scoped lookup. An unassigned or unknown UUID
therefore returns `404`; the API does not reveal whether another workspace exists.

`WorkspaceController` remains an HTTP coordinator. Read logic lives in
`ListWorkspacesForUser` and `FindWorkspaceForUser`, and `WorkspaceResource` defines
the deliberate response fields: public UUID, name, slug and the user's role. Internal
workspace and membership IDs are not exposed.

The Next.js application uses explicit workspace URLs:

```text
/app/workspaces/{workspacePublicId}
```

`/app` loads the server-verified workspace list and redirects to the first assigned
workspace. The workspace page fetches both the assigned list and the requested
workspace from Laravel with `cache: "no-store"`. Switching is normal URL navigation,
so the selected workspace summary and role are fetched again. The browser does not
store or authorise a hidden global current-workspace value.

Users without memberships receive an explicit no-workspace state. Users who request
an inaccessible public UUID receive the generic Next.js 404 page.

### Development fixtures

The development seeder should create a small, deterministic workspace fixture
for local testing. At minimum:

- one synthetic test user belongs to two workspaces;
- the user has different roles where useful for interface testing;
- each workspace has exactly one owner;
- all users and workspace names are synthetic;
- the existing `CreateWorkspace` action is reused where practical so seeded
  workspaces obey the same atomic owner-creation invariant as production code.

`DevelopmentWorkspaceSeeder` implements that fixture with two synthetic users,
`Atlas Research` and `Beacon Operations`. The primary user is the Atlas owner and a
Beacon admin; the secondary user is the Beacon owner and an Atlas member. Each
workspace is created through `CreateWorkspace` when absent, and the additional
memberships use `updateOrCreate`.

Running the normal command twice:

```bash
make seed
make seed
```

produced the same fixture:

```text
fixture users:                 2
fixture workspaces:            2
fixture memberships:           4
workspaces with one owner:     2
```

### Tests added

`WorkspaceAccessTest` covers:

- using isolated in-memory SQLite rather than development PostgreSQL;
- listing only assigned workspaces, including the membership role;
- resolving an assigned workspace by immutable public UUID;
- returning `404` for an inaccessible workspace;
- rejecting unauthenticated and unverified requests;
- running the development seeder twice without duplicate users, workspaces or
  memberships; and
- preserving exactly one owner in each seeded workspace.

`WorkspaceSwitcher.test.tsx` verifies that the active workspace is marked and each
assigned workspace links to its public-UUID route.

Focused results:

```text
WorkspaceAccessTest: 6 tests / 22 assertions passed
WorkspaceSwitcher:   1 test passed
```

### Manual browser verification

The synthetic user signed in through the normal browser flow. The first assigned
workspace rendered as `Atlas Research` with role `owner`. Selecting
`Beacon Operations` changed the route, active marker, heading and role to `admin`.
A direct request for an unassigned valid UUID rendered the generic 404 page.

### Commands and verification

```bash
make up
make format-api
make format-web
docker compose exec -T api php artisan test --filter=WorkspaceAccessTest
docker compose exec -T web npx vitest run \
  src/components/WorkspaceSwitcher.test.tsx
make typecheck-web
make seed
make seed
make format-check-api
make lint-api
make test-api
make format-check-web
make lint-web
make typecheck-web
make test-web
make format-check lint typecheck test ps
make down
```

Final repository result:

```text
Web:  ESLint and TypeScript passed; 4 Vitest tests passed
API:  Pint passed; 36 Laravel tests / 119 assertions passed
AI:   Ruff format/lint and MyPy passed; 1 Pytest test passed
All six Compose services healthy
```

The platform was stopped afterward without deleting persistent volumes.

### Problems and corrections

The first focused seeder assertion expected raw role strings, but Eloquent correctly
returned `WorkspaceRole` enum instances through the relationship cast. The test was
corrected to compare enum values.

The first switcher test assumed whitespace between adjacent accessible text nodes.
Each link received an explicit accessible label containing workspace name and role,
which improved assistive-technology output and provided a stable test contract.

During browser verification, the complete Laravel suite removed the development
fixture rows. Investigation proved that Docker's process environment took precedence
over the `<env>` values in `phpunit.xml`, so `RefreshDatabase` was operating against
PostgreSQL rather than in-memory SQLite. The PHPUnit values were changed to forced
`<server>` entries, which Laravel's environment reader checks first. A regression
test now asserts the `sqlite`/`:memory:` connection, and a PostgreSQL marker remained
present before and after both the focused and complete API suites.

### Acceptance result

- The active workspace is visible. — Met.
- Workspace switching is supported. — Met.
- Workspace-specific data refreshes after switching. — Met.
- Users cannot select or access unassigned workspaces. — Met.
- Laravel verifies membership for every requested workspace public UUID. — Met.
- Two-workspace synthetic development data is available. — Met.
- Repeated development seeding does not duplicate fixture rows. — Met.
- Workspace creation, membership management, ownership transfer and role
  administration. — Deliberately deferred by scope.

### Commit boundary

```bash
git add \
  IMPLEMENTATION_GUIDE.md \
  apps/api \
  apps/web \
  docs/journal/2026-07-28-r07-s03-add-workspace-aware-web-experience.md \
  tasks.json

git commit -m "Add workspace-aware web experience" \
  -m "Implements ADR-0006."

git tag -a phase-7 -m "Complete Phase 7 multi-tenancy foundation"
```

---

# Phase 8 — Document Domain and Storage

## Phase objective

Model tenant-owned documents and store source files safely before asynchronous ingestion begins.

---

## Stage 8.1 — Define Document Lifecycle

### Objective

Define document states, ownership, metadata and failure behaviour.

### Status

Completed on 2026-07-28.

### Decision

The Document lifecycle and storage-separation model was accepted and recorded
before any Phase 8 implementation code in:

```text
docs/adr/0007-define-the-document-lifecycle-and-storage-model.md
```

A Document is a durable, workspace-owned domain record — distinct from the
uploaded file it describes — and belongs to exactly one workspace, never an
individual user, consistent with ADR 0006's workspace-owned entity
classification. Three layers hold non-interchangeable responsibility:
PostgreSQL is authoritative for identity, ownership and lifecycle state;
S3-compatible object storage holds the authoritative source content, never
trusted as the source of truth for lifecycle; and searchable/vector
representations (Qdrant, from Phase 14 onward) are a derived, disposable,
rebuildable projection, not itself authoritative.

The accepted lifecycle is an explicit state machine, not boolean flags:

```text
UPLOADING → UPLOADED → QUEUED → PROCESSING → INDEXED
PROCESSING → FAILED
<any non-DELETED state> → DELETING → DELETED
```

`UPLOADED` means the authoritative source content is confirmed present in
object storage, regardless of how it originated — deliberately worded so a
future connector-sourced document (Google Drive, SharePoint, a fetched URL)
fits the same states without a parallel lifecycle. `UPLOADED → QUEUED` occurs
only once ingestion-event publication actually succeeds. `INDEXED` requires
the complete searchable representation to be available for workspace-filtered
retrieval; a partial write does not qualify.

Domain-level retry (`FAILED → QUEUED`) is always an explicit, authorised
action, never automatic; it preserves the Document's identity, not a history
of prior attempts. Automatic, unbounded retry loops are rejected at every
layer; bounding transport/queue redelivery is Phase 9's responsibility, not
defined here. Deletion is asynchronous and reachable from any non-deleted
state; `DELETING`/`DELETED` are cancellation barriers with no valid
transition back to an active state. `DELETED` may retain the relational row
for reconciliation and auditability, with retention duration and any hard
purge deferred to a later data-retention decision.

Every upload is currently treated as an independent Document; true
versioning is intentionally deferred until a real product requirement exists.

The full set of agreed decisions, rejected alternatives and rationale is
recorded in ADR 0007 rather than duplicated here. ADR 0007 went through three
rounds of refinement (an initial architecture review, then a corrections pass,
then a single terminology amendment to the `UPLOADED` state) before
acceptance — see the session journal for what changed in each round.

### Session verification

This was an architecture-and-documentation-only session. No migrations,
models, middleware, policies, routes or frontend code were introduced.
Verification consisted of:

* inspecting `CLAUDE.md`, `CONTRIBUTING.md`, `PROJECT_ROADMAP.md`,
  `IMPLEMENTATION_GUIDE.md`, `tasks.json` and the existing `docs/adr/` files
  (including ADR 0006) before drafting, to preserve numbering, format,
  terminology and cross-ADR consistency;
* checking each ADR 0006 cross-reference in ADR 0007 against ADR 0006's
  actual text before keeping or removing it, rather than assuming the
  comparison held;
* checking the ADR's final form against each Stage 8.1 acceptance criterion
  below.

### Acceptance criteria

* Document states are explicit. — Met: the `UPLOADING → UPLOADED → QUEUED →
  PROCESSING → INDEXED` state machine, plus `FAILED` and
  `DELETING`/`DELETED`, replaces the placeholder state list above.
* Valid transitions are documented. — Met: including the deletion
  cancellation-barrier invariant and the explicit-only domain retry
  transition.
* Tenant ownership is mandatory. — Met: a Document belongs to exactly one
  workspace, consistent with ADR 0006.
* Original filename and media type are preserved safely. — Met at the
  conceptual level (recorded as relational metadata); exact columns are
  Stage 8.2 work.
* Storage keys do not trust user-provided paths. — Met at the conceptual
  level: object storage is addressed by a server-controlled key; exact key
  construction is Stage 8.2/8.3 work.
* Failure and retry states are defined. — Met: `FAILED`, and explicit,
  bounded (non-automatic) domain-level retry.
* Deletion semantics are defined. — Met: asynchronous, reachable from any
  non-deleted state, with a cancellation-barrier invariant and deferred
  retention/purge policy.

### Commit boundary

git add docs/adr docs/journal tasks.json docs/rag-platform-tasks.json IMPLEMENTATION_GUIDE.md
git commit -m "Document document lifecycle"

---

## Stage 8.2 — Implement Document Persistence

### Objective

Persist the Document domain model defined by ADR-0007.

### Status

Completed, reviewed and verified on 2026-07-28.

### Engineering rationale

The Document is the platform's authoritative system of record for document
identity, workspace ownership and lifecycle, as defined by ADR-0007.

This stage establishes only the durable persistence model. Upload,
processing, object storage integration and retrieval are implemented in
later stages.

When deciding whether a field belongs on the Document model, apply the
following principle:

> Persist only information that remains true regardless of how the document
> is processed.

Intrinsic properties of the source content—such as ownership, lifecycle,
storage identity, checksum and source metadata—belong on the Document.

Information discovered or produced during processing—such as page count,
detected language, chunk count, extracted text or embedding metadata—is
processing metadata and should not be persisted on the Document unless a
future ADR explicitly defines it as part of the core domain model.

### Acceptance criteria

* Document records are tenant-owned.
* Lifecycle state is represented using the ADR-0007 state machine.
* Workspace ownership is enforced.
* Provenance is recorded separately from ownership.
* Storage keys are generated safely.
* Cross-tenant access is rejected.
* API responses do not expose sensitive storage details.
* Feature tests cover the document domain model and relationships.

### Implementation

* Added the string-backed `DocumentStatus` enum with every lifecycle state
  accepted by ADR-0007: `uploading`, `uploaded`, `queued`, `processing`,
  `indexed`, `failed`, `deleting` and `deleted`.
* Added the `documents` table with an internal primary key, immutable unique
  public UUID, mandatory workspace ownership, separate creator provenance,
  lifecycle status, intrinsic source metadata, a server-generated unique
  storage key, nullable failure diagnostics and timestamps.
* Added foreign keys that restrict deletion of a referenced workspace or
  creator, plus indexes for workspace/status queries and creator provenance.
  PostgreSQL check constraints reject negative sizes and require non-blank
  failure category and message values whenever status is `failed`.
* Added the first-class `Document` model and relationships:
  `Workspace::documents()`, `Document::workspace()`,
  `Document::createdBy()` and `User::createdDocuments()`. Status and size use
  enum and integer casts respectively.
* Added `CreateDocument` as the bounded application action. It creates only
  the relational `uploading` record and generates both public identity and a
  tenant/document-scoped storage key server-side. It does not upload bytes,
  publish events or advance lifecycle state.
* Added `FindDocumentForWorkspace`, which resolves a public document ID
  through the owning workspace relationship so a document in another
  workspace fails closed.
* Added `DocumentResource` to establish the safe public representation
  without exposing storage keys, internal foreign keys or failure internals.
  No controller or route was introduced in this persistence-only stage.
* Added a factory with states for every accepted lifecycle value. No document
  seeder was added: seeding an active document without authoritative source
  content would create a state that contradicts ADR-0007.

### Commands executed

```bash
make up
make format-api
make migrate
docker compose exec -T api php artisan test tests/Feature/DocumentPersistenceTest.php
docker compose exec -T api php artisan test tests/Unit/DocumentStatusTest.php
make format-check-api lint-api test-api
make format-check lint typecheck test ps
git diff --check
```

A temporary PostgreSQL database named `rag_platform_r08_s02_verify` was also
created, migrated from zero, inspected for the expected columns, indexes,
foreign keys and check constraints, rolled back to confirm that `documents`
was removed cleanly, migrated again, and dropped. This verified the
PostgreSQL-specific constraints that SQLite cannot faithfully exercise.

### Verification evidence

* Focused document feature tests: 17 passed (45 assertions).
* Focused lifecycle enum unit test: 1 passed (1 assertion).
* Full Laravel suite: 54 passed (165 assertions).
* Web suite: 4 passed across 2 test files.
* AI suite: 1 passed.
* Laravel Pint, ESLint, Ruff formatting/linting, TypeScript and mypy all
  passed.
* All Docker Compose services reported healthy.
* Clean PostgreSQL migration, rollback and re-migration passed.

### Problems and corrections

* The requested `docs/IMPLEMENTATION_GUIDE.md` path does not exist; the
  repository's canonical root `IMPLEMENTATION_GUIDE.md` was used.
* Review of the first implementation pass added model-level immutability
  guards and tests for public identity, workspace ownership and creator
  provenance. Database uniqueness and foreign-key constraints remain the
  final persistence backstop.
* Lifecycle transition operations remain deferred. This stage represents the
  accepted states but deliberately does not invent upload, processing, retry
  or deletion workflows.

### Commit boundary

git add apps/api IMPLEMENTATION_GUIDE.md tasks.json docs/journal
git commit -m "Implement document persistence" \
  -m "Implements ADR-0007."

---

## Stage 8.3 — Implement Direct Upload Flow

### Objective

Allow the browser to upload documents safely to S3-compatible storage.

### Status

Completed on 2026-07-28 with automated and live LocalStack verification.
Human visual verification of the multi-file interface passed, and the Phase 8
commit/tag boundary was approved.

### Architecture alignment

The implementation follows ADR-0007's document lifecycle and ADR-0004's
accepted local AWS boundary. An implementation brief referred to MinIO, which
conflicted with ADR-0004 and the existing LocalStack infrastructure. Work
stopped for a human decision before code changed. The agreed resolution was:

* retain LocalStack 4.14 as the local S3 implementation;
* treat MinIO wording as generic S3-compatible storage requirements;
* retain the standard AWS SDK/Flysystem adapter so production uses real S3
  through configuration;
* allow every authenticated, verified active workspace member (`owner`,
  `admin` or `member`) to upload; and
* keep granular document permissions and PostgreSQL RLS out of this stage.

No ADR was added or changed because these choices preserve the accepted
architecture rather than establishing a new one.

### Upload flow

1. The client fetches the workspace-authorised upload configuration.
2. Laravel validates active workspace membership using the scoped workspace
   query and `WorkspacePolicy`.
3. The browser performs lightweight validation; Laravel remains authoritative
   for filename, declared MIME type, extension, non-zero size and the
   environment-backed 25 MB default limit.
4. `InitializeDocumentUpload` creates an independent `UPLOADING` Document and
   obtains a ten-minute presigned PUT request in one database transaction.
   Signing is local computation; a signing failure therefore rolls the new row
   back before the client receives an unusable upload.
5. The server-controlled key has the form
   `workspaces/{workspace-public-id}/documents/{document-public-id}/source.{ext}`.
   The original filename is display metadata and never participates in the
   key.
6. The browser PUTs the bytes directly to LocalStack/S3 using
   `XMLHttpRequest`, reporting real byte progress.
7. After the PUT succeeds, the UI displays `VERIFYING` and calls the explicit
   completion endpoint.
8. Laravel resolves the Document inside the authorised workspace, checks
   `DocumentPolicy`, performs an S3 HEAD-equivalent existence/size check and
   transitions only `UPLOADING → UPLOADED`.
9. The completion action uses a database row lock before the state change.
   Repeated completion of an already-`UPLOADED` Document is safe and does not
   create another record.

The database transaction does not pretend to include the browser's external
PUT. A failed or abandoned PUT leaves its Document `UPLOADING`, as ADR-0007
requires. Cleanup policy for abandoned uploads remains deferred.

### API operations

```text
GET  /api/workspaces/{workspacePublicId}/documents/uploads/configuration
POST /api/workspaces/{workspacePublicId}/documents/uploads
POST /api/workspaces/{workspacePublicId}/documents/{documentPublicId}/uploads/complete
```

All routes require `auth:sanctum` and `verified`. Workspace and Document
lookups fail closed with `404` across tenant boundaries. Normal Document
resources omit the storage key, bucket, disk, credentials and failure
internals.

### Upload configuration

`apps/api/config/documents.php` centrally defines:

* PDF, DOCX, DOC, RTF, TXT and Markdown extension/MIME pairs;
* `DOCUMENT_MAX_UPLOAD_MB` (default `25`);
* `DOCUMENT_PRESIGNED_URL_LIFETIME_SECONDS` (default `600`);
* `DOCUMENT_UPLOAD_CONCURRENCY` (default `3`);
* the internal verification disk and browser-signing disk.

The configuration endpoint supplies the safe validation/concurrency values to
the client so PHP and React do not maintain competing product limits.
`.env.example`, `apps/api/.env.example` and Compose expose the new
environment contract.

### LocalStack endpoint and CORS configuration

Laravel needs two views of the same S3 service:

```text
Internal verification endpoint: http://localstack:4566
Browser signing endpoint:       http://localhost:4566
```

The `s3` disk performs server-side object checks using Docker DNS. The
`s3_uploads` disk only creates signed, browser-reachable PUT requests using
`AWS_UPLOAD_ENDPOINT`. Both share the same AWS credentials, region and bucket.
Production omits local endpoint overrides and uses normal AWS endpoint
resolution.

The idempotent LocalStack ready hook now applies bucket CORS for the configured
frontend origin, PUT/HEAD and the `content-type` request header.
`make aws-status` verifies this configuration in addition to the existing
bucket, queues and redrive policy.

### Frontend behaviour

`DocumentUploadPanel` provides:

* multiple selection and drag-and-drop;
* waiting-list removal and duplicate-selection prevention;
* authoritative configuration loaded from Laravel;
* a three-upload default concurrency ceiling;
* independent `WAITING`, `INITIALISING`, `UPLOADING`, `VERIFYING`, `COMPLETE`
  and `FAILED` UI states;
* real byte-based progress plus accessible textual and ARIA progress;
* batch progress;
* independent failure messages and retry; and
* no `COMPLETE` state until Laravel returns an `uploaded` Document.

These UI states are not new persisted lifecycle values. A retry performs a new
initialisation; any abandoned prior attempt remains `UPLOADING` for the future
cleanup policy defined outside this stage.

### Implementation files

Laravel additions include focused initialisation/completion Actions, a Form
Request, workspace/document policies, `DocumentObjectStorage`, safe upload
exceptions, `DocumentUploadController`, central configuration and feature
tests. The existing `CreateDocument` action now accepts a validated optional
extension for the server-generated key, and the Document model protects its
storage identity and source metadata from mutation after creation.

Next.js additions include the interactive upload panel, upload API/client
helpers, bounded concurrency and XMLHttpRequest transport, styling and focused
tests. LocalStack provisioning/verification, Compose and environment examples
were updated without introducing a new service.

### Commands executed

```bash
make up
make seed
make format-api
make format-web
make typecheck-web
docker compose exec -T api php artisan test \
  tests/Feature/DocumentUploadWorkflowTest.php \
  tests/Feature/DocumentPersistenceTest.php
docker compose exec -T web npx vitest run \
  src/lib/document-upload.test.ts \
  src/components/DocumentUploadPanel.test.tsx
make aws-status
docker compose exec -T api php artisan route:list --path=documents
docker compose config --quiet
git diff --check
make format-check lint typecheck test aws-status ps
```

A disposable authenticated HTTP smoke test also:

1. requested a browser-reachable presigned URL from Laravel;
2. passed the LocalStack CORS preflight;
3. PUT a Markdown fixture directly to `localhost:4566`;
4. called the completion endpoint;
5. confirmed the returned status was `uploaded`;
6. confirmed the resource contained only the seven safe public fields; and
7. removed the synthetic object and Document row afterward.

### Verification evidence

* Focused Laravel document tests: 31 passed (129 assertions).
* Full Laravel suite: 68 passed (249 assertions).
* Focused frontend upload tests: 6 passed.
* Full web suite: 10 passed across 4 test files.
* AI suite: 1 passed.
* Pint, ESLint, Ruff formatting/linting, TypeScript and mypy passed.
* Compose configuration validation passed.
* `make aws-status` passed with upload CORS, bucket, queues and redrive policy.
* All six Compose services reported healthy.
* The live signed-URL/CORS/PUT/HEAD/completion smoke test passed.
* No queue job was pushed and no Document advanced to `QUEUED`.

### Problems and corrections

* The brief named `docs/IMPLEMENTATION_GUIDE.md`; the canonical root
  `IMPLEMENTATION_GUIDE.md` was used.
* MinIO conflicted with ADR-0004. Implementation paused until retaining
  LocalStack was explicitly accepted.
* Role-specific upload permissions were undefined. Implementation paused
  until upload access for all active members was explicitly accepted.
* The first backend test used an event-wide “nothing dispatched” assertion.
  Laravel correctly dispatches authentication, policy, request and Eloquent
  events, so the test was corrected to assert the relevant invariant: no
  ingestion queue work is pushed.
* The first focused component run exposed missing React Testing Library
  cleanup between tests; explicit cleanup was added.
* The first command-line smoke login omitted the frontend `Origin` and then
  reused a stale CSRF value. It was corrected to match the real client:
  frontend Origin plus a fresh Sanctum CSRF cookie before each unsafe API
  operation.
* The in-app automation browser rejected localhost under its URL security
  policy. No bypass or alternate browser automation was attempted. Automated
  UI tests and the live HTTP/storage smoke passed; the visible multi-file
  interaction remains a human acceptance check.

### Acceptance criteria

* Uploads are authorised by Laravel. — Met through authentication,
  verification, membership-scoped resolution and policies.
* Presigned requests are time-limited. — Met; configurable 600-second default.
* File type and size rules are enforced. — Met centrally and tested.
* Storage keys are tenant-scoped. — Met with opaque workspace/document UUID
  paths and validated extensions.
* The browser does not receive permanent AWS credentials. — Met; only the
  short-lived signed PUT request and required headers are returned.
* Interrupted uploads do not become ready documents. — Met; they remain
  `UPLOADING`.
* Upload completion is verified. — Met through server-side existence and exact
  size checks.
* LocalStack and real S3-compatible configuration share the same application
  flow. — Met through standard S3 adapters and endpoint configuration.
* Multi-file visual behaviour. — Automated component coverage and human
  browser acceptance passed.

### Commit boundary

```bash
git add \
  .env.example \
  IMPLEMENTATION_GUIDE.md \
  apps/api \
  apps/web \
  compose.yaml \
  docs/journal \
  infrastructure/localstack \
  scripts/localstack \
  tasks.json

git commit -m "Implement document upload workflow" \
  -m "Implements ADR-0004 and ADR-0007."
```

---

# Phase 9 — Event-Driven Ingestion

## Phase objective

Decouple document upload from document processing through a durable,
tenant-aware and versioned ingestion workflow.

At the end of this phase, an uploaded Document can move through:

```text
UPLOADED
↓
QUEUED
↓
PROCESSING
```

Laravel remains authoritative for Document identity, workspace ownership and
lifecycle state.

Python consumes ingestion requests asynchronously but does not directly modify
Laravel-owned database tables.

This phase establishes orchestration and delivery guarantees only. It does not
perform text extraction, chunking, embedding generation or vector indexing.

---

# Stage 9.1 — Define the Ingestion Architecture and Event Contract

## Objective

Define the service boundaries, delivery semantics and versioned contract used
to request document ingestion.

## Status

Completed and approved on 2026-07-28.

## Actual implementation record

### Objective completed

The version 1 `document.ingestion.requested` contract is now canonical under
`contracts/events/document-ingestion-requested/` and is validated from that
single location by both Laravel and Python.

The contract records the immutable public Workspace and Document identifiers,
storage identity, source properties, a stable logical event identifier and a
separate correlation identifier. It is strict and explicitly versioned:
unknown fields and unsupported versions fail closed.

### Architecture alignment

ADR-0008 accepts the Transactional Outbox Pattern for reliable ingestion
publication. During implementation, ADR-0007's original description of
`QUEUED` was found to conflict with that newer decision. After human approval,
ADR-0007 received a narrow supersession notice without rewriting its original
historical text:

* `QUEUED` means the lifecycle transition and durable publication intent have
  committed together;
* successful SQS publication occurs asynchronously afterward; and
* no additional lifecycle state was introduced.

The contract implementation does not publish, consume or process an event.

### Changes made

* Added the Draft 2020-12 JSON Schema, valid example, four deliberately
  invalid fixtures and human-readable contract documentation.
* Added `opis/json-schema` as a Laravel development dependency.
* Added `jsonschema` with non-GPL format validators and `types-jsonschema` as
  Python development dependencies.
* Mounted the repository `contracts/` directory read-only at `/contracts` in
  both the API and AI containers.
* Added a focused Laravel test that validates the canonical example and
  asserts that every shared invalid fixture fails for its intended JSON Schema
  keyword.
* Added focused pytest coverage of the same files, including explicit
  assertions for the version, additional-property and required-field
  failures.
* Kept the schema, examples and invalid fixtures out of both application
  directories so neither language owns a private copy.

### Commands executed

```bash
docker compose exec -T api composer require --dev \
  'opis/json-schema:^2.6' --no-interaction
docker compose exec -T ai uv add --dev \
  'jsonschema[format-nongpl]>=4.26.0'
docker compose up -d --force-recreate ai api web
make format-api format-ai
docker compose exec -T api php artisan test \
  tests/Unit/DocumentIngestionRequestedContractTest.php
docker compose exec -T ai uv run pytest \
  tests/test_document_ingestion_requested_contract.py -v
make format-check lint typecheck test ps
docker compose exec -T ai uv add --dev \
  'types-jsonschema>=4.26.0'
make typecheck test ps
make format-check lint typecheck test ps
docker compose exec -T api composer validate --strict
docker compose exec -T ai uv lock --check
docker compose config --quiet
git diff --check
jq empty tasks.json
jq empty contracts/events/document-ingestion-requested/*.json
jq empty contracts/events/document-ingestion-requested/fixtures/*.json
```

### Verification evidence

* Focused Laravel contract suite: 6 passed (10 assertions).
* Focused Python contract suite: 9 passed.
* Full Laravel suite: 74 passed (259 assertions).
* Full web suite: 10 passed across 4 files.
* Full AI suite: 10 passed.
* Pint, ESLint, Ruff formatting/linting, TypeScript and mypy passed.
* Composer manifest/lock validation and uv lock validation passed.
* Compose configuration validation, service health and `git diff --check`
  passed.
* JSON parsing passed for the tracker, schema, example and all fixtures.

The valid example passed in both languages. The same four invalid fixtures
failed in both languages for the intended reasons:

* missing `workspace_id`: `required`;
* `event_version: 2`: `const`, because version 1 is required; and
* unexpected `presigned_url`: `additionalProperties`; and
* `byte_size: 0`: `minimum`, because accepted uploads must contain at least
  one byte.

### Problems and corrections

The first full verification run reached mypy after all runtime and formatting
checks had passed, but mypy reported that `jsonschema` does not provide the
typing metadata required by this repository's strict test checking.
`types-jsonschema` was added as a development dependency, after which mypy and
the complete repository gate passed.

The shared contract directory was not visible inside the existing service
containers because each service mounted only its application directory.
Read-only `/contracts` mounts were added rather than duplicating contract
files.

Final review found that the schema's original `minimum: 0` for `byte_size`
was weaker than the established upload invariant. The browser rejects empty
files, Laravel requires `size_bytes >= 1`, and completion verifies that the
stored object has that positive declared size. Because an ingestion request
can only follow an accepted upload, no legitimate producer path needs zero.
The schema now requires `minimum: 1`, and a shared negative fixture proves
that both validators reject a zero-byte event.

### Commit boundary

The human developer approved the R09-S01 implementation and commit boundary.
Stage 9.2 may now begin.

## Engineering rationale

SQS provides at-least-once delivery rather than exactly-once delivery.

Consumers must therefore assume that messages can be:

- delayed;
- retried;
- delivered more than once;
- delivered after related lifecycle state has changed.

The ingestion architecture must be designed around:

- versioned contracts;
- idempotent producers and consumers;
- explicit workspace context;
- correlation identifiers;
- unsupported-version handling;
- retry and dead-letter behaviour;
- clear ownership of lifecycle state.

Laravel owns the Document aggregate and its lifecycle.

The Python ingestion service may request lifecycle transitions through an
authenticated internal application boundary, but it must not update Laravel's
PostgreSQL tables directly.

A queue message represents a request to process a Document. It is not the
authoritative record that the Document exists or remains eligible for
processing.

## Planned location

```text
contracts/events/document-ingestion-requested/
```

Suggested structure:

```text
contracts/events/document-ingestion-requested/
├── v1.schema.json
├── v1.example.json
└── README.md
```

## Contract requirements

The version 1 contract should include:

- event identifier;
- event type;
- event version;
- occurred-at timestamp;
- workspace identifier;
- document identifier;
- source-storage bucket;
- source-storage object key;
- media type;
- byte size;
- correlation identifier.

Use public or transport-safe identifiers where appropriate.

Do not include:

- storage credentials;
- presigned URLs;
- user-facing secrets;
- complete user records;
- unbounded arbitrary metadata.

The event identifier identifies one logical ingestion request.

The document identifier identifies the durable Document being processed.

The correlation identifier connects upload, publication, consumption and
processing logs without replacing either identifier.

## Delivery semantics

The architecture must explicitly document that:

- delivery is at least once;
- message ordering is not guaranteed unless deliberately introduced later;
- consumers must be idempotent;
- unsupported event versions must fail safely;
- malformed events must not be processed;
- messages are acknowledged only after the responsibility represented by the
  event has completed durably;
- repeated terminal failures are routed to a dead-letter queue.

## Expected changes

- Versioned JSON Schema for the event.
- Example event payload.
- Human-readable contract documentation.
- Shared contract-validation fixtures.
- Laravel contract-validation tests.
- Python contract-validation tests.
- Architectural documentation describing producer and consumer ownership.

Create a new ADR only if the existing ADRs do not already settle:

- transactional publication strategy;
- Laravel and Python lifecycle ownership;
- internal service authentication;
- retry and dead-letter semantics.

Do not hide unresolved architecture inside implementation code.

## Verification

Verify that:

- valid example payloads pass JSON Schema validation;
- missing required fields fail validation;
- unknown additional fields follow the documented schema policy;
- unsupported versions are detectable;
- Laravel and Python validate against the same canonical schema;
- workspace and correlation identifiers are required;
- no secret or presigned URL appears in the contract.

## Acceptance criteria

- The ingestion event is explicitly versioned.
- Required fields and their meanings are documented.
- Workspace identity is present.
- Document identity is present.
- Event and correlation identifiers are distinct.
- Delivery semantics are documented as at least once.
- Consumers can identify and reject unsupported versions.
- Valid and invalid fixture payloads exist.
- Laravel and Python validate against the same contract.
- No credentials, secrets or presigned URLs are included.
- Laravel remains authoritative for Document lifecycle state.

## Commit boundary

```bash
git add contracts docs apps/api apps/ai
git commit -m "Define document ingestion event contract"
```

---

# Stage 9.2 — Publish Ingestion Requests Reliably

## Objective

Reliably request ingestion after an uploaded Document is accepted for
processing.

## Status

Implemented, verified and approved on 2026-07-28.

## Actual implementation record

### Objective completed

Laravel now accepts an authenticated, verified, workspace-scoped request to
ingest an `UPLOADED` Document. The Document transition to `QUEUED` and one
immutable, contract-valid outbox event commit in the same PostgreSQL
transaction.

A separate long-running `publisher` process claims durable outbox events,
validates their payloads against the canonical Stage 9.1 schema, publishes raw
language-neutral JSON to SQS and records the confirmed publication.

### Accepted lifecycle and HTTP behaviour

The endpoint is:

```text
POST /api/workspaces/{workspace}/documents/{document}/ingestion-requests
```

It always returns the current safe `DocumentResource` with `202 Accepted`
when the ingestion request is accepted or is already in progress:

* `UPLOADED` — transition to `QUEUED` and create one outbox event;
* `QUEUED` — return idempotently without creating an event; and
* `PROCESSING` — return idempotently without creating an event.

`UPLOADING`, `INDEXED`, `FAILED`, `DELETING` and `DELETED` return
`409 Conflict`. Explicit retry from `FAILED` remains future work.

A valid caller-supplied `X-Correlation-ID` UUID is preserved. If the header is
absent or invalid, Laravel generates a UUID. The resolved value is returned in
the response header and, for a new request, is persisted in the outbox payload
and structured publication logs.

### Idempotency invariant

Idempotency is enforced by the authoritative lifecycle transition, not by an
endpoint-local idempotency flag or a client-supplied key.

`RequestDocumentIngestion` locks the Document row with `FOR UPDATE` inside the
same transaction that creates the outbox event. Only the transaction that
observes the locked Document in `UPLOADED` may create an event and transition
it to `QUEUED`. A concurrent or repeated request subsequently observes
`QUEUED` or `PROCESSING` and returns the current resource without another
event.

No permanent unique constraint on Document identity was added to the outbox.
Such a constraint would incorrectly prevent a future explicit
`FAILED → QUEUED` domain retry from creating a new logical ingestion attempt.
The current invariant is instead the locked, one-way
`UPLOADED → QUEUED` transition. Every logical outbox event still has a unique,
immutable `event_id`.

### Outbox persistence

The `outbox_events` table stores:

* unique logical event identity, event type and version;
* immutable public Workspace and Document identifiers;
* correlation identity and the canonical JSON payload;
* occurrence, publication and terminal-failure timestamps;
* lease time and claim token;
* attempt count, next-attempt time and a sanitised last error; and
* normal record timestamps.

The table deliberately stores public transport identifiers rather than
foreign keys to the Laravel aggregates. Publication evidence must remain
diagnosable without introducing a foreign-key deletion barrier around future
Document deletion orchestration.

PostgreSQL constraints enforce a positive event version, unique event and
claim identifiers, mutually exclusive published/failed terminal states and
paired claim timestamp/token values. The model prevents mutation of event
identity or payload after creation.

### Publisher behaviour

`php artisan ingestion:publish` continuously polls in the dedicated Compose
`publisher` service. `--once` processes at most one configured batch and is
exposed as:

```bash
make publish-ingestion
```

Each event is claimed in its own short PostgreSQL transaction. PostgreSQL uses
`FOR UPDATE SKIP LOCKED`; the claim is persisted as a token plus lease
timestamp before network I/O begins. This allows multiple publisher instances
without holding a database transaction open during SQS calls. An expired
lease is reclaimable after a publisher crash. A late publisher can only
update a record while it still owns the matching claim token.

The publisher:

* validates every claimed payload against the shared canonical schema;
* sends the stored payload unchanged through Laravel's SQS adapter;
* marks it published only after SQS returns a transport message identifier;
* keeps the original `event_id` through every retry;
* sets deterministic contract failures aside using `failed_at`;
* releases transient SQS failures for capped exponential-backoff retry; and
* logs event, correlation, Workspace, Document and transport identifiers
  without logging payloads or credentials.

Transient publication attempts are retried durably rather than discarded
after an arbitrary ceiling. The configurable delay starts at five seconds
and is capped at five minutes, preventing a tight outage loop. Consumer
redrive and the DLQ remain the separate Stage 9.3 responsibility.

The long-running command waits safely if the outbox migration has not yet run,
which allows normal `make bootstrap` ordering without a crash loop.

### Files and operational changes

* Added the outbox migration, model and factory.
* Added the request action, thin controller, policy method and route.
* Added the payload builder and canonical runtime contract validator.
* Moved `opis/json-schema` from development-only to production Composer
  dependencies because publication performs runtime validation.
* Added the publisher interface, SQS adapter, claiming/publication action and
  Artisan command.
* Added configurable batch, polling, lease and retry settings to both example
  environment files.
* Added the dedicated Compose `publisher` process.
* Added `make publish-ingestion` and documented the background service in the
  root README.
* Reused ADR-0004's existing LocalStack ingestion queue, DLQ and redrive
  provisioning without introducing another emulator or queue.

### Commands executed

```bash
docker compose exec -T api composer require \
  'opis/json-schema:^2.6' --no-interaction
make format-api
docker compose exec -T api php artisan test \
  tests/Feature/DocumentIngestionPublicationTest.php
docker compose config --quiet
docker compose up -d publisher
docker compose exec -T api php artisan migrate --force
docker compose logs --tail 80 publisher
docker compose exec -T api php artisan test
docker compose exec -T postgres createdb \
  --username rag_platform rag_platform_r09_s02_test
docker compose exec -T -e DB_DATABASE=rag_platform_r09_s02_test \
  api php artisan migrate:fresh --force
docker compose exec -T postgres psql \
  --username rag_platform \
  --dbname rag_platform_r09_s02_test \
  --command "SELECT conname FROM pg_constraint WHERE conrelid = 'outbox_events'::regclass ORDER BY conname"
docker compose exec -T postgres dropdb \
  --username rag_platform rag_platform_r09_s02_test
docker compose restart publisher
make publish-ingestion
make format-check lint typecheck test ps
make aws-status
docker compose exec -T api composer validate --strict
docker compose exec -T api composer install \
  --no-dev --dry-run --no-interaction
docker compose exec -T api php artisan route:list \
  --path=ingestion-requests
docker compose exec -T api php artisan list ingestion
docker compose config --quiet
git diff --check
jq empty tasks.json
```

### Verification evidence

* Focused R09-S02 Laravel suite: 21 passed (105 assertions).
* Full Laravel suite: 95 passed (364 assertions).
* Full web suite: 10 passed across 4 files.
* Full AI suite: 10 passed.
* Pint, ESLint, Ruff formatting/linting, TypeScript and mypy passed.
* Composer validation and a production `--no-dev` installation dry run
  passed.
* Compose validation passed and all seven processes were running; the six
  HTTP/infrastructure services remained healthy.
* LocalStack verification passed for the S3 bucket, ingestion queue, DLQ and
  redrive policy (`maxReceiveCount: 3`).
* A disposable PostgreSQL database migrated cleanly from empty and exposed
  the expected outbox constraints. It was removed afterward.
* The endpoint and Artisan command were registered.
* `git diff --check` and tracker JSON validation passed.

A disposable live smoke created an `UPLOADED` Document and requested
ingestion. PostgreSQL committed the Document as `QUEUED` with one outbox
record. The dedicated publisher delivered the exact version 1 payload to
LocalStack SQS, preserved the supplied correlation identifier, recorded one
attempt and marked the event published. Structured logs contained the
logical and transport identifiers. The synthetic Document, outbox event and
queue message were removed afterward.

### Problems and corrections

The runtime publisher now validates payloads, so keeping
`opis/json-schema` in `require-dev` would have broken production images.
Composer moved the already-pinned 2.6.0 package into `require`; no version
upgrade was introduced.

The first test formatting run exposed a chained PHP `match` expression that
required parentheses. The syntax was corrected before any test execution.

The initial publisher implementation claimed a whole batch before performing
network I/O. It was tightened to claim one event immediately before each send,
so later records in a batch do not spend their lease waiting behind earlier
SQS calls.

The publisher can start before migrations during a clean bootstrap. It now
waits for the outbox table in continuous mode while the explicit `--once`
command fails clearly if migrations are missing.

### Commit and tag boundary

The implementation was approved for the R09-S02 stage commit and annotated
`phase-9-s02` tag. Stage 9.3 is the next bounded session.

## Engineering rationale

Updating PostgreSQL and publishing directly to SQS are two independent
operations.

A naïve workflow creates a dual-write failure:

```text
Document becomes QUEUED
↓
SQS publication fails
↓
Document is never processed
```

Reversing the operation order is also unsafe:

```text
SQS message is published
↓
Database transaction fails
↓
Worker receives a request for state that was never committed
```

Use a transactional outbox so the lifecycle transition and durable publication
intent are recorded within the same PostgreSQL transaction.

Conceptual flow:

```text
Document: UPLOADED
↓
Database transaction
├── transition Document to QUEUED
└── create outbox record
↓
Commit
↓
Outbox publisher sends event to SQS
↓
Outbox record marked as published
```

The outbox guarantees durable publication intent. It does not provide
exactly-once delivery, so downstream processing must remain idempotent.

## Required behaviour

Provide an explicit operation that requests ingestion for an eligible
Document.

The operation must:

1. authenticate the user;
2. resolve the workspace through active membership;
3. authorise access to the Document;
4. ensure the Document belongs to the workspace;
5. require the expected `UPLOADED` state;
6. transition the Document to `QUEUED`;
7. create one durable outbox event within the same database transaction;
8. return the updated public Document representation.

Do not publish directly to SQS from inside the request transaction.

An outbox publisher must:

- read unpublished events;
- validate payloads against the canonical contract;
- publish them to LocalStack SQS locally and AWS SQS in production;
- mark successful publications durably;
- retry transient publication failures;
- avoid uncontrolled duplicate publication;
- retain enough failure information for diagnosis.

## Idempotency

Repeated ingestion requests must not create uncontrolled duplicate work.

Where the Document is already `QUEUED` or `PROCESSING`, the operation should
either:

- return the current state idempotently; or
- reject the request with a documented domain conflict.

Choose one behaviour and test it consistently.

Use a database constraint or equivalent invariant to prevent multiple active
outbox requests for the same logical ingestion attempt.

Do not claim exactly-once publication.

## Correlation

Propagate a correlation identifier across:

- the initiating HTTP request;
- the outbox record;
- the SQS event;
- structured publication logs.

The event identifier must remain stable for retries of the same outbox event.

A publication retry must not generate a new logical event identifier.

## Local infrastructure

Use the architecture established by ADR-0004:

- LocalStack SQS for local development;
- AWS SQS in production;
- the existing AWS SDK and configuration conventions;
- a configured ingestion queue;
- a configured dead-letter queue;
- an explicit redrive policy.

Do not introduce MinIO or an additional queue emulator.

## Expected changes

Likely implementation areas include:

- outbox persistence model and migration;
- outbox event factory or serializer;
- ingestion-request application action;
- lifecycle transition enforcement;
- SQS publisher;
- publisher command or worker process;
- LocalStack queue provisioning;
- dead-letter queue configuration;
- structured logging;
- Laravel feature and integration tests.

Keep controllers thin and keep AWS-specific publication details outside the
Document model.

## Verification

Verify the following scenarios:

1. An `UPLOADED` Document becomes `QUEUED`.
2. The lifecycle transition and outbox record commit atomically.
3. A failed database transaction creates neither change.
4. An SQS outage leaves a retryable unpublished outbox record.
5. A later publisher run successfully publishes that record.
6. The event matches the versioned contract.
7. The event contains the correct workspace and Document identifiers.
8. Duplicate requests do not create uncontrolled duplicate outbox records.
9. Repeated publisher execution does not intentionally create new logical
   events.
10. LocalStack receives the event.
11. No Python processing is invoked synchronously.
12. The HTTP request does not wait for document processing.

## Acceptance criteria

- Laravel records publication intent durably.
- `UPLOADED → QUEUED` follows ADR-0007.
- The lifecycle transition and outbox creation are atomic.
- Published events conform to the shared contract.
- Publication is workspace-aware.
- Correlation identifiers are propagated.
- Transient queue failures remain retryable.
- Duplicate requests are controlled.
- LocalStack SQS and its dead-letter queue are provisioned.
- Publication behaviour is covered by automated tests.
- No parsing, chunking, embedding or vector work occurs.

## Commit boundary

```bash
git add apps/api infrastructure scripts compose.yaml docs
git commit -m "Publish document ingestion requests"
```

---

# Stage 9.3 — Consume and Claim Ingestion Requests

## Objective

Create a dedicated Python worker that receives, validates and idempotently
claims document-ingestion requests.

## Status

Implemented, verified and approved on 2026-07-29 under ADR-0008 and ADR-0009.
Ready for the stage commit and completion tags.

## Actual implementation record

### What was implemented

Laravel now exposes one narrowly scoped internal endpoint:

```text
POST /api/internal/ingestion/events/{eventId}/claim
```

The endpoint is protected by dedicated ingestion-worker HMAC middleware. It
validates the untouched request bytes, request timestamp, Key ID, signature,
HTTP method, path, body digest and logical event identifier using ADR-0009's
canonical string. Query strings are rejected. Authentication failures return
the same generic response while safe operational reasons remain available in
Laravel logs.

The configured key ring accepts multiple enabled Key IDs concurrently for
rotation. Secrets must be strictly Base64 encoded and decode to at least 32
bytes. The active Python signing key is separate from Laravel's application
and user authentication configuration.

Laravel independently validates the exact event body against the shared
Document Ingestion Requested version 1 schema. It then executes
`ClaimDocumentIngestion` in one database transaction:

1. lock the Workspace-scoped Document;
2. inspect the durable logical-event claim;
3. insert the claim for a new eligible event;
4. move the Document atomically from `QUEUED` to `PROCESSING`; and
5. return `claimed`, `already_claimed`, `stale_event` or
   `ineligible_state` without moving lifecycle state backwards.

The first-class `ingestion_event_claims` record stores the globally unique
logical event ID, public Workspace and Document identities, correlation ID,
claim timestamp and SHA-256 digest of the canonical event payload. The digest
means the same event ID cannot later be presented with modified content and
mistaken for an identical delivery. Database uniqueness makes idempotency
durable across Python and Laravel process restarts.

The Python service now has a dedicated `python -m app.worker` process,
separate from FastAPI. It long-polls SQS, parses and validates each event
against the repository's canonical shared schema, signs the raw request,
calls Laravel's authoritative claim endpoint and deletes only messages with a
safe terminal outcome:

* `claimed`, `already_claimed` and `stale_event` are acknowledged;
* malformed, unsupported, unknown, ineligible and identity-reuse outcomes are
  left for the existing SQS redrive policy;
* authentication, throttling, server and network failures remain
  unacknowledged for retry; and
* unexpected responses fail closed.

Structured JSON logs carry logical event, correlation, Workspace, Document,
SQS transport-message and receive-count context without request bodies,
signatures, credentials or document content. `SIGTERM` and `SIGINT` stop new
receives and allow the bounded current claim to finish. `--once` processes at
most one receive batch.

Compose gained a dedicated `worker` service and the Makefile gained
`consume-ingestion`. The root, Laravel and Python example environments
document the local key ring, active signing key and worker polling settings.
`httpx` and `jsonschema` are runtime Python dependencies because the deployed
worker needs both.

No heartbeat was added. The current worker performs only a bounded HTTP claim;
its 30-second SQS visibility timeout exceeds the 10-second internal HTTP
timeout. A later stage that performs extraction or other long-running work
must reassess and, where necessary, extend message visibility.

### Commands executed

The implementation was formatted and verified with the repository commands:

```bash
make format
make format-check
make lint
make typecheck
make test
make ps
make aws-status
```

Focused and supporting checks included:

```bash
docker compose exec api php artisan test tests/Feature/DocumentIngestionClaimTest.php
docker compose exec ai uv run pytest tests/test_ingestion_signing.py \
  tests/test_ingestion_claim_client.py tests/test_ingestion_sqs.py \
  tests/test_ingestion_worker.py tests/test_worker_entrypoint.py
docker compose exec api composer validate --strict
docker compose exec ai uv sync --locked --no-dev --dry-run
docker compose config --quiet
docker compose exec api php artisan route:list --path=api/internal/ingestion
git diff --check
```

A disposable PostgreSQL database was created, migrated from empty, inspected
for the claim table's constraints and indexes, and dropped. LocalStack queue
and DLQ state was inspected with `awslocal`.

### Verification evidence

* Focused Laravel claim suite: 23 tests passed with 127 assertions.
* Full Laravel suite: 118 tests passed with 491 assertions.
* Focused Python worker suite: 32 tests passed.
* Full Python suite: 42 tests passed.
* Full Next.js suite: 10 tests passed.
* Pint, ESLint, Ruff, TypeScript and mypy checks passed.
* Strict Composer validation and the production-only Python dependency dry
  run passed.
* Compose configuration, all container health checks, LocalStack resources,
  the internal route and whitespace checks passed.
* A clean database migrated successfully and exposed the expected unique
  logical-event constraint, payload-digest check and tenant/document index.

The live LocalStack acceptance exercised the full path:

```text
UPLOADED Document
→ transactional ingestion request and outbox
→ QUEUED
→ publisher
→ LocalStack SQS
→ Python contract validation and HMAC request
→ Laravel durable claim
→ PROCESSING
→ SQS acknowledgement
```

Republishing the same logical event under a different SQS transport message
returned `already_claimed`, retained one durable claim and was acknowledged.
A deliberately unavailable Laravel endpoint left the message unacknowledged;
the normal worker later received and safely acknowledged it. A malformed
message remained unacknowledged on three receives and reached the configured
DLQ. Correlation context appeared in publisher, Python and Laravel logs.
Graceful worker termination was also observed. No Document became `INDEXED`.

All synthetic Document, outbox and claim rows were removed after the
acceptance run, and the source queue and DLQ were purged.

### Problems and corrections

The runtime worker imports `httpx` and `jsonschema`; both were moved from
development-only dependencies to the production dependency set.

The durable record was strengthened with the exact canonical payload digest.
Without it, reuse of an existing logical event ID with altered event content
could appear to be an ordinary duplicate.

Known local credentials exist only in example and Compose-local
configuration. Application defaults do not silently enable that identity, so
a deployed worker or Laravel API fails closed when secrets management has not
supplied an HMAC secret.

The worker's error classification was made explicit so deterministic poison
messages are not deleted prematurely and transient infrastructure failures
are not misclassified as permanent contract failures.

Final review found that the `ineligible_state` client test used the exception
response shape even though Laravel returns that outcome through the normal
`data` response. The fixture now mirrors the real wire contract. Review also
found that structurally malformed SQS receive entries were ignored silently.
They remain unacknowledged as before, but now emit a safe warning containing
only transport diagnostics. A regression test covers that behaviour.

### Commit boundary

After human review, stage only the R09-S03 implementation, documentation and
tracker changes. Do not include unrelated local files. The proposed commit is:

```text
Add document ingestion worker

Implements ADR-0008 and ADR-0009.
```

Create the annotated `phase-9-s03` stage tag after the commit. Because this
stage also completes the Phase 9 acceptance workflow, create the annotated
`phase-9` phase tag at the same commit.

## Agreed bounded implementation brief

Implement only SQS consumption, canonical event validation and the durable
claim that moves a Document from `QUEUED` to `PROCESSING`. This stage must not
extract text, read source objects for processing, chunk content, create
embeddings, write vectors or transition a Document to `INDEXED` or `FAILED`.

### Laravel internal claim boundary

Add the internal endpoint:

```text
POST /api/internal/ingestion/events/{eventId}/claim
```

The request body is the exact canonical Document Ingestion Requested v1 event.
The path event ID, body `event_id` and signed event-ID header must agree.
Laravel validates the shared event schema independently rather than trusting
the worker's validation.

Protect only this endpoint with the ADR-0009 HMAC middleware. It does not use
Sanctum or CSRF authentication and is not added to the user-facing route
group. The implementation must:

* resolve an enabled Key ID from the configured ingestion-worker key ring;
* reject absent, malformed, unknown, disabled or expired signatures with one
  generic authentication response;
* calculate the digest from the untouched request bytes;
* use the exact ADR-0009 string-to-sign and constant-time comparison;
* enforce the configurable five-minute timestamp window;
* reject query strings and inconsistent path/header/body event identifiers;
* avoid logging secrets, signatures or request bodies; and
* expose safe Key ID, event and correlation context in structured logs.

Add a first-class ingestion-event claim record with a globally unique logical
`event_id`, Workspace and Document public identifiers, correlation identifier
and claim timestamp. Public identifiers preserve the event evidence and tenant
context. The exact migration must have reviewable constraints and indexes and
must not grant Python database access.

A focused Laravel action performs the claim in one PostgreSQL transaction:

1. lock the Workspace-scoped Document row;
2. inspect the durable event claim;
3. for a new event and `QUEUED` Document, insert the claim and transition the
   Document to `PROCESSING`;
4. for the same event, Workspace and Document, return the existing successful
   claim idempotently; and
5. reject identity reuse, backwards transitions and ineligible or stale
   events without changing lifecycle state.

Return a small machine-readable response with these initial semantics:

* `200` with `claimed` when the claim and transition commit;
* `200` with `already_claimed` for the identical durable event claim;
* `409` with `stale_event` when the Document is already in a later lifecycle
  state without a matching claim; and
* `409` with `ineligible_state` when the Document is in an earlier or otherwise
  impossible state for this event.

The worker acknowledges `claimed`, `already_claimed` and `stale_event`.
`ineligible_state`, an unknown Workspace or Document, invalid contract data and
event-identity reuse are poison outcomes left for the SQS redrive policy. No
response may move the Document backwards.

### Python ingestion worker

Add a dedicated worker entry point in `apps/ai`, separate from the FastAPI
application process, and a separate Compose `worker` service using the
existing AI image.

The worker must:

1. resolve the ingestion queue URL through the configured LocalStack/AWS
   adapter;
2. long-poll SQS in bounded batches;
3. parse the message body as JSON;
4. validate it against the canonical shared v1 schema;
5. reject unsupported versions without guessing compatibility;
6. attach the logical event, correlation, Workspace, Document, SQS message
   and receive-count context to logs;
7. sign the exact internal request according to ADR-0009 using its configured
   active Key ID;
8. request Laravel's authoritative claim;
9. delete the SQS message only after a new claim, an identical existing claim
   or a safe stale-event response; and
10. leave retryable and poison messages unacknowledged so SQS visibility and
    the existing redrive policy govern retry and DLQ delivery.

Move `httpx` and JSON Schema validation into Python runtime dependencies
because the production worker requires both. Keep boto3 as the SQS adapter.

Initial worker configuration must include:

* queue name or URL, AWS region and optional local endpoint;
* long-poll duration, visibility timeout and batch size;
* internal Laravel base URL and bounded HTTP timeout;
* active HMAC Key ID and secret;
* signature clock-skew agreement; and
* shutdown polling interval or equivalent interruptible-wait behaviour.

Because this stage performs only one bounded internal HTTP claim, configure
the SQS visibility timeout to exceed the HTTP timeout and normal claim
duration. Do not add a heartbeat loop yet. Long-running extraction begins in
a later stage and must introduce or confirm visibility extension before doing
work that can exceed the timeout.

On `SIGTERM` or `SIGINT`, stop receiving new messages, allow the current
bounded claim to finish and do not acknowledge an unfinished message. A
`--once` mode must process at most one receive batch for deterministic tests
and manual acceptance.

### Outcome classification

Use explicit worker outcomes:

* valid new or identical durable claim — acknowledge;
* Laravel-declared `stale_event` — acknowledge without processing;
* Laravel-declared `ineligible_state`, unknown domain identity or event-ID
  identity reuse — do not acknowledge; repeated receipt must reach the DLQ;
* malformed JSON, schema failure or unsupported version — do not acknowledge;
  repeated receipt must reach the configured DLQ;
* missing/invalid HMAC configuration or Laravel `401`/`403` — do not
  acknowledge and log a safe operational error;
* network error, timeout, `429` or Laravel `5xx` — do not acknowledge and
  retry after visibility expiry; and
* an unexpected Laravel response — fail closed and do not acknowledge.

No outcome in this stage marks a Document `INDEXED` or broadly changes it to
`FAILED`.

### Required tests

Laravel tests must cover:

* every required signature component;
* valid authentication;
* invalid signature, unknown Key ID and stale/future timestamp rejection;
* body, path, method and event-ID tamper rejection;
* constant-time comparison through the framework implementation choice;
* overlapping old/new keys during rotation;
* canonical contract validation;
* atomic `QUEUED → PROCESSING` plus claim insertion;
* rollback if either write fails;
* identical-event idempotency;
* event-ID identity mismatch rejection;
* stale and ineligible lifecycle handling;
* Workspace isolation; and
* clean migration behaviour and database constraints.

Python tests must cover:

* canonical contract loading and validation;
* deterministic ADR-0009 signing;
* SQS envelope parsing and receive metadata;
* success, duplicate, stale, poison and transient response classification;
* acknowledgement only for safe terminal outcomes;
* no acknowledgement on interruption or failure;
* stable logical event identity across duplicate transport messages;
* graceful shutdown; and
* `--once` operation.

LocalStack acceptance must demonstrate:

1. a valid published event is received by the dedicated worker;
2. Python and Laravel validate the same shared contract;
3. the signed internal request is accepted;
4. Laravel commits the event claim and `PROCESSING` transition atomically;
5. the worker then deletes the SQS message;
6. redelivery of the same logical event does not create another claim;
7. a transient Laravel failure leaves the message available for retry;
8. a malformed or unsupported event reaches the existing DLQ after the
   configured receive threshold;
9. correlation context is visible in both services; and
10. no Document becomes `INDEXED`.

### Expected implementation areas

Expected changes are bounded to:

* the new Laravel migration, claim model/action, internal controller,
  HMAC verifier/middleware, configuration, route and focused tests;
* the Python worker, SQS and internal-API adapters, settings, runtime
  dependencies and focused tests;
* the dedicated Compose worker process and safe example environment values;
* minimal Make/README operational commands;
* Stage 9.3's factual implementation record and session journal; and
* tracker evidence after verification.

ADR-0009 is the architecture authority for authentication. If implementation
requires another principal, permission model, general authentication
framework, Python database access, a different lifecycle transition or a
different idempotency authority, stop for architecture review.

## Engineering rationale

The Python worker is a separate process from the FastAPI HTTP application.

Its first responsibility is reliable orchestration:

```text
SQS message
↓
Validate contract
↓
Check supported version
↓
Establish correlation context
↓
Claim Document for processing
↓
QUEUED → PROCESSING
```

Laravel remains authoritative for the lifecycle transition.

The Python service must not connect directly to Laravel's application database
to update the Document record. Use an authenticated internal API or another
explicitly approved application boundary.

At-least-once delivery means the same event may be received more than once.
Duplicate delivery must not cause duplicate processing or an invalid lifecycle
transition.

This stage ends after the ingestion request has been claimed durably and the
Document has entered `PROCESSING`.

The Document may remain in `PROCESSING` until the extraction and indexing
phases are implemented.

Do not transition a Document to `INDEXED` merely to demonstrate queue success.

## Worker process

Implement a dedicated worker entry point separate from the HTTP server.

The worker must have explicit configuration for:

- queue URL or name;
- AWS region;
- local AWS endpoint;
- long-poll duration;
- visibility timeout;
- maximum receive count;
- dead-letter queue;
- processing heartbeat or visibility-extension strategy where required;
- shutdown behaviour;
- internal API credentials or identity.

The process must support graceful shutdown without acknowledging unfinished
work.

## Message handling

For each received message:

1. parse the SQS envelope;
2. parse the event payload;
3. validate against the canonical JSON Schema;
4. reject unsupported event versions safely;
5. attach event and correlation identifiers to structured logs;
6. perform the idempotency check;
7. request the authoritative `QUEUED → PROCESSING` transition;
8. acknowledge the SQS message only after the claim succeeds durably.

Classify failures deliberately.

### Permanent failures

Examples include:

- malformed JSON;
- contract-validation failure;
- unsupported event version;
- impossible or permanently invalid identifiers.

These must not retry forever. Allow the configured SQS redrive policy to route
them to the dead-letter queue, or use a documented equivalent strategy.

### Transient failures

Examples include:

- temporary Laravel API unavailability;
- network timeout;
- temporary database failure;
- temporary AWS failure.

These should remain unacknowledged and be retried after the visibility timeout.

### Duplicate or stale messages

Examples include:

- the same event has already been claimed;
- the Document is already `PROCESSING`;
- the Document has reached a later valid state;
- the Document was deleted or cancelled according to ADR-0007.

Handle these idempotently according to the authoritative lifecycle response.

Do not restart completed work blindly.

## Idempotency

Persist or otherwise durably enforce processing of event identifiers.

Idempotency must survive worker restarts.

An in-memory set is insufficient.

The implementation should distinguish between:

- duplicate delivery of the same event;
- a deliberate future retry represented by a new event;
- a stale event for a Document whose lifecycle has advanced.

Do not rely solely on SQS message identifiers because a logical event may be
republished using a different transport message identifier.

## Lifecycle reporting

The worker must be able to request:

```text
QUEUED → PROCESSING
```

through the Laravel-owned lifecycle boundary.

Do not implement:

```text
PROCESSING → INDEXED
```

until indexing has actually completed in a later phase.

For failures occurring before the processing claim succeeds, leave the
Document in its authoritative existing state and allow retry or dead-letter
handling.

Do not introduce broad `FAILED` transitions without distinguishing between:

- event-delivery failure;
- inability to claim processing;
- failure during actual document processing.

Actual processing failure semantics belong with the stage that performs that
processing, unless an ADR explicitly defines them earlier.

## Observability

Structured worker logs should include:

- event identifier;
- event version;
- correlation identifier;
- workspace identifier;
- document identifier;
- SQS message identifier;
- receive count;
- processing outcome;
- retry or acknowledgement decision.

Do not log:

- credentials;
- full document content;
- presigned URLs;
- sensitive source data.

## Expected changes

Likely implementation areas include:

- dedicated Python worker entry point;
- SQS polling adapter;
- contract-validation adapter;
- idempotency persistence;
- Laravel internal ingestion-control endpoint;
- service-to-service authentication;
- lifecycle claim action;
- worker container or Compose process;
- health or readiness strategy;
- structured logging configuration;
- unit, contract and LocalStack integration tests.

Do not expose the internal ingestion-control endpoint as an ordinary
user-facing API.

If service-to-service authentication is not already architecturally settled,
stop and create or amend the appropriate ADR before implementing it.

## Verification

Verify the following scenarios:

1. The worker receives a valid LocalStack SQS event.
2. The worker validates it against the shared schema.
3. The worker requests `QUEUED → PROCESSING`.
4. Laravel authoritatively performs the transition.
5. The message is acknowledged only after the durable claim succeeds.
6. A transient internal API failure causes retry.
7. A malformed event is not processed.
8. An unsupported version fails safely.
9. Repeated terminal failures reach the dead-letter queue.
10. Duplicate delivery does not duplicate processing claims.
11. Idempotency survives a worker restart.
12. Correlation context appears in Laravel and Python logs.
13. Graceful shutdown does not lose an in-flight message.
14. No Document is falsely transitioned to `INDEXED`.

## Acceptance criteria

- The worker runs separately from FastAPI.
- LocalStack SQS messages are received.
- The canonical event schema is enforced.
- Unsupported versions fail safely.
- Lifecycle state remains owned by Laravel.
- `QUEUED → PROCESSING` occurs through an explicit application boundary.
- Successful claims are acknowledged.
- Transient failures are retried.
- Repeated terminal failures reach the dead-letter queue.
- Duplicate events do not create duplicate processing claims.
- Idempotency is durable across worker restarts.
- Structured logs include correlation context.
- No text extraction, chunking, embeddings or vector indexing occurs.
- The phase ends with the Document legitimately in `PROCESSING`.

## Commit boundary

```bash
git add apps/api apps/ai contracts infrastructure compose.yaml docs
git commit -m "Add document ingestion worker"
```

---

# Phase 9 completion criteria

Phase 9 is complete when the following local workflow is demonstrated:

```text
Document is UPLOADED
↓
Ingestion is requested
↓
Document and outbox commit atomically
↓
Document becomes QUEUED
↓
Outbox publisher sends a versioned event to LocalStack SQS
↓
Python worker receives and validates the event
↓
Python worker claims processing through Laravel
↓
Document becomes PROCESSING
↓
SQS message is acknowledged
```

The demonstration must also prove:

- an SQS outage does not lose durable publication intent;
- duplicate requests do not create uncontrolled work;
- duplicate deliveries are handled idempotently;
- malformed or unsupported events fail safely;
- repeated failures reach the dead-letter queue;
- workspace and correlation context are preserved;
- Laravel remains authoritative for lifecycle state;
- no Document is marked `INDEXED`;
- no extraction, chunking, embedding or vector persistence occurs.

## Suggested phase tag

```bash
git tag -a phase-9 -m "Complete event-driven document ingestion"
git push origin phase-9
```

---

# Phase 10 — Text Extraction and Normalisation

## Phase objective

Convert supported source documents into a consistent internal representation with traceable source metadata.

---

## Stage 10.1 — Define Extracted Document Contract

### Objective

Define the internal representation produced by document extractors.

### Status

Completed on 2026-07-29.

### Decision

The canonical extracted-document architecture was accepted and recorded
before any Phase 10 implementation code in:

```text
docs/adr/0010-define-the-canonical-extracted-document-contract.md
```

Every extractor — plain text, PDF, DOCX, and any future format — produces
exactly one canonical, immutable `ExtractedDocument` representation, composed
of a common, extensible `Element` model (`HeadingElement`, `ParagraphElement`,
`ListElement`, `TableElement`, `CodeBlockElement`, `QuoteElement`,
`HyperlinkElement`, `ImageCaptionElement`, `HorizontalRuleElement`,
`FootnoteElement`, open to future element types without downstream redesign).
Extraction, normalisation and chunking each own exactly one responsibility:
each extractor owns its parser-specific objects privately and maps them into
canonical `ExtractedDocument`; normalisation consumes that representation and
produces a new immutable `NormalisedDocument` through deterministic structural
normalisation without discarding meaningful semantic information or chunking.
It may remove or reconcile semantically empty, duplicated or parser-generated
structural noise under explicit deterministic rules while preserving
provenance and traceability. Chunking consumes only `NormalisedDocument`.

Source format remains available as provenance for citations, auditing and
debugging, but chunking must not branch on it. Public Workspace and Document
identities remain at document level to preserve tenant and aggregate context.
Every consumer must deliberately handle future or currently unrecognised
`Element` types through a safe fallback rather than failing unexpectedly.

The governing principle is that extraction is a loss-minimisation stage, not
a simplification stage — any lossy transformation is deferred to whichever
later pipeline stage has enough context to make an informed trade-off, because
information can always be discarded later but never recreated. Reading order,
document hierarchy, page numbering, provenance, extraction confidence and
document metadata are preserved wherever the source format makes it
practical. Every semantic element carries a stable identifier. Extraction
failures are distinguished as transient or permanent, with permanent failures
carrying both a machine-readable failure code and a human-readable
explanation, every failure audited and non-fatal warnings retained.

`ExtractedDocument` and `NormalisedDocument` are immutable. A new extraction
run creates new element UUIDs; deterministic identifiers across re-extraction
are not required. Derived representations preserve traceability rather than
mutating a previous stage's output. Immutability reduces accidental or
unauthorised pipeline mutation but complements rather than replaces storage
access controls, integrity checks and auditing.

The full set of agreed decisions, rejected alternatives (an ad hoc
per-extractor structure, flattening to plain text at extraction time, adopting
a third-party parser's object model as canonical, mutable in-place pipeline
objects, an untyped generic `Block` shape, deferring provenance, and combining
extraction with chunking) and consequences is recorded in ADR 0010 rather than
duplicated here.

### Session verification

This was an architecture-and-documentation-only session. No extractors,
models, schemas or pipeline code were introduced. Verification consisted of:

* inspecting `CONTRIBUTING.md`, `IMPLEMENTATION_GUIDE.md`, `tasks.json`,
  `CLAUDE.md` and every existing ADR (0001–0009) before drafting, to preserve
  numbering, structure, terminology and cross-ADR consistency — in
  particular using "workspace" throughout rather than the stale "tenant"
  wording this stage's own placeholder text still used, to stay consistent
  with ADR 0006;
* two initial rounds of architecture review plus a final boundary review that
  separated private parser models, `ExtractedDocument`, `NormalisedDocument`
  and chunking responsibilities (see the session journal);
* checking the ADR against each Stage 10.1 acceptance criterion below; and
* running the repository-wide formatting, linting, type-checking, test,
  process and LocalStack gates: Laravel 118 tests (491 assertions), Python 42
  tests and web 10 tests all passed.

### Acceptance criteria

* The representation is typed. — Met: a canonical `ExtractedDocument`
  composed of a typed, extensible `Element` model, rather than an untyped
  block shape (explicitly rejected as an alternative).
* Source locations can later support citations. — Met: provenance and page
  numbering are preserved as an architectural requirement on every element.
* Extraction warnings are retained. — Met: covered as distinct non-fatal
  diagnostics on the immutable extraction output; exact warning
  representation is deferred to Stage 10.2 onward.
* Empty and malformed documents are represented explicitly. — Met: covered
  by the permanent-failure requirement (machine-readable code and
  human-readable explanation), rather than being silently absent.
* Tenant context is preserved. — Superseded by Workspace context: immutable
  public Workspace and Document identities are preserved at document level,
  consistent with ADR 0006, rather than duplicated on every element.
* Extractor version information is captured. — Met: provenance is required
  to support debugging, auditing and replay, which includes extractor
  identity and version; exact fields are deferred to Stage 10.2 onward.

### Commit boundary

git add docs/adr docs/journal tasks.json IMPLEMENTATION_GUIDE.md
git commit -m "Define extracted document representation"

---

## Stage 10.2 — Implement Plain Text Extraction

### Objective

Support ingestion of UTF-8 plain-text documents.

### Status

Completed on 2026-07-29 following human review and approval.

### Agreed bounded implementation brief

Implement a standalone Python plain-text extractor and only the canonical
model foundation required to support it under ADR-0010.

Use the existing Pydantic runtime to define strict, frozen models with
immutable collections and forbidden unexpected fields:

* `ExtractedDocument` with public Workspace and Document UUIDs, source media
  type and byte size, extractor identity/version, complete decoded text,
  ordered elements and retained warnings;
* a common semantic `Element` and source-location foundation;
* `ParagraphElement` with text and source provenance; and
* an explicit `UnknownElement` fallback so future element types can be
  retained or traversed conservatively rather than failing unexpectedly.

Add specialised heading, list, table and other semantic types only in the
extractor stages that can define and test their real invariants.

The plain-text extractor accepts bytes plus trusted extraction context. It:

* enforces an injectable maximum size whose default matches the platform's
  25 MiB upload limit;
* performs strict UTF-8 decoding while accepting an optional UTF-8 BOM;
* converts CRLF and standalone CR line endings to LF;
* retains the complete decoded text;
* represents blank-line-delimited non-empty content as ordered paragraph
  elements without trimming meaningful whitespace;
* records zero-based half-open character offsets and one-based inclusive line
  ranges into the retained text;
* creates fresh UUIDv4 element identifiers on every extraction run; and
* raises typed permanent failures for oversized, invalidly encoded, empty or
  whitespace-only content.

Unicode normalisation and general whitespace normalisation remain Stage 10.5
responsibilities. Tests compare deterministic content and provenance
separately from the intentionally fresh UUIDs.

This stage does not read S3, alter the SQS worker, extend message visibility,
report lifecycle transitions to Laravel, persist extracted content or perform
normalisation/chunking.

### Implementation record

The Python service now has a deliberately small `app/extraction` boundary:

* `models.py` defines the frozen, strict Pydantic contract foundation:
  extraction context, extractor identity, retained warnings, extensible
  source locations, base elements, paragraphs, the explicit unknown-element
  fallback and `ExtractedDocument`;
* `errors.py` distinguishes transient and permanent typed extraction failures
  and carries both a machine-readable code and user-facing explanation; and
* `plain_text.py` contains the standalone extractor with an injectable
  default 25 MiB limit.

The extractor validates the byte limit before decoding, accepts strict UTF-8
with an optional BOM, converts only CRLF and CR to LF, and splits paragraphs
only on blank LF-delimited lines. Limiting line recognition to LF is
intentional: Python's broader `splitlines()` also treats Unicode separators
as line boundaries, which would perform unapproved whitespace normalisation
before Stage 10.5. Meaningful leading, trailing and internal whitespace is
therefore retained.

Source byte size records the original bytes, including a BOM when present.
Character offsets refer to the complete decoded, newline-normalised text and
are zero-based and half-open; line ranges are one-based and inclusive.
Paragraph UUIDv4 identifiers are fresh for every extraction run. Tests compare
all deterministic fields separately from those deliberately fresh identities.

`UnknownElement` provides the required conservative representation for a type
a consumer does not yet understand. It retains the original kind, source
location and any available text without weakening normal extraction into an
untyped generic block model.

No new dependency was required because Pydantic is already part of the Python
runtime. No S3, SQS, Laravel lifecycle, persistence, normalisation or chunking
code changed.

### Commands executed

```bash
docker compose exec -T ai uv run ruff format app/extraction tests/test_plain_text_extraction.py
docker compose exec -T ai uv run ruff format --check app/extraction tests/test_plain_text_extraction.py
docker compose exec -T ai uv run ruff check app/extraction tests/test_plain_text_extraction.py
docker compose exec -T ai uv run mypy app/extraction tests/test_plain_text_extraction.py
docker compose exec -T ai uv run pytest tests/test_plain_text_extraction.py -q
make format-check lint typecheck test ps
make aws-status
```

### Verification evidence

* The focused extractor suite passed all 14 tests.
* The Python repository suite passed all 56 tests and mypy checked all 24
  source files without error.
* Ruff formatting and linting passed.
* Laravel passed 118 tests with 491 assertions.
* The web application passed all 10 tests and its TypeScript check.
* All eight Compose processes were running; every service with a health check
  was healthy.
* LocalStack bucket, upload CORS, ingestion queue, dead-letter queue and
  redrive policy verification passed.

### Acceptance criteria

* Valid text files are extracted. — Met by Unicode, multiline, whitespace and
  ordered-paragraph fixture coverage.
* Encoding failures are handled clearly. — Met by a typed permanent
  `invalid_encoding` failure with a user-readable UTF-8 explanation.
* Empty files are handled. — Met for both zero-byte and whitespace-only input
  through typed permanent `empty_content` failures.
* Excessively large files are bounded. — Met before decoding through the
  injectable limit and typed permanent `source_too_large` failure.
* Newline normalisation is deterministic. — Met for CRLF, CR and LF while
  deliberately retaining non-CR/LF Unicode separators for Stage 10.5.
* Tests use representative fixtures. — Met by
  `tests/fixtures/plain_text/representative.txt` plus focused boundary inputs.

### Commit boundary

git add apps/ai IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-07-29-r10-s02-implement-plain-text-extraction.md
git commit -m "Add plain text extraction"

---

## Stage 10.3 — Implement PDF Extraction

### Objective

Extract text and source-location metadata from PDFs.

### Status

Complete. Implemented, verified and approved on 2026-07-29.

### Agreed bounded implementation brief

Use `pdfplumber` for the initial PDF implementation behind a small,
implementation-neutral `PdfExtractor` protocol. The concrete
`PdfPlumberExtractor` accepts source bytes plus trusted `ExtractionContext`
and returns the same immutable canonical `ExtractedDocument` as every other
extractor. Parser-specific page, table, geometry and exception objects remain
private to its adapter package.

> The initial PDF extractor implementation uses pdfplumber. The extractor
> boundary intentionally isolates parser-specific behaviour so that
> alternative implementations, such as PyMuPDF or future commercial parsers,
> can be introduced and benchmarked without changing downstream pipeline
> stages.

Add `pdfplumber` as a runtime dependency and ReportLab as a development-only
dependency for deterministic synthetic fixtures. Do not implement
configuration-based selection, fallback extraction, multi-parser
reconciliation, confidence routing or benchmarking in this stage.

Extend the canonical model only as required for PDF output:

* precise rotation-aware `PdfSourceLocation` values using PDF points in
  pdfplumber's validated displayed-page, top-left-origin coordinate space,
  while explicitly recording whether a distinct CropBox exists rather than
  falsely claiming it was applied as a destructive extraction boundary;
* parser identity/version alongside the platform extractor identity/version;
* source-aware typed warnings; and
* immutable table, row and cell representations with cell provenance.

Treat paragraph reconstruction conservatively. PDFs do not expose true
semantic paragraphs: group only under explicit deterministic geometric rules
and preserve smaller ordered blocks when uncertain. Do not infer headings from
font size. Reading order is deterministic best effort based on parser output
and page geometry, not a universal semantic-order guarantee.

Recognise line-bounded tables and suppress their text from paragraph output
only when it can be confidently associated with successfully extracted cells.
If separation is ambiguous, preserve the content and emit a typed warning
rather than risk information loss.

Define canonical complete-text assembly as a stable contract:

* deterministic separators between elements and pages;
* deterministic table serialisation;
* zero-based, end-exclusive Unicode string offsets; and
* element ranges that are directly sliceable against
  `ExtractedDocument.text`.

Preserve blank pages. A textless page may receive a `no_extractable_text` or
`ocr_may_be_required` warning where appropriate; it is not automatically
declared scanned. A wholly textless image-bearing document may fail
permanently as `ocr_required`. OCR and password submission remain out of
scope.

Use injectable initial limits of 25 MiB, 500 pages and 5,000,000 extracted
characters. Byte, page and text limits reduce exposure but do not replace
future process isolation, timeouts, memory controls or protection against
unusually high per-page parser-object expansion. Those operational hardening
decisions must be resolved before untrusted PDF parsing is connected to the
worker.

Synthetic fixtures are the initial controlled suite. Rendered fixtures are
diagnostic evidence, while programmatic assertions over order, geometry,
tables, offsets and provenance remain the automated CI oracle. A curated,
legally redistributable real-world corpus is future evaluation work.

This stage does not read S3, alter the ingestion worker, report lifecycle
transitions, persist extracted output, perform OCR, normalise content or
chunk it.

### Implementation record

The PDF extraction package now exposes:

* an implementation-neutral `PdfExtractor` protocol;
* a minimal `create_pdf_extractor()` composition function; and
* a `PdfPlumberExtractor` adapter, which is the only production module that
  imports `pdfplumber`.

The adapter accepts source bytes and trusted `ExtractionContext`, returning
the immutable canonical `ExtractedDocument`. The dependency lock selected
`pdfplumber` 0.11.10. ReportLab 5.0.0 and its type stubs are development-only
dependencies used to generate deterministic test fixtures in memory.

The canonical extraction model gained immutable PDF source locations,
document metadata, parser identity, source-aware warnings and typed table,
row and cell structures. Existing plain-text extraction continues to use the
same model and shared 25 MiB source-size constant.

PDF coordinates are PDF points in pdfplumber's displayed-page,
top-left-origin coordinate space. Each location records the page number,
displayed width and height, validated bounding box, normalized rotation and
whether its PDF CropBox differs from its MediaBox. The v1 extractor does not
discard content merely because it lies outside a distinct CropBox. This is a
deliberate loss-minimising behaviour, not a claim that the CropBox was
destructively applied.

Paragraph-like output is deliberately conservative: pdfplumber text boxes are
ordered deterministically by vertical position, horizontal position and
parser order. This is a repeatable best effort for multi-column documents,
not a semantic reading-order guarantee. Headers and footers are retained for
Stage 10.5 and no heading meaning is inferred from font size.

Line-bounded tables are emitted as one `TableElement` with rectangular,
ordered rows and cells. Each cell retains PDF provenance when pdfplumber
provides it. A table's canonical text is escaped tab-separated rows:
backslashes, tabs and newlines inside cells are escaped before cells are
joined with tabs and rows with newlines. A text box is suppressed only when
its normalized content and bounding box confidently match an extracted table
cell. Ambiguous overlap is preserved and produces an
`ambiguous_table_text_overlap` warning.

The complete-text contract uses:

```text
element separator: "\n\n"
page separator:    "\n\n\f\n\n"
```

Blank pages therefore remain visible through page separators. Element
character ranges are zero-based, end-exclusive Python Unicode indexes and
slice directly against `ExtractedDocument.text`.

Textless pages produce `no_extractable_text`, or
`ocr_may_be_required` when an image is present. A wholly image-only document
fails permanently with `ocr_required`; a textless non-image document fails
with `empty_content`. Empty, corrupt and encrypted input, excessive source
bytes, page count and extracted character count also produce stable typed
permanent failures. OCR is never attempted.

The initial injectable limits are:

| Resource | Default |
|---|---:|
| Source bytes | 25 MiB |
| Pages | 500 |
| Complete extracted text | 5,000,000 Unicode characters |

These limits are checked in the adapter. They do not yet bound parser time,
memory or the number of parser objects on an individual page, so process
isolation and stronger operational controls remain required before the
extractor handles untrusted worker input.

### Problems and corrections

The initial encrypted-PDF handler expected pdfminer exceptions directly.
Current pdfplumber wraps them in `PdfminerException`, so the adapter now
unwraps the cause to distinguish a password-protected file from a generally
invalid PDF while retaining stable platform failure codes.

Review of a rotated PDF with a distinct CropBox showed that pdfplumber reports
the displayed page geometry without necessarily excluding content outside the
CropBox. The model and documentation were corrected from an inaccurate
“crop applied” claim to the factual `has_distinct_crop_box` provenance flag.

Pydantic's `model_copy()` does not revalidate updated values. The source
location invariant test therefore reconstructs through `model_validate()` so
it genuinely exercises the paired offset validation rather than merely
asserting a test-helper behaviour.

### Verification commands

```bash
docker compose build ai worker
docker compose up --detach --force-recreate --no-deps --wait ai worker
docker compose exec -T ai uv run ruff format --check \
  app/extraction tests/pdf_fixtures.py tests/test_pdf_extraction.py
docker compose exec -T ai uv run ruff check \
  app/extraction tests/pdf_fixtures.py tests/test_pdf_extraction.py
docker compose exec -T ai uv run mypy \
  app/extraction tests/pdf_fixtures.py tests/test_pdf_extraction.py
docker compose exec -T ai uv run pytest \
  tests/test_plain_text_extraction.py tests/test_pdf_extraction.py -q
make format-check lint typecheck test ps
make aws-status
```

A controlled table fixture was also written to a temporary PDF, inspected
with `pdfinfo`, rendered with Poppler and visually checked. The rendered table
contained the same three rows and two columns asserted by the automated
extraction test. The temporary PDF and image were then removed. This render
was diagnostic evidence only; programmatic assertions remain the CI oracle.

### Verification evidence

* The focused extraction suite passed all 33 tests: 14 plain-text tests and
  19 PDF tests.
* The complete Python suite passed all 75 tests and mypy checked all 31 source
  files without error.
* Ruff formatting and linting passed across 32 files.
* Laravel passed 118 tests with 491 assertions.
* The web application passed all 10 tests and its TypeScript check.
* The rebuilt AI and worker images installed the locked dependencies and
  started successfully.
* All eight Compose processes were running; every service with a health check
  was healthy.
* LocalStack bucket, upload CORS, ingestion queue, dead-letter queue and
  redrive policy verification passed.

### Deferred work

* OCR, password submission and semantic heading inference.
* Parser selection, fallback, reconciliation and benchmarking.
* A curated, legally redistributable real-world evaluation corpus.
* Process isolation, time and memory limits, and per-page parser-object
  expansion controls before worker integration.
* Normalisation of repeated headers, footers and other structural noise.
* Worker, object-storage, lifecycle and persistence integration.

### Acceptance criteria

* Text-based PDFs are supported. — Met by controlled single-page, multi-page,
  multi-column, rotated and metadata-bearing fixtures.
* Page numbers are preserved. — Met by element, cell and warning source
  locations with one-based page numbers.
* Unsupported encrypted files fail clearly. — Met by the typed permanent
  `encrypted_pdf` failure and user-readable explanation.
* Empty extraction is detected. — Met for empty bytes, textless PDFs and
  image-only PDFs with distinct typed outcomes.
* Table behaviour is documented. — Met by the deterministic table contract,
  loss-minimising duplicate rule and typed ambiguity warning above.
* Representative fixtures are tested. — Met by deterministic ReportLab text,
  layout, table, image, blank-page, crop/rotation and encrypted fixtures.
* OCR is not silently performed unless intentionally implemented. — Met:
  image-only content is warned or rejected and OCR is never invoked.

### Commit boundary

git add apps/ai IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-07-29-r10-s03-implement-pdf-extraction.md
git commit -m "Add PDF text extraction"

---

## Stage 10.4 — Implement DOCX Extraction

### Objective

Extract paragraphs, headings and table content from DOCX documents.

### Status

Complete. Implemented, verified and approved on 2026-07-29.

### Agreed bounded implementation brief

Use `python-docx` 1.2 for the initial DOCX implementation behind an
implementation-neutral `DocxExtractor` protocol. The concrete
`PythonDocxExtractor` accepts source bytes plus trusted `ExtractionContext`
and returns the immutable canonical `ExtractedDocument`. Parser-specific
package, document, paragraph, table, style and exception objects remain
private to the adapter.

Use `Document.iter_inner_content()` so top-level paragraphs and tables retain
their actual body order. Preserve non-empty ordinary paragraphs as
`ParagraphElement` values. Preserve explicit Word Heading 1 through Heading 9
styles as typed `HeadingElement` values with their level; do not infer
headings from font size, bold text or visual appearance.

Represent top-level tables with the existing immutable `TableElement`,
`TableRow` and `TableCell` contract. Produce a deliberate rectangular
layout-grid representation: account for omitted leading and trailing grid
columns, and accept python-docx's documented repeated-cell approximation for
merged spans. Serialise table text with the same escaped TSV rules as PDF so
complete text remains deterministic. Preserve cell text in document order;
if a cell contains a nested table, flatten its text deterministically into
that cell and emit a typed warning rather than silently discard it.

Add precise parser-neutral `DocxSourceLocation` provenance using zero-based
body block indexes plus optional row and column indexes for table cells.
Element character ranges remain zero-based, end-exclusive Unicode indexes
that slice directly against `ExtractedDocument.text`. DOCX has no dependable
rendered page coordinates, so do not invent page numbers.

Map available core properties into `ExtractedDocumentMetadata`. Use the same
element separator as the other extractors. Page separators are not introduced
because DOCX pagination is a rendering result rather than a stable property
of the source package.

Use the shared injectable 25 MiB source limit and an injectable
5,000,000-character complete-text limit. Treat empty, corrupt, non-DOCX and
password-protected/encrypted packages as stable typed permanent failures where
the parser can distinguish them. A valid document without extractable
paragraph or table text fails with `empty_content`.

Generate deterministic representative fixtures with `python-docx` itself.
Test ordered paragraphs, explicit heading levels, interleaved tables,
metadata, cell provenance, merged and omitted-grid behaviour where practical,
nested-table flattening, complete-text offsets, corrupt/empty inputs, resource
limits, immutability and fresh element UUIDs.

Do not extract images, perform OCR, include comments, resolve tracked-change
markup outside python-docx's public body iteration, normalise content, connect
the worker or object storage, persist output or chunk it. Unsupported or
unrecognised content must fail clearly or produce an explicit warning; it
must not disappear under a claim of complete semantic support.

### Implementation record

The DOCX extraction package now exposes:

* an implementation-neutral `DocxExtractor` protocol;
* a minimal `create_docx_extractor()` composition function; and
* a `PythonDocxExtractor` adapter, which owns every `python-docx` type and
  exception used by production code.

The dependency lock selected `python-docx` 1.2.0 and its existing `lxml`
dependency. `lxml-stubs` 0.5.1 is development-only and keeps the repository
MyPy gate explicit rather than suppressing parser exception types.

Top-level paragraphs and tables are read through
`Document.iter_inner_content()` in body order. Non-empty paragraphs remain
`ParagraphElement` values. Paragraphs using explicit Heading 1 through
Heading 9 style names or identifiers become immutable `HeadingElement`
values with the corresponding level. Visual formatting alone never creates a
heading.

`DocxSourceLocation` records the zero-based body block index. Table-cell
locations additionally record zero-based row and layout-grid column indexes.
Element locations carry zero-based, end-exclusive complete-text offsets that
slice directly against `ExtractedDocument.text`. DOCX page numbers are not
invented because pagination depends on the renderer rather than the package
content.

The complete text joins top-level elements with `"\n\n"`. No page separator
is added. Tables reuse the escaped TSV representation established in Stage
10.3. Omitted layout-grid columns are padded explicitly, and python-docx's
documented repeated-value approximation is retained for merged cells.
Nested tables are flattened into their containing cell in document order and
produce a `nested_table_flattened` warning.

Available title, author, subject, keywords, creation date and modification
date core properties are mapped into canonical document metadata. Embedded
inline images are not treated as extracted content: usable surrounding text
is retained and an `images_not_extracted` warning is emitted. A document
without extractable paragraph or table text fails with `empty_content`.

Stable permanent failures cover empty bytes, invalid packages,
encrypted-or-legacy OLE Word packages, excessive source size and excessive
complete text. The shared default source limit is 25 MiB and the injectable
complete-text limit is 5,000,000 Unicode characters.

### Problems and corrections

Initial static analysis identified four boundary issues without requiring a
contract change: the fixture helper annotated the `Document()` factory rather
than its returned type, the direct lxml exception import needed stubs, the
internal heading representation required an explicit non-null guard before
constructing `HeadingElement`, and one test needed to narrow an optional
offset before arithmetic. These were corrected at their actual boundaries.

One focused MyPy rerun encountered `sqlite3.OperationalError: disk I/O error`
while opening generated parallel cache shards on the Docker bind mount. The
same code passed immediately with an isolated cache, proving the failure was
not a typing error. Only the generated `.mypy_cache/3.14` files were removed;
the normal repository MyPy command was then rerun successfully and later
passed again in the complete gate.

### Verification commands

```bash
docker compose run --rm --no-deps ai uv lock
docker compose build ai worker
docker compose up --detach --force-recreate --no-deps --wait ai worker
docker compose exec -T ai uv run ruff format \
  app/extraction tests/docx_fixtures.py tests/test_docx_extraction.py
docker compose exec -T ai uv run ruff check \
  app/extraction tests/docx_fixtures.py tests/test_docx_extraction.py
docker compose exec -T ai uv run mypy \
  app/extraction tests/docx_fixtures.py tests/test_docx_extraction.py
docker compose exec -T ai uv run pytest \
  tests/test_plain_text_extraction.py tests/test_pdf_extraction.py \
  tests/test_docx_extraction.py -q
make format-check
make lint
make typecheck
make test
make ps
make aws-status
```

### Verification evidence

* The focused extraction suite passed all 49 tests: 14 plain-text, 19 PDF and
  16 DOCX tests.
* The complete Python suite passed all 91 tests and MyPy checked all 37 source
  files without error.
* Ruff reported all 38 files formatted and no lint violations.
* Laravel Pint passed across 105 files, and Laravel passed 118 tests with 491
  assertions.
* The web application passed all 10 tests plus ESLint and TypeScript checks.
* The rebuilt AI and worker images installed the locked DOCX dependencies and
  started successfully.
* All eight Compose processes were running; every service with a health check
  was healthy.
* LocalStack bucket, upload CORS, ingestion queue, dead-letter queue and
  redrive policy verification passed.

### Deferred work

* Images, OCR, comments and unsupported tracked-revision body content.
* Rendered pagination or conversion to PDF.
* Rich inline formatting, drawing geometry and Word layout reproduction.
* Worker, object-storage, lifecycle and persistence integration.
* Normalisation and chunking.

### Acceptance criteria

* Paragraph text is extracted in order. — Met by the interleaved
  heading/paragraph/table/paragraph fixture and complete-text assertions.
* Heading information is retained. — Met by typed heading elements and an
  explicit Heading 2 style/level test without visual inference.
* Table content is represented deliberately. — Met by immutable rows, cells,
  provenance, escaped TSV, merged-cell behaviour and nested-table warnings.
* Corrupt files fail clearly. — Met by stable typed `invalid_docx`,
  `unsupported_word_package` and `empty_content` failures.
* Source structure can support later citations. — Met by body-block and
  table-cell indexes plus directly sliceable element character ranges.
* Representative fixtures are tested. — Met by deterministic metadata,
  ordered body, table, merged-cell, nested-table, image, empty and corrupt
  fixtures plus boundary inputs.

### Commit boundary

git add apps/ai IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-07-29-r10-s04-implement-docx-extraction.md
git commit -m "Add DOCX text extraction"

---

## Stage 10.5 — Normalise Extracted Content

### Objective

Convert extractor-specific output into one deterministic normalised representation.

### Status

Complete. Implemented, verified and approved on 2026-07-29.

### Agreed bounded implementation brief

Add a pure `StructuralNormaliser` that consumes only immutable
`ExtractedDocument` and produces a distinct immutable `NormalisedDocument`.
It must not receive parser objects, mutate extraction output, chunk content or
branch on source media type.

The normalised contract records:

* Workspace and Document identity;
* source media type, extractor identity and metadata as provenance;
* a versioned normaliser identity;
* immutable normalised semantic elements;
* deterministic complete text;
* retained extraction warnings;
* explicit normalisation changes; and
* source-element UUID and source-location linkage for every derived element.

Generate derived element UUIDs deterministically from normaliser version,
source-element identity, semantic kind and normalised content. The same
immutable extraction input must therefore produce the same complete
normalised representation without requiring deterministic identity across
separate extraction runs.

Use the following explicit version-one rules:

* apply Unicode NFC canonical composition;
* convert Unicode space-separator characters to ASCII space;
* normalise CRLF and CR to LF;
* collapse horizontal spaces and tabs inside known paragraph and heading
  content, trim line edges and reconcile repeated empty internal lines;
* normalise table cells independently and rebuild the existing escaped TSV
  table representation;
* retain semantic kind, heading level, table structure, confidence, metadata,
  warnings and source provenance;
* remove a known element only when its normalised content is semantically
  empty, recording the removal explicitly; and
* preserve unknown future element types through a safe immutable fallback
  containing their original kind, conservative text and canonical serialized
  payload rather than failing unexpectedly.

Treat repeated headers and footers conservatively. A paragraph is eligible
only when PDF source-location provenance places it in the top or bottom 15%
of its page, the same normalized text occurs at the corresponding boundary
of at least three content-bearing pages, and every content-bearing page has
the same candidate. Do not use source media type or filename as a switch.
Never remove headings or tables under this rule. Record every suppressed
source element and retain page traceability.

Preserve PDF page boundaries in complete text from source-location page
numbers, including gaps for blank pages where extraction provenance makes
them knowable. Preserve DOCX section structure through heading elements.
Other formats retain their ordered element boundaries. Normalised element
character ranges must be zero-based, end-exclusive indexes that directly
slice `NormalisedDocument.text`.

Test independently from extraction libraries. Cover immutability, unchanged
source input, deterministic output and identifiers, Unicode and whitespace,
semantic structure, table rebuilding, warning and metadata retention, source
linkage, conservative repeated-boundary detection, blank PDF pages, unknown
element fallback, semantically empty removal and direct-slice offsets.

Do not persist output, connect the worker, alter lifecycle state, select
chunks, add embeddings or implement source-format-specific normalisers.

### Planned behaviour

* Unicode normalisation;
* whitespace normalisation;
* repeated-header/footer handling;
* section-boundary preservation;
* page-boundary preservation;
* deterministic output;
* warning retention.

### Implementation record

The new `app.normalisation` package contains immutable normalisation models
and a pure `StructuralNormaliser`. It imports the canonical extraction
contract but no parser library. The source `ExtractedDocument` is never
modified.

`NormalisedDocument` distinguishes the new representation explicitly. It
retains Workspace and Document identity, source media type and byte size,
extractor identity, metadata and extraction warnings. It adds normaliser name
and version, deterministic complete text, immutable derived elements and
explicit `NormalisationChange` records.

Each derived element has:

* a UUIDv5 generated from a private fixed namespace, normaliser version,
  source element UUID, semantic kind and normalized text;
* one or more source-element UUIDs;
* one or more immutable source locations;
* normalized complete-text offsets;
* retained extraction confidence; and
* its semantic structure, including heading level or table rows and cells.

UUIDv5 makes repeated normalisation of the same immutable extraction output
fully deterministic. A separate extraction run still creates new source
element UUIDs in accordance with ADR-0010, so no cross-extraction identity
claim is introduced.

Version-one known-text rules apply NFC composition, CR/LF normalization,
Unicode space-separator replacement, horizontal whitespace collapse, line
edge trimming and reduction of three or more consecutive line feeds to two.
They do not apply Unicode compatibility normalization. Table cells are
normalised independently and the table's escaped TSV complete text is then
rebuilt from those cells.

Unknown future element subtypes are converted to
`NormalisedUnknownElement`. Their source kind, canonical JSON payload,
source identity and source location are retained. Any available text receives
only conservative NFC and CR/LF normalization, avoiding known-type whitespace
policy being imposed on unrecognised semantics.

Known elements that become empty under the explicit rules are removed with a
`semantically_empty_element_removed` change referencing the source UUID.
Extraction warnings are copied intact into the distinct
`extraction_warnings` collection rather than being renamed or discarded.

Repeated PDF headers and footers are removed only when all of these conditions
hold:

* at least three content-bearing pages are represented;
* every represented page contains at least two elements;
* the candidate is a paragraph and is respectively first or last on the
  page;
* geometry places it in the top or bottom 15% of the page; and
* normalized candidate text is identical and non-empty on every represented
  page.

The rule inspects `PdfSourceLocation` provenance rather than source media type.
It never suppresses headings or tables. Each group of removed source UUIDs is
recorded as `repeated_header_removed` or `repeated_footer_removed`.

PDF page transitions use the existing `"\n\n\f\n\n"` separator. A jump from
page one to page three emits two separators, retaining the blank middle page.
Leading and trailing page gaps are retained when page provenance makes them
knowable. Non-page formats use the standard `"\n\n"` element separator, and
DOCX heading elements retain section structure.

### Problems and corrections

The first table-normalisation test accidentally supplied an already-composed
`é` followed by another combining acute accent. NFC correctly retained the
double accent, exposing that the fixture did not represent the intended
decomposed form. The fixture was corrected to `e` plus combining acute; the
test now proves canonical composition to one `é`.

No production correction or dependency was required after focused review.

### Verification commands

```bash
docker compose exec -T ai uv run ruff format \
  app/normalisation tests/test_structural_normalisation.py
docker compose exec -T ai uv run ruff check \
  app/normalisation tests/test_structural_normalisation.py
docker compose exec -T ai uv run mypy \
  app/normalisation tests/test_structural_normalisation.py
docker compose exec -T ai uv run pytest \
  tests/test_plain_text_extraction.py tests/test_pdf_extraction.py \
  tests/test_docx_extraction.py tests/test_structural_normalisation.py -q
make format-check
make lint
make typecheck
make test
make ps
make aws-status
```

### Verification evidence

* All 12 focused structural-normalisation tests passed.
* The combined extraction and normalisation suite passed all 61 tests.
* The complete Python suite passed all 103 tests and MyPy checked all 41
  source files without error.
* Ruff reported all 42 files formatted and no lint violations.
* Laravel Pint passed across 105 files, and Laravel passed 118 tests with 491
  assertions.
* The web application passed all 10 tests plus ESLint and TypeScript checks.
* All eight Compose processes were running; every service with a health check
  was healthy.
* LocalStack bucket, upload CORS, ingestion queue, dead-letter queue and
  redrive policy verification passed.

### Deferred work

* Persistence and worker integration for extraction and normalisation output.
* Additional semantic element subtypes as real extractors begin producing
  them.
* Richer list, code, quote, hyperlink, footnote and image-caption
  normalisation rules.
* Chunking, which begins only after R11-S01 defines its accepted contract.

### Acceptance criteria

* Equivalent input produces deterministic output. — Met by complete model
  equality and UUIDv5 identity across repeated runs of the same immutable
  extraction input.
* Source-location mappings remain valid. — Met by retained source UUIDs and
  immutable source locations plus separate directly sliceable normalized
  offsets.
* Structural boundaries are not discarded unnecessarily. — Met by preserved
  semantic types, heading levels, table structure, PDF page gaps and
  conservative repeated-boundary suppression.
* Normalisation is tested independently. — Met by 12 tests constructed solely
  from canonical extraction models without invoking any source parser.
* Raw extraction and normalised content can be distinguished. — Met by
  separate immutable contracts, explicit source extractor and normaliser
  identities, and a test proving the extraction input is unchanged.

### Commit boundary

git add apps/ai IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-07-29-r10-s05-normalise-extracted-content.md
git commit -m "Normalise extracted document content"

---

# Phase 11 — Chunking

## Phase objective

Split normalised documents into retrieval units while preserving enough context and source metadata for accurate answers and citations.

## Pre-ADR R11-S01 architecture direction

R11-S01 must define a pluggable `ChunkingStrategy` abstraction. This is a
deferred Phase 11 decision and does not expand ADR-0010 beyond extraction and
normalisation.

The agreed direction for that architecture session is:

* `ChunkingStrategy` accepts an immutable `NormalisedDocument`;
* `ChunkingStrategy` returns an immutable `ChunkingResult`;
* callers depend on the strategy abstraction rather than a concrete chunker;
* Phase 11 v1 implements one deterministic, structure-aware baseline
  strategy;
* the abstraction permits future structural, semantic, context-enriched,
  LLM-driven and hybrid strategies without changing the consuming pipeline;
* `ChunkingResult` preserves strategy identity, strategy version,
  consequential configuration, source-element provenance, per-chunk token
  counts and semantic warnings; and
* a future model-assisted strategy records consequential model identity and
  parameters in its semantic configuration, while execution token usage,
  call count, latency, estimated cost and processing duration remain separate
  operational telemetry.

ADR-0011 supersedes the earlier wording that placed operational measurements
inside `ChunkingResult`. Exact contract fields and baseline implementation
details remained intentionally deferred to R11-S01 and R11-S02 respectively.

---

## Stage 11.1 — Define Chunk Contract

### Objective

Define the fields and invariants of a document chunk.

### Status

Completed on 2026-07-30.

### Decision

The chunking architecture and chunk contract were accepted and recorded
before any Phase 11 implementation code in:

```text
docs/adr/0011-define-the-chunking-architecture-and-contract.md
```

Chunking is a deterministic, versioned transformation from one immutable
`NormalisedDocument` into one immutable `ChunkingResult`, via
`ChunkingStrategy.chunk(document: NormalisedDocument) -> ChunkingResult`. No
`ChunkingContext`/`ChunkingInput` wrapper is introduced — `NormalisedDocument`
already carries its own `workspace_id` and `document_id`, consistent with how
`ExtractionContext` exists only because raw bytes lack identity of their own.
The invocation accepts only the document; strategy configuration and
implementation dependencies (a tokenizer, for example) are supplied
independently and must not require resolving a persisted pipeline-run entity.

The pipeline depends on the `ChunkingStrategy` abstraction, not a concrete
chunker, so future semantic, contextual or model-assisted strategies are
additive. Given an identical document, strategy, version and consequential
configuration, chunking must produce an identical chunk set and identical
chunk identities; a model-assisted strategy is conformant only if its model
identity/version, parameters and model-produced decisions are fixed, retained
or cached enough to actually reproduce that guarantee.

A successful `ChunkingResult` must account for all content the canonical
normalised model classifies as chunkable — content that cannot be
represented safely without loss causes a typed failure, not an incomplete
result dressed up as a success. Warnings remain valid only for recoverable
compromises (splitting an oversized element, missing a preferred target
size, a table-specific compromise, a recorded fallback), never for content
silently dropped.

Each chunk carries provenance back to the `NormalisedElement`(s) it was
built from, and a deterministically derived identifier (document identity,
strategy identity/version, configuration fingerprint, ordinal, ordered
provenance spans and final content) rather than a random UUID — deliberately
unlike `ExtractedElement`'s fresh-per-run identity, because chunking's input
is already a fixed, complete value with no "which run" ambiguity to
preserve. `ChunkingResult` retains the consequential configuration as both a
canonical snapshot and a derived fingerprint, and keeps the semantic outcome
(strategy identity/version, configuration, chunks, provenance, warnings, and
for a model-assisted strategy its model identity/parameters) conceptually
separate from operational/execution information (timing, call count, token
usage, cost), which orchestration or instrumentation may capture instead of
the strategy itself.

The full set of agreed decisions, rejected alternatives and consequences is
recorded in ADR 0011 rather than duplicated here.

### Session verification

This was an architecture-and-documentation-only session. No chunk model,
chunking strategy or pipeline code was introduced. Verification consisted of:

* inspecting the existing `apps/ai/app/extraction` and
  `apps/ai/app/normalisation` models and every prior ADR (0001–0010) before
  drafting, to confirm `NormalisedDocument` already carries `workspace_id`
  and `document_id` and to ground the `ChunkingContext` decision in the
  actual `ExtractionContext` precedent rather than an assumption;
* two rounds of architecture review before acceptance (see the session
  journal for what was tightened in each round — completeness/failure
  semantics, model-assisted determinism, snapshot-and-fingerprint,
  tokenizer precision, the narrowed persistent-run-record rejection,
  semantic-versus-operational `ChunkingResult` fields, strengthened chunk
  identity material, and the "identical input"/"given nothing but" wording
  fixes);
* checking the ADR against each Stage 11.1 acceptance criterion below.

### Acceptance criteria

* Chunk identifiers are stable or reproducibly generated. — Met: chunk
  identity is deterministically derived, not randomly generated, from
  document identity, strategy identity/version, configuration fingerprint,
  ordinal, provenance spans and final content.
* Tenant and document identity are mandatory. — Superseded by workspace
  identity: `NormalisedDocument.workspace_id`/`document_id` are already
  mandatory and are what chunk identity is partly derived from; no separate
  chunk-level tenant field is required by the architecture.
* Source metadata supports citations. — Met: every chunk preserves
  provenance back to its source `NormalisedElement`(s), without this ADR
  designing the citation system itself (deferred to Phase 16 — now Phase 17
  following the Phase 15 Ingestion Orchestration insertion — and the
  citation/re-extraction constraint in `PROJECT_ROADMAP.md`).
* Chunking strategy version is recorded. — Met: strategy identity and
  version are part of the semantic `ChunkingResult` and part of chunk
  identity derivation.
* Token counts are available. — Met: each chunk records its own token
  count; tokenizer identity (precise enough to resolve exact tokenisation
  behaviour) is recorded once, on the configuration.
* The contract supports re-chunking. — Met: determinism plus derived
  (not random) chunk identity make a re-chunking run comparable and
  diffable against a prior one.

### Commit boundary

git add docs/adr docs/journal tasks.json IMPLEMENTATION_GUIDE.md
git commit -m "Define document chunk contract"

---

## Stage 11.2 — Implement Baseline Chunker

### Objective

Create a deterministic baseline chunking strategy.

### Status

Completed on 2026-07-30 after implementation, repository-wide verification
and human review.

### Implementation

The Python service now exposes the ADR-0011
`ChunkingStrategy.chunk(document: NormalisedDocument) -> ChunkingResult`
boundary and a version-one `BaselineStructuralChunker`.

Tokenisation is isolated behind a `Tokenizer` protocol. The baseline adapter
pins `tiktoken==0.13.0` and the explicit `o200k_base` encoding. Its semantic
configuration records the library identity and version, encoding identity,
the SHA-256 fingerprint of the loaded mergeable-rank vocabulary, and the
consequential chunking values:

```text
target_tokens=400
max_tokens=512
overlap_tokens=64
preferred_min_tokens=100
```

The complete typed configuration snapshot is retained on `ChunkingResult`;
canonical JSON and SHA-256 produce its deterministic fingerprint. No model
identity is present because this strategy invokes no model.

The upstream hash-verified `o200k_base` vocabulary is loaded into a dedicated
cache while the AI image is built. Runtime chunking therefore does not depend
on downloading tokenizer data from the network.

The baseline preserves complete normalised elements where they fit. Oversized
paragraphs prefer sentence boundaries, then line and word boundaries, before
falling back to a tokenizer-safe character boundary. Oversized tables prefer
row boundaries. Headings are moved to the following primary group when that
association fits under the hard maximum. List-like content currently follows
paragraph structure because the canonical normalised model does not yet
define a list element subtype.

Every contribution records its normalised-element UUID, original
source-element UUIDs and source locations, element-local character span,
chunk-local character span, and whether it is primary content or deliberate
overlap. A postcondition proves that primary spans cover every non-empty
chunkable element contiguously from start to end. An unknown element with
preserved text is chunked conservatively; an unknown element containing only
its preserved structural payload is explicitly classified as non-chunkable
and produces a semantic warning. Any other unrepresentable content raises
the typed `UnrepresentableContentError`.

Chunk UUIDv5 identities include workspace and Document identity, strategy
name/version, configuration fingerprint, ordinal, ordered provenance spans
and roles, and final chunk content. Repeating the same transformation
therefore reproduces both values and identities. Per-chunk token counts are
semantic output; execution duration and other operational telemetry remain
outside `ChunkingResult` in accordance with ADR-0011.

### Problems and corrections

The first focused MyPy run found that the private piece model represented its
provenance role as a general string while `ChunkContribution` correctly
requires the literal values `primary` or `overlap`. The private type was
narrowed to the same literal union. No runtime behaviour changed.

An explicit small-final-chunk test showed that the preferred-minimum warning
initially counted overlap as though it were new primary content. The check now
measures primary content only, so repeated context cannot conceal an
undersized retrieval unit.

The pre-ADR Phase 11 note incorrectly placed processing duration and
model-execution usage inside semantic `ChunkingResult`. Its wording was
corrected to match accepted ADR-0011 before implementation.

### Verification commands

```bash
docker compose exec -T ai uv add 'tiktoken==0.13.0'
docker compose exec -T ai uv run ruff format \
  app/chunking tests/test_baseline_chunking.py
docker compose exec -T ai uv run ruff check \
  app/chunking tests/test_baseline_chunking.py
docker compose exec -T ai uv run mypy \
  app/chunking tests/test_baseline_chunking.py
docker compose exec -T ai uv run pytest \
  tests/test_baseline_chunking.py -q
docker compose build ai worker
docker run --rm --network none rag-platform-ai:development \
  python -c "from app.chunking import TiktokenTokenizer; print(TiktokenTokenizer().identity)"
make format-check lint typecheck test ps
```

### Verification evidence

* All 14 focused baseline-chunking tests passed.
* The rebuilt AI image loaded the pinned tokenizer successfully with
  networking disabled, proving the vocabulary is available at runtime
  without a download.
* The complete Python suite passed all 117 tests; MyPy checked all 48 source
  files without error and Ruff reported all 49 files formatted with no lint
  violations.
* Laravel Pint passed across 105 files, and Laravel passed 118 tests with 491
  assertions.
* The web application passed all 10 tests plus ESLint and TypeScript checks.
* All eight Compose processes were running; every service with a health check
  was healthy.

### Deferred work

* Stage 11.3 evaluation against representative prose, PDF, DOCX and table
  material, including chunk-size distribution and retrieval-context review.
* Dedicated list, code, quote and other future normalised element semantics.
* Model-assisted, semantic, contextual and hybrid strategies.
* Pipeline orchestration, persistence, embeddings and vector storage.

### Acceptance criteria

* Chunk size is bounded. — Met: every chunk is checked against the explicit
  512-token hard maximum, including overlap and separators.
* Overlap is deterministic. — Met: trailing primary spans are selected
  deterministically under the explicit 64-token budget and labelled
  `overlap`.
* Text is not silently lost. — Met: a postcondition requires contiguous
  primary coverage of every chunkable element; unsafe representation raises
  a typed failure.
* Chunk ordering is stable. — Met: source order is retained, ordinals must be
  contiguous, and repeated runs are equal.
* Source metadata is preserved. — Met: contributions retain normalised and
  extracted element identities, immutable source locations, and exact source
  and chunk character spans.
* Edge cases are tested. — Met: tests cover oversized prose and tables,
  sentence and row boundaries, headings, Unicode, overlap, small final
  chunks, unknown elements, empty documents, configuration validation,
  immutability and identity changes.
* Configuration is explicit rather than hard-coded throughout the codebase.
  — Met: one validated immutable configuration snapshot owns all
  consequential parameters and tokenizer identity.

### Commit boundary

git add apps/ai IMPLEMENTATION_GUIDE.md \
  docs/journal/2026-07-30-r11-s02-implement-baseline-chunker.md tasks.json
git commit -m "Implement baseline document chunking"
git tag -a phase-11-s02 -m "Complete Stage 11.2: Implement Baseline Chunker"

---

## Stage 11.3 — Evaluate Chunking Quality

### Objective

Create an evaluation corpus and measure whether chunks preserve useful retrieval context.

### Status

Completed on 2026-07-30 after implementation, repository-wide verification,
the final editorial refinement and human review.

### Evaluation implementation

The committed, repository-authored CC0 evaluation specification is:

```text
apps/ai/tests/fixtures/chunking/corpus.json
```

`tests/test_chunking_evaluation.py` builds six deterministic cases from that
specification: a short plain-text document, long repeated prose, awkward
Unicode/whitespace and an unbroken value, a three-page prose-heavy PDF with
repeated header/footer furniture, a multi-section DOCX with headings, and a
48-row table. PDF and DOCX bytes are generated during the tests through the
same extraction and normalisation implementations used elsewhere; no
third-party document fixture is committed.

The tests verify repeated-run equality, the hard token bound, complete primary
text coverage, exact source/chunk character slicing, inherited source-element
identity and location equality, retained PDF and DOCX location types,
repeated-page-furniture removal, heading/body adjacency, row-boundary table
splitting and inspectable distribution output.

Measured default-profile token distributions:

| Case | Chunks | Minimum | Median | Mean | Maximum |
|---|---:|---:|---:|---:|---:|
| Short plain text | 1 | 25 | 25 | 25 | 25 |
| Long plain text | 4 | 337 | 428.5 | 414 | 462 |
| Awkward plain text | 1 | 48 | 48 | 48 | 48 |
| Prose-heavy PDF | 2 | 316 | 356 | 356 | 396 |
| Multi-section DOCX | 1 | 87 | 87 | 87 | 87 |
| Table | 3 | 224 | 400 | 362.67 | 464 |

Expected structural behaviour, measured results and known limitations are
recorded in:

```text
docs/evaluation/r11-s03-baseline-chunking.md
```

### Problems and corrections

The first heading expectation required each heading/body pair to be the only
two primary contributions in a chunk. The actual valid result placed all
three small sections into one bounded chunk while retaining every heading
immediately before its body. The assertion was corrected to test adjacency,
which is the intended structural invariant.

The initial PDF fixture contained too little prose to justify the planned
"prose-heavy" description. It was expanded to three pages with six body
paragraphs per page. The final result forms two chunks of 316 and 396 tokens
after deterministic repeated-header/footer removal.

### Verification commands

```bash
docker compose exec -T ai uv run ruff format \
  tests/test_chunking_evaluation.py
docker compose exec -T ai uv run ruff check \
  tests/test_chunking_evaluation.py
docker compose exec -T ai uv run mypy \
  tests/test_chunking_evaluation.py
docker compose exec -T ai uv run pytest \
  tests/test_chunking_evaluation.py -q
docker compose exec -T ai uv run python -c \
  "import json; from tests.test_chunking_evaluation import evaluated_cases, token_distribution; from app.chunking import BaselineStructuralChunker; print(json.dumps({name: token_distribution(BaselineStructuralChunker().chunk(document)) for name, document in evaluated_cases().items()}, indent=2, sort_keys=True))"
make format-check lint typecheck test ps
```

### Verification evidence

* All 6 focused chunking-evaluation tests passed.
* The complete Python suite passed all 123 tests; MyPy checked all 49 source
  files without error and Ruff reported all 50 files formatted with no lint
  violations.
* Laravel Pint passed across 105 files, and Laravel passed 118 tests with 491
  assertions.
* The web application passed all 10 tests plus ESLint and TypeScript checks.
* All eight Compose processes were running; every service with a health check
  was healthy.

### Acceptance criteria

* Evaluation fixtures are committed where licensing permits. — Met: all
  semantic material is repository-authored and explicitly CC0; binary PDF
  and DOCX inputs are generated deterministically during tests.
* Expected boundaries are documented. — Met in the corpus specification and
  evaluation report for prose, headings, page furniture, tables and fallback
  splitting.
* Chunk-size distributions can be inspected. — Met through a reusable metrics
  function, JSON output command and the measured table above.
* Text-loss checks exist. — Met: primary provenance is reconstructed per
  element and compared exactly with every chunkable element's complete text.
* Source-location integrity is tested. — Met: contribution spans slice both
  chunk and element text exactly; identity/location values and PDF/DOCX
  location types survive the full path.
* Known limitations are recorded. — Met in the evaluation report without
  expanding this stage into semantic/model-assisted chunking.

### Commit boundary

git add apps/ai/tests docs/evaluation \
  docs/journal/2026-07-30-r11-s03-evaluate-chunking-quality.md \
  IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Add chunking evaluation corpus"
git tag -a phase-11-s03 -m "Complete Stage 11.3: Evaluate Chunking Quality"
git tag -a phase-11 -m "Complete Phase 11: Chunking"

---

# Phase 12 — Observability Foundation

## Phase objective

Make the platform observable by design, using OpenTelemetry as a
vendor-neutral instrumentation and correlation foundation, before
embeddings, retrieval and generation introduce the platform's first calls
to external AI providers.

---

## Stage 12.1 — Define Telemetry and Observability Architecture

### Objective

Establish OpenTelemetry as the platform's canonical instrumentation API,
vendor-neutral Collector boundary, context-propagation, privacy and
semantic-convention principles.

### Status

Complete.

### Decision

* OpenTelemetry as the canonical instrumentation API;
* the OpenTelemetry Collector as the routing and backend boundary;
* vendor neutrality from commercial observability platforms;
* trace-context propagation across Laravel, the queue, Python and external
  providers;
* a privacy allowlist rather than a denylist for telemetry attributes;
* AI-specific semantic conventions only where OpenTelemetry has no
  equivalent;
* metric cardinality discipline;
* graceful degradation on telemetry failure.

### Architecture record

`docs/adr/0012-establish-the-observability-and-telemetry-foundation.md`

ADR-0012 was accepted on 30 July 2026. It establishes OpenTelemetry as the
canonical application instrumentation API and the Collector as the only
backend-routing boundary. Application code must not depend directly on a
commercial observability SDK or backend-specific exporter.

The accepted decision keeps the durable contract-level `correlation_id`
distinct from the OpenTelemetry trace ID while allowing the correlation ID
as an allowlisted span attribute. Trace context must propagate across
Laravel HTTP, the transactional outbox and queue, the Python worker,
outbound HTTP and external providers where supported.

Telemetry uses an explicit safe-attribute allowlist. Document content,
prompts, retrieved chunks, questions, model responses, credentials and
secrets are excluded by default. Workspace and document public identifiers
may be recorded on traces, but unbounded identifiers and free text must not
be metric labels. Telemetry failure must degrade safely and must never fail
the business operation being observed.

### Alternatives rejected

* a custom application-owned telemetry abstraction duplicating
  OpenTelemetry primitives;
* direct instrumentation against a commercial observability SDK;
* exporting independently from each application without a Collector;
* deferring observability until after AI-provider integrations exist;
* capturing full request and response payloads by default;
* using raw entity identifiers as metric labels;
* requiring the durable correlation ID to equal the OpenTelemetry trace ID.

### Commands used

```bash
sed -n '1,520p' \
  docs/adr/0012-establish-the-observability-and-telemetry-foundation.md
rg -n "ADR-0012|Stage 12\\.1|R12-S01|Phase 12" \
  docs/adr/README.md IMPLEMENTATION_GUIDE.md PROJECT_ROADMAP.md tasks.json
git show --stat --oneline 5b28068
git show --format=fuller --no-patch 5b28068
```

### Changes made

* Added and accepted ADR-0012.
* Added ADR-0012 to the ADR index.
* Reconciled the accepted ADR against the renumbered Phase 12 roadmap,
  implementation guide and tracker.
* Added the factual Stage 12.1 journal entry and advanced the session
  tracker to Stage 12.2.

### Verification

* Confirmed ADR-0012 has every required ADR section and is marked Accepted.
* Confirmed each Stage 12.1 acceptance criterion is represented as an
  explicit architectural invariant.
* Confirmed the ADR preserves the established Workspace, transactional
  outbox, HMAC worker-authentication and cross-language pipeline boundaries.
* Confirmed this was an architecture-only stage; no application,
  infrastructure or dependency files changed, so application test suites
  were not applicable.

### Acceptance criteria

* OpenTelemetry is adopted as the canonical instrumentation API. — Met by
  ADR-0012's canonical instrumentation API decision.
* No proprietary telemetry abstraction duplicates OpenTelemetry primitives.
  — Met: the ADR explicitly rejects such a wrapper.
* Backend choice is a Collector-configuration concern, not an
  application-code concern. — Met by the Collector boundary and
  application-code invariants.
* Trace-context propagation requirements are defined across every service
  boundary. — Met across Laravel HTTP, queue/outbox, Python, outbound HTTP
  and supporting providers.
* A default privacy allowlist is defined. — Met: sensitive content is
  excluded by default and only explicitly safe attributes may be exported.
* Metric cardinality principles are defined. — Met: entity identifiers and
  free text are prohibited as metric labels.
* Telemetry failure-handling behaviour is defined. — Met: telemetry must
  degrade safely without failing user-facing work.

### Commit boundary

The accepted ADR and ADR-index change were committed as:

```bash
git commit -m "Accept ADR-0012: establish observability and telemetry foundation"
```

The completed stage record is committed and tagged at the Stage 12.1
boundary:

```bash
git add IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-07-31-r12-s01-define-telemetry-and-observability-architecture.md
git commit -m "Close telemetry and observability architecture stage"
git tag -a phase-12-s01 \
  -m "Complete Stage 12.1: Define Telemetry and Observability Architecture"
```

---

## Stage 12.2 — Establish Local Telemetry Infrastructure

### Objective

Provision the shared OpenTelemetry Collector and a local telemetry backend
before either application is instrumented.

### Status

Complete.

### Infrastructure

The application-facing `otel-collector` service uses the explicitly pinned
OpenTelemetry Collector `0.153.0` image. It accepts OTLP/gRPC on port 4317
and OTLP/HTTP on port 4318, exposes its health extension on port 13133, and
has explicit traces and metrics pipelines in
`infrastructure/opentelemetry/collector.yaml`.

The Collector exports both signals over OTLP/HTTP to the explicitly pinned
`grafana/otel-lgtm:0.29.2` local backend. The backend packages local
Prometheus-compatible metric storage, Tempo trace storage and Grafana for
development use. Grafana is available at `http://localhost:3001`; the
backend's own OTLP ports are not published to the host.

The dependency direction is:

```text
Laravel / Python services
  -> OpenTelemetry SDK and instrumentation
  -> otel-collector
  -> otel-lgtm
  -> Grafana
```

OpenTelemetry is the instrumentation and transport standard; it is not a
telemetry store. `otel-lgtm` is the replaceable local storage, query and
visualisation destination. Application service environments contain only
the application-facing Collector endpoint
`http://otel-collector:4318` and the standard `http/protobuf` protocol.
They contain neither the `otel-lgtm` address nor any Grafana-specific
configuration. The backend address is owned exclusively by Collector
configuration through `OTEL_BACKEND_OTLP_ENDPOINT`.

This stage intentionally adds no OpenTelemetry SDK dependency and no
Laravel- or Python-specific instrumentation. Those remain bounded to
Stages 12.3 and 12.4.

### Files changed

* `compose.yaml`
  * adds the pinned `otel-collector` and `otel-lgtm` services;
  * publishes Collector ports only on loopback;
  * publishes Grafana on loopback port 3001;
  * gives both services health checks and starts the Collector only after
    the backend is healthy;
  * gives Laravel and Python processes only the standard Collector OTLP
    endpoint/protocol variables;
  * persists local backend state in `telemetry_data`.
* `infrastructure/opentelemetry/collector.yaml`
  * defines OTLP/gRPC and OTLP/HTTP receivers;
  * defines batch processing and retrying/queued OTLP/HTTP export;
  * defines separate traces and metrics pipelines;
  * enables the Collector health endpoint.
* `scripts/telemetry/smoke-test.sh`
  * creates a unique synthetic trace and metric;
  * submits them only to the application-facing Collector;
  * queries the same trace and exact metric value through Grafana's
    provisioned Tempo and Prometheus data sources;
  * retries briefly for asynchronous batching and fails explicitly if
    either signal is absent.
* `.env.example`
  * documents local Grafana and Collector ports plus the shared
    application-facing OTLP endpoint/protocol.
* `Makefile`
  * adds `make telemetry-smoke`.
* `README.md`
  * documents local ports, responsibilities, topology, inspection and the
    infrastructure smoke command.

### Commands used

```bash
docker compose config --quiet
docker compose config --images
sh -n scripts/telemetry/smoke-test.sh
docker compose pull otel-lgtm otel-collector
docker compose up --detach --wait --wait-timeout 180 \
  otel-lgtm otel-collector
make telemetry-smoke
make up
docker compose ps
docker compose images
make telemetry-smoke
make format-check
make lint
make typecheck
make test
make aws-status
git diff --check
```

### Verification

* `docker compose config --quiet` accepted the complete topology.
* Compose resolved exactly
  `ghcr.io/open-telemetry/opentelemetry-collector-releases/opentelemetry-collector:0.153.0`
  and `grafana/otel-lgtm:0.29.2`; neither uses a floating tag.
* All ten Compose processes were running. All eight services with health
  checks were healthy.
* `make telemetry-smoke` proved that a fresh trace and metric travelled
  through the dedicated Collector and were queryable through Grafana's
  Tempo and Prometheus data sources.
* The backend's OTLP ports remained internal to the Compose network; only
  the Collector's OTLP ports were published to the host.
* Web verification passed: ESLint, TypeScript, and 10 tests.
* Laravel verification passed: Pint and 118 tests (491 assertions).
* Python verification passed: Ruff formatting, Ruff linting, mypy, and 123
  tests.
* LocalStack S3, SQS, DLQ and redrive verification passed.

### Problems and corrections

The first agent-run smoke attempt could not reach the loopback health port
because that command was executed inside a restricted network sandbox. The
published port and Collector logs confirmed the service was ready; running
the same repository command with normal local loopback access passed. No
topology correction was required.

The initial smoke identity used only whole seconds. It was strengthened
before completion to combine the current time and process identity for a
fresh trace ID and to require the metric value produced by the current run.
This prevents previously stored telemetry from creating a false positive.

### Acceptance criteria

* The Collector runs as a Docker Compose service. — Met by the healthy
  pinned `otel-collector` service.
* A local backend makes traces and metrics inspectable without a commercial
  account. — Met by the loopback-only Grafana UI backed by local Tempo and
  Prometheus storage.
* Collector configuration, not application code, determines backend
  routing. — Met: only the Collector knows
  `http://otel-lgtm:4318`.
* Laravel and Python share consistent endpoint configuration. — Met:
  Laravel API/publisher and Python API/worker receive the same standard
  Collector endpoint and protocol.
* No application code yet depends on this infrastructure — this stage
  provisions it only. — Met: no application dependencies, imports or
  instrumentation call sites were added.

### Commit boundary

```bash
git add .env.example README.md compose.yaml makefile \
  infrastructure/opentelemetry/collector.yaml \
  scripts/telemetry/smoke-test.sh \
  docs/journal/2026-07-31-r12-s02-establish-local-telemetry-infrastructure.md \
  IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Establish local telemetry infrastructure"
git tag -a phase-12-s02 \
  -m "Complete Stage 12.2: Establish Local Telemetry Infrastructure"
```

---

## Stage 12.3 — Instrument Laravel with OpenTelemetry

### Objective

Emit traces and metrics from the Laravel API using the OpenTelemetry SDK.

### Status

Complete.

### Implementation

Laravel now uses the official `open-telemetry/sdk` 1.15.0 and
`open-telemetry/exporter-otlp` 1.4.0 packages directly. The container also
builds the explicitly pinned protobuf extension 5.35.1. The SDK exports
traces and cumulative metrics over OTLP/HTTP protobuf only to the dedicated
application-facing Collector.

`TelemetryServiceProvider` owns provider construction and lifecycle. It
binds the official SDK/API provider interfaces, retains request and database
instrumentation for the complete Laravel lifecycle, and performs best-effort
flush/shutdown. If configuration or export fails, no-op providers or guarded
lifecycle operations preserve application behaviour.

The implemented signals are:

* one server span plus request-count and request-duration metrics for each
  Laravel HTTP request, named with the resolved route template rather than
  the raw URL;
* client spans plus duration metrics for database operations, recording only
  the database system and operation verb, never SQL text, bindings or table
  names;
* producer spans plus publication-count and publication-duration metrics for
  claimed outbox events;
* W3C `traceparent` and `tracestate` injection into SQS message attributes
  without changing the canonical JSON event contract.

The explicit attribute allowlist keeps entity UUIDs and correlation IDs on
traces where they aid diagnosis, while excluding them from metric labels.
Payloads, source content, prompts, questions, credentials and SQL are not
accepted attributes. Resource attributes are also explicit: only
`service.name` and `deployment.environment.name` are exported. This avoids
the default PHP process detector capturing command arguments, which can
contain request query data under the development server.

Cumulative metric temporality is explicit because the local
Prometheus-compatible backend does not retain the SDK's default delta
points. This is protocol configuration behind the Collector boundary, not
Grafana coupling. HTTP telemetry flushes in Laravel's post-response
termination lifecycle, so a Collector outage does not delay the response
body. Tests disable network telemetry globally and use the SDK's in-memory
exporters only in the focused telemetry suite.

### Files changed

* `.env.example` and `compose.yaml`
  * configure endpoint, protocol, 250 ms exporter timeout, cumulative metric
    temporality, SDK enablement and distinct API/publisher service names.
* `apps/api/Dockerfile`
  * installs the pinned protobuf 5.35.1 PHP extension.
* `apps/api/composer.json` and `apps/api/composer.lock`
  * add the official OpenTelemetry SDK and OTLP exporter.
* `apps/api/config/telemetry.php`
  * defines exporter configuration and separate trace/metric attribute
    allowlists.
* `apps/api/app/Providers/TelemetryServiceProvider.php`
  * registers official SDK providers, database observation and lifecycle.
* `apps/api/app/Telemetry/`
  * constructs the SDK, filters attributes, records safe database operations
    and guards flush/shutdown.
* `apps/api/app/Http/Middleware/TraceHttpRequests.php`
  * records safe route-template HTTP spans and baseline metrics.
* `apps/api/app/Actions/Ingestion/PublishIngestionOutbox.php`
  * records outbox producer spans and low-cardinality outcome metrics.
* `apps/api/app/Services/Ingestion/SqsIngestionEventPublisher.php`
  * injects current W3C context into SQS message attributes.
* `apps/api/bootstrap/app.php`, `apps/api/bootstrap/providers.php` and
  `apps/api/phpunit.xml`
  * activate instrumentation and keep normal tests network-independent.
* `apps/api/tests/Feature/TelemetryTest.php`
  * verifies propagation, privacy, metrics, database/outbox instrumentation,
    SQS injection and graceful exporter failure.

### Commands used

```bash
docker compose exec -T api composer require \
  'open-telemetry/sdk:^1.15' \
  'open-telemetry/exporter-otlp:^1.4' \
  --with-all-dependencies --no-interaction
make format-api
docker compose exec -T api php artisan test \
  tests/Feature/TelemetryTest.php
docker compose build api
docker compose up --detach --wait --wait-timeout 180 api publisher
docker compose exec -T api php -r \
  'echo phpversion("protobuf"), PHP_EOL;'
curl --header 'traceparent: 00-…-…-01' \
  http://127.0.0.1:8000/api/auth/user
curl http://127.0.0.1:3001/api/datasources/proxy/uid/tempo/api/traces/…
curl http://127.0.0.1:3001/api/datasources/proxy/uid/prometheus/api/v1/label/__name__/values
docker compose stop otel-collector
curl http://127.0.0.1:8000/api/auth/user
docker compose up --detach --wait --wait-timeout 120 otel-collector
make format-check lint typecheck test aws-status telemetry-smoke
docker compose config --quiet
docker compose exec -T api composer validate --strict
git diff --check
```

### Verification

* Focused telemetry suite: 7 tests and 70 assertions passed.
* A known incoming W3C trace ID produced a `GET /api/auth/user` server span
  with the expected parent and was queryable from Tempo.
* The stored trace resource contained only `service.name` and
  `deployment.environment.name`; the synthetic query value was absent.
* Prometheus exposed Laravel HTTP request and database operation
  count/duration series after cumulative export.
* With the Collector stopped, the same API request still returned its
  expected 401 response in 0.036 seconds; Collector health was then restored.
* The rebuilt image reported protobuf 5.35.1.
* Repository verification passed:
  * web: ESLint, TypeScript and 10 tests;
  * Laravel: Pint and 125 tests (561 assertions);
  * Python: Ruff formatting/linting, mypy and 123 tests;
  * LocalStack S3/SQS/DLQ/redrive checks;
  * Collector-to-Grafana trace and metric smoke verification.

### Problems and corrections

The first runtime trace used the SDK's default resource detectors. Under
PHP's local development server, a query value appeared as
`process.command`, even though span attributes were safe. Resource
collection was therefore changed to an explicit two-field allowlist and a
focused regression test was added.

The Collector received the PHP SDK's initial delta metrics, but the local
Prometheus-compatible backend did not retain them. Metric temporality was
made explicitly cumulative, after which the HTTP and database series were
queryable. A temporary Collector debug exporter was used only for diagnosis
and was removed before completion.

HTTP middleware begins before Laravel has resolved the route. Route
template naming is therefore finalised after downstream dispatch, avoiding
raw request paths and the misleading `unmatched` name.

### Acceptance criteria

* Laravel emits spans using the OpenTelemetry SDK, not a proprietary
  wrapper. — Met by direct use of official API/SDK interfaces and OTLP
  exporters.
* No sensitive payload appears in exported attributes by default. — Met by
  trace, metric and resource allowlists plus negative tests and stored-trace
  inspection.
* Telemetry failures do not affect request success or user-visible latency.
  — Met by guarded no-op/flush behaviour and the live Collector-outage
  request.
* Metrics avoid unbounded-cardinality labels. — Met by the separate metric
  allowlist, which excludes all workspace, document, event and correlation
  identifiers.

### Commit boundary

```bash
git add .env.example compose.yaml apps/api \
  docs/journal/2026-07-31-r12-s03-instrument-laravel-with-opentelemetry.md \
  IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Instrument Laravel with OpenTelemetry"
git tag -a phase-12-s03 \
  -m "Complete Stage 12.3: Instrument Laravel with OpenTelemetry"
```

---

## Stage 12.4 — Instrument the Python AI Service with OpenTelemetry

### Objective

Emit traces and metrics from the Python AI service using the OpenTelemetry
SDK.

### Status

Complete.

### Implementation

The Python AI image now uses the official OpenTelemetry Python SDK and
OTLP/HTTP protobuf exporter 1.44.0. Both the FastAPI service and ingestion
worker export only to the dedicated application-facing Collector. No
Grafana-specific SDK, endpoint or application configuration was introduced.

`app/telemetry.py` owns SDK construction and best-effort lifecycle. It uses
an explicit two-field resource (`service.name` and
`deployment.environment.name`), a 250 ms export timeout, cumulative metrics
and separate trace and metric attribute allowlists. Configuration or
shutdown failure degrades to no-op or guarded behaviour rather than changing
application correctness.

The implemented signals are:

* FastAPI server spans plus standard HTTP request count and duration metrics,
  using route templates rather than raw URLs and extracting incoming W3C
  context;
* standard SQS receive client spans and
  `messaging.client.operation.duration` metrics;
* per-message consumer spans, `messaging.process.duration` and a bounded
  outcome counter;
* Laravel claim client spans plus count and duration metrics, with the
  current W3C context injected alongside the existing HMAC headers.

The SQS adapter reads only the `traceparent` and `tracestate` message
attributes written in Stage 12.3. The canonical JSON event body is unchanged.
Because this worker receives one message at a time and has no competing
ambient request context, the message creation context becomes the consumer
span parent. The later claim span is its child and propagates that context to
Laravel.

Entity, event and correlation identifiers are allowlisted on traces for
diagnosis but excluded from metrics. Metric dimensions are limited to bounded
operation, route, status, version and outcome values. Bodies, storage keys,
filenames, document content, prompts, questions, credentials and signatures
are never recorded. Automatic exception recording is disabled so exception
messages cannot bypass the allowlist; only the exception type may be emitted.

### Files changed

* `apps/ai/pyproject.toml` and `apps/ai/uv.lock`
  * add and lock the official OpenTelemetry SDK and OTLP/HTTP exporter 1.44.0.
* `apps/ai/app/telemetry.py` and `apps/ai/app/settings.py`
  * configure providers, resource privacy, attribute allowlists and guarded
    lifecycle from standard OpenTelemetry environment variables.
* `apps/ai/app/main.py`
  * adds W3C-aware FastAPI server spans and low-cardinality HTTP metrics.
* `apps/ai/app/ingestion/sqs.py`
  * records standard receive telemetry and extracts W3C SQS attributes.
* `apps/ai/app/ingestion/worker.py`
  * creates the consumer span and records safe processing telemetry.
* `apps/ai/app/ingestion/claim_client.py`
  * creates the outbound claim span, injects W3C headers and records bounded
    outcomes without recording the signed body.
* `apps/ai/app/worker.py`
  * starts and shuts down the SDK around the worker lifecycle.
* `apps/ai/tests/test_telemetry.py` and focused existing ingestion tests
  * verify propagation, privacy, metrics, lifecycle and transport behaviour.
* `compose.yaml`
  * passes the shared timeout, temporality and SDK enablement settings to the
    AI and worker processes.

### Commands used

```bash
cd apps/ai
uv add 'opentelemetry-sdk>=1.44.0' \
  'opentelemetry-exporter-otlp-proto-http>=1.44.0'
uv sync --locked --all-groups
.venv/bin/ruff format app tests
.venv/bin/ruff check app tests
.venv/bin/mypy app tests
cd ../..
docker compose build ai worker
docker compose run --rm --no-deps ai uv sync --locked
docker compose run --rm --no-deps ai pytest -q
docker compose run --rm --no-deps ai ruff check app tests
docker compose run --rm --no-deps ai mypy app tests
docker compose config --quiet
docker compose up --detach --build ai worker
docker compose exec ai python -c \
  "import urllib.request; print(urllib.request.urlopen('http://127.0.0.1:8001/health', timeout=2).read().decode())"
docker compose run --rm worker python -m app.worker --once
docker compose exec otel-lgtm curl --get --data-urlencode \
  'q={ resource.service.name = "rag-platform-ingestion-worker" }' \
  http://127.0.0.1:3000/api/datasources/proxy/uid/tempo/api/search
docker compose exec otel-lgtm curl --get --data-urlencode \
  'query=messaging_client_operation_duration_seconds_count{service_name="rag-platform-ingestion-worker"}' \
  http://127.0.0.1:3000/api/datasources/proxy/uid/prometheus/api/v1/query
docker compose run --rm \
  -e OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:9 \
  worker python -m app.worker --once
```

### Verification

* Ruff formatting and linting passed.
* mypy passed across 51 Python source and test files.
* The complete Python suite passed: 130 tests.
* Seven focused telemetry tests prove service and queue parent extraction,
  claim propagation, privacy allowlists, bounded metric attributes and
  graceful setup/shutdown failure.
* Tempo stored `rag-platform-ai` `GET /health` traces and
  `rag-platform-ingestion-worker` SQS receive traces through the Collector.
* Prometheus stored the cumulative worker receive-duration metric with only
  service, environment, queue, operation and messaging-system labels.
* A worker poll against an intentionally unreachable OTLP endpoint completed
  successfully; exporter failures were logged without changing its exit
  result.
* Repository verification passed:
  * web: ESLint, TypeScript and 10 tests;
  * Laravel: Pint and 125 tests (561 assertions);
  * Python: Ruff formatting/linting, mypy and 130 tests;
  * LocalStack S3/SQS/DLQ/redrive checks;
  * Collector-to-Grafana trace and metric smoke verification.

### Problems and corrections

The host's cached Python 3.14 release candidate was incompatible with the
locked Pydantic build, so authoritative runtime tests were executed in the
repository's Python 3.14.6 container. Static Ruff and mypy checks also passed
on the host and in the container.

The initial receive metric used a project-shaped name. It was corrected to
the official `messaging.client.operation.duration` semantic convention and
the required `messaging.operation.name` attribute was added. Processing uses
the official `messaging.process.duration` convention; only the
domain-specific outcome count retains a `rag.*` name.

### Acceptance criteria

* The Python service emits spans using the OpenTelemetry SDK, not a
  proprietary wrapper. — Met by direct official API/SDK instrumentation and
  OTLP export solely to the Collector.
* No sensitive payload appears in exported attributes by default. — Met by
  explicit resource, trace and metric allowlists plus negative tests.
* Telemetry failures do not affect worker correctness. — Met by guarded
  setup/shutdown and the successful live unreachable-endpoint worker run.
* Metrics avoid unbounded-cardinality labels. — Met by excluding all entity,
  event, correlation and transport-message identifiers from metric labels.

### Acceptance criteria

* The Python service emits spans using the OpenTelemetry SDK, not a
  proprietary wrapper.
* No sensitive payload appears in exported attributes by default.
* Telemetry failures do not affect worker correctness.
* Metrics avoid unbounded-cardinality labels.

### Commit boundary

```bash
git add compose.yaml apps/ai \
  docs/journal/2026-07-31-r12-s04-instrument-the-python-ai-service-with-opentelemetry.md \
  IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Instrument the Python AI service with OpenTelemetry"
git tag -a phase-12-s04 \
  -m "Complete Stage 12.4: Instrument the Python AI Service with OpenTelemetry"
```

---

## Stage 12.5 — Verify Cross-Service Trace Propagation and the Privacy Allowlist

### Objective

Prove that one logical request remains correlated across Laravel, the
queue and Python, and that sensitive content never appears in exported
telemetry.

### Status

Completed on 2026-07-31.

### Implementation and verification

The accepted ADR-0012 boundary was exercised using a real authenticated
document upload and ingestion request. Inspection identified one missing
link: the ingestion HTTP span ended before the asynchronous outbox publisher
ran, but the outbox did not preserve its W3C context. The publisher therefore
started a separate trace even though its SQS child context reached Python
correctly.

The narrow correction adds nullable `traceparent` and `tracestate` fields to
the outbox. `RequestDocumentIngestion` injects the current request context
when it creates the durable event, and `PublishIngestionOutbox` extracts that
context as the producer span's parent. These fields are immutable with the
event identity and payload. Missing context remains valid so telemetry cannot
block durable ingestion and pre-existing outbox rows remain publishable.

The versioned event body and durable `correlation_id` were not changed. The
business correlation identifier remains separate from the OpenTelemetry trace
ID, exactly as ADR-0012 requires.

Two repeatable repository commands now exercise the live guarantees:

* `make telemetry-verify` authenticates as the deterministic synthetic
  development user, uploads a one-line synthetic document, requests
  ingestion with known W3C and correlation IDs, waits for `PROCESSING`, and
  queries Tempo for the same trace. It requires the API, publisher and Python
  worker service identities plus the publisher, consumer and internal-claim
  spans. It also proves the unique synthetic content is absent and checks
  Prometheus series for forbidden entity labels. Database, S3 and temporary
  local artifacts are removed on exit.
* `make telemetry-outage` authenticates while the stack is healthy, stops
  only the Collector, proves the protected user-facing platform-status
  request still returns HTTP 200, and restores the Collector through an exit
  trap before returning.

Focused Laravel tests separately prove that the request context is persisted
at the transactional outbox boundary, the publisher resumes the recorded
parent, safe trace attributes remain available and metric attributes remain
bounded. Existing Python tests continue proving SQS extraction and claim-HTTP
injection.

### Commands used

```bash
docker compose exec -T api php artisan migrate --force
docker compose exec -T api php artisan db:seed --force
docker compose restart publisher
docker compose exec -T api ./vendor/bin/pint
docker compose exec -T api php artisan test --filter=TelemetryTest
make telemetry-verify
make telemetry-outage
make lint format-check typecheck test aws-status \
  telemetry-smoke telemetry-verify telemetry-outage
docker compose exec -T postgres createdb \
  --username rag_platform rag_platform_r12_s05_test
docker compose exec -T -e DB_DATABASE=rag_platform_r12_s05_test \
  api php artisan migrate:fresh --force
docker compose exec -T postgres psql \
  --username rag_platform \
  --dbname rag_platform_r12_s05_test \
  --command "SELECT column_name, is_nullable, character_maximum_length
    FROM information_schema.columns
    WHERE table_name = 'outbox_events'
      AND column_name IN ('traceparent', 'tracestate')"
docker compose exec -T postgres dropdb \
  --username rag_platform rag_platform_r12_s05_test
```

### Verification evidence

* A real trace crossed `rag-platform-api`,
  `rag-platform-ingestion-publisher` and
  `rag-platform-ingestion-worker`, including the asynchronous outbox/SQS
  boundary and the signed claim request back to Laravel.
* The synthetic document reached `PROCESSING`; no extraction, chunking or
  indexing was introduced.
* The per-run sensitive marker was absent from the exact stored trace.
* Prometheus exposed no correlation, Workspace, Document, event or transport
  message identifier as a metric label.
* With the Collector stopped, the authenticated platform-status endpoint
  returned HTTP 200; the Collector was restored healthy afterward.
* The focused Laravel telemetry suite passed: 8 tests and 76 assertions.
* Repository checks passed: web lint, TypeScript and 10 tests; Laravel Pint
  and 127 tests (568 assertions); Python Ruff formatting/linting, mypy and
  130 tests; LocalStack provisioning; telemetry infrastructure smoke; live
  cross-service verification; and Collector-outage isolation.
* A disposable PostgreSQL database migrated cleanly from empty. The nullable
  outbox columns were observed at their intended lengths (`traceparent` 55,
  `tracestate` 512), and the database was removed.
* The acceptance script removed its synthetic Document, outbox, claim and S3
  records. The source queue had zero visible or in-flight messages afterward.

### Residual gaps

Stage 12 validates the ingestion request, publication and claim slice only.
Future external embedding and generation provider calls do not exist yet and
will inherit the same propagation/privacy rules when their phases implement
them. Dashboards, alerting, sampling and production retention remain the
operational work explicitly deferred by ADR-0012.

### Acceptance criteria

* Trace context is proven to propagate across every service boundary for
  one logical request. — Met by the live authenticated upload, asynchronous
  publication, Python consumption and signed Laravel claim trace.
* A test proves sensitive content is never exported. — Met by the per-run
  synthetic content assertion against the exact stored Tempo trace and the
  application allowlist tests.
* A test proves a Collector outage does not fail a user-facing request. — Met
  by `make telemetry-outage`, which returned HTTP 200 and restored the
  Collector healthy.
* Findings and any residual gaps are recorded. — Met above and in the Stage
  12.5 journal.

### Commit boundary

```bash
git add makefile \
  apps/api/app/Actions/Documents/RequestDocumentIngestion.php \
  apps/api/app/Actions/Ingestion/PublishIngestionOutbox.php \
  apps/api/app/Models/OutboxEvent.php \
  apps/api/database/factories/OutboxEventFactory.php \
  apps/api/database/migrations/2026_07_31_000006_add_trace_context_to_outbox_events_table.php \
  apps/api/tests/Feature/DocumentIngestionPublicationTest.php \
  apps/api/tests/Feature/TelemetryTest.php \
  scripts/telemetry/verify-cross-service.sh \
  scripts/telemetry/verify-collector-outage.sh \
  tests/end-to-end/README.md \
  docs/journal/2026-07-31-r12-s05-verify-cross-service-trace-propagation-and-the-privacy-allowlist.md \
  IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Verify cross-service trace propagation and telemetry privacy"
git tag -a phase-12-s05 \
  -m "Complete Stage 12.5: Verify Cross-Service Trace Propagation and the Privacy Allowlist"
git tag -a phase-12 \
  -m "Complete Phase 12: Observability Foundation"
```

---

# Phase 13 — Embeddings

## Phase objective

Generate reproducible vector representations while keeping model providers replaceable.

---

## Stage 13.1 — Define Embedding Provider Boundary

### Objective

Introduce a provider-neutral interface for embedding text.

### Status

Complete. ADR-0013 defines the provider-neutral embedding boundary, the
initial Voyage embedding-space profile and the compatibility rules that
Stages 13.2–14 must preserve.

### Planned decisions

* hosted versus local model support;
* model identifier configuration;
* embedding dimensions;
* batching;
* retry and timeout policy;
* rate limiting;
* cost instrumentation;
* test doubles;
* model-version tracking.

### Required ADR

[ADR-0013: Define the Provider-Neutral Embedding Architecture and Embedding Contract](docs/adr/0013-define-the-provider-neutral-embedding-architecture-and-contract.md)

### Decisions recorded

* Application and pipeline callers depend on an application-owned
  `Embedder` protocol, not directly on the Voyage client.
* Voyage is the initial hosted V1 provider. The initial profile uses
  `voyage-4-large`, 1,024-dimensional float vectors, unit-length
  normalisation and explicit document/query input-type mappings.
* Provider-side truncation is disabled. Oversized inputs become typed,
  permanent failures rather than silently incomplete embeddings.
* One immutable `EmbeddingProfile` records the complete compatible space:
  provider, model, optional revision, dimensions, data type, normalisation,
  both purpose mappings and adapter version. Its canonical snapshot produces
  a deterministic SHA-256 fingerprint.
* Document and query requests retain their individual purpose, but that
  per-operation purpose does not create a second fingerprint. Both mappings
  belong to one compatible profile so query vectors can search its document
  vectors.
* A consequential profile change creates a new vector generation and requires
  controlled re-embedding; matching dimensions alone never imply
  compatibility.
* Batches retain local chunk identity. The Voyage adapter associates returned
  vectors by the provider's documented response order and validates count,
  dimensions, finite numeric values and profile retention without claiming
  that Voyage understands local chunk IDs.
* Semantic embedding results remain separate from operational telemetry.
  Content and vectors are excluded from telemetry by default under ADR-0012.
* Only rate limits, timeouts and temporary provider unavailability are
  retryable. Invalid input, credentials/configuration faults, malformed
  responses, dimension mismatches and profile mismatches are permanent typed
  failures.
* Ordinary tests use a deterministic fake provider. Live Voyage tests remain
  isolated and opt-in.

### Scope boundary

This stage changed architecture documentation only. It did not add the
`Embedder` classes, the Voyage dependency, credentials, provider calls,
vector persistence, Qdrant topology, retrieval or re-embedding orchestration.
Those remain bounded to Stage 13.2 and the later phases identified by
ADR-0013.

### Architecture review and corrections

The final review identified two issues in the prepared ADR:

1. A single request's `document` or `query` purpose initially participated
   in the profile fingerprint. That would have assigned incompatible
   fingerprints to the two sides of the same retrieval space. The accepted
   ADR now records both purpose mappings together in one profile while
   retaining the individual purpose separately on each request and result.
2. The model and dimensions were illustrative rather than selected, despite
   the Stage 13.1 acceptance criterion requiring them to be explicit. The
   accepted ADR now selects `voyage-4-large`, 1,024 float dimensions, unit
   normalisation and disabled provider truncation. It also records that
   Voyage does not expose an immutable model revision.

The review also clarified the batch association guarantee: source IDs are
kept locally and paired with provider results by documented positional order;
the platform validates that mapping but does not claim provider-side ID
verification.

### Commands used

```bash
sed -n '1,760p' \
  docs/adr/0013-define-the-provider-neutral-embedding-architecture-and-contract.md
sed -n '9501,9578p' IMPLEMENTATION_GUIDE.md
sed -n '2380,2565p' tasks.json
rg -n \
  "voyage-4-large|1024|truncation|purpose|fingerprint|model_revision|association|normalisation" \
  docs/adr/0013-define-the-provider-neutral-embedding-architecture-and-contract.md
git show --stat --format=fuller dd0b0d7
git show --stat --format=fuller 8ec0cf2
git diff --check
python3 -m json.tool tasks.json >/dev/null
```

Voyage's official embedding, API-reference, rate-limit, normalisation and
pricing documentation was also reviewed on 2026-08-03 to verify the current
model family, explicit input types, configurable dimensions, float output,
normalisation behaviour, batch limits and truncation semantics.

### Files changed

* `docs/adr/0013-define-the-provider-neutral-embedding-architecture-and-contract.md`
* `docs/adr/README.md`
* `IMPLEMENTATION_GUIDE.md`
* `docs/journal/2026-08-03-r13-s01-define-embedding-provider-boundary.md`
* `PROJECT_JOURNEY.md` (updated locally; intentionally ignored by Git under
  the repository's current policy)
* `tasks.json`

No application, dependency, environment or infrastructure file changed.

### Verification

* Confirmed ADR-0013 is marked Accepted, indexed and contains every ADR
  section required by `docs/adr/README.md`.
* Confirmed the amended profile resolves document/query compatibility while
  retaining explicit purpose lineage.
* Confirmed the initial provider, model, dimensions, data type,
  normalisation and truncation policy are explicit.
* Confirmed retries are bounded to typed transient failures, ordinary tests
  require no external provider and controlled re-embedding is mandatory for
  consequential profile changes.
* Confirmed ADR-0013 preserves ADR-0006 tenancy, ADR-0011 chunk identity and
  completeness, and ADR-0012 telemetry privacy and semantic/operational
  separation.
* Confirmed this was architecture-only, so application test suites and live
  provider calls were not applicable.

### Acceptance criteria

* Application code does not depend directly on one vendor SDK everywhere.
* Model and dimensions are explicit.
* Timeouts and retries are bounded.
* Provider failures are typed.
* Tests can run without paid external calls.
* Model changes trigger controlled re-embedding.

All Stage 13.1 acceptance criteria are satisfied by ADR-0013. Executable
proof of the protocol, validation, failure taxonomy and deterministic fake
belongs to Stage 13.2.

### Commit boundary

The accepted ADR and index were committed locally as:

```bash
git commit -m "Accept ADR-0013: Provider-neutral embedding architecture"
```

The final amendments resolving compatibility, V1 profile and batch-order
semantics were committed under the same architecture subject. The completed
stage record is committed and tagged at the Stage 13.1 boundary:

```bash
git add IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-08-03-r13-s01-define-embedding-provider-boundary.md
git commit -m "Close embedding provider architecture stage"
git tag -a phase-13-s01 \
  -m "Complete Stage 13.1: Define Embedding Provider Boundary"
```

---

## Stage 13.2 — Implement Embedding Generation

### Objective

Generate embeddings for chunks in controlled batches.

### Status

Complete. The Python service now exposes the immutable provider-neutral
embedding contracts defined by ADR-0013, a deterministic fake, and an
isolated Voyage adapter for the explicit V1 profile.

### Implementation

* Added immutable `EmbeddingProfile`, `EmbeddingRequest`, `EmbeddingResult`,
  `EmbeddingInput` and `EmbeddedVector` models plus the document/query
  `EmbeddingPurpose` enum.
* Recorded the complete V1 Voyage profile — `voyage-4-large`, 1,024 float
  dimensions, unit-length normalisation, disabled truncation, both purpose
  mappings, unavailable model revision and adapter version — and derive its
  compatibility fingerprint from canonical JSON.
* Added the application-owned `Embedder` protocol, so callers have no direct
  dependency on Voyage.
* Added `ChunkEmbeddingGenerator`, which accepts an immutable
  `ChunkingResult`, rejects missing or blank chunk content, embeds controlled
  batches and preserves exact chunk-ID order across the combined result.
* Added `DeterministicFakeEmbedder`, which produces stable unit vectors from
  the profile fingerprint, purpose and input text and can surface any injected
  typed embedding failure without credentials or network access.
* Added an isolated `VoyageEmbedder` over the repository's existing `httpx`
  dependency. It sends only text and required embedding parameters, keeps
  platform IDs local, and associates returned vectors through validated
  positional order.
* Validate provider model, response count and indices, dimensions, finite
  numeric values, unit normalisation, purpose and profile compatibility before
  accepting a result. Provider-reported input-token usage is retained as
  semantic metadata.
* Translate provider failures into typed platform errors. Only rate limiting,
  timeouts and temporary provider unavailability use capped, jittered and
  bounded retries; permanent input, credential, response, dimension and
  profile failures are not retried.
* Added environment-only Voyage credentials and operational configuration.
  The secret remains a `SecretStr` and is unwrapped only by the real adapter.
* Added allowlisted embedding trace and metric attributes plus structured logs
  containing correlation/document/workspace IDs and safe operational facts.
  Raw text, vectors, credentials and provider response bodies are excluded.
* Added an isolated live Voyage contract test that runs only when
  `RUN_VOYAGE_INTEGRATION=1` and `VOYAGE_API_KEY` are deliberately supplied.
  Ordinary repository tests never make a paid provider call.

No Voyage SDK was added: the already-present `httpx` client keeps the vendor
surface inside one adapter and makes the complete wire contract testable with
an injected transport.

### Commands used

```bash
cd apps/ai
.venv/bin/ruff format app/embedding \
  tests/test_embedding_models.py \
  tests/test_embedding_configuration.py \
  tests/test_chunk_embedding_generation.py \
  tests/test_voyage_embedding.py \
  tests/test_voyage_embedding_live.py
.venv/bin/ruff check app/embedding app/settings.py app/telemetry.py \
  tests/test_embedding_models.py tests/test_embedding_configuration.py \
  tests/test_chunk_embedding_generation.py tests/test_voyage_embedding.py \
  tests/test_voyage_embedding_live.py
cd ../..
docker compose exec worker mypy app \
  tests/test_embedding_models.py \
  tests/test_embedding_configuration.py \
  tests/test_chunk_embedding_generation.py \
  tests/test_voyage_embedding.py \
  tests/test_voyage_embedding_live.py
docker compose exec worker pytest -q \
  tests/test_embedding_models.py \
  tests/test_embedding_configuration.py \
  tests/test_chunk_embedding_generation.py \
  tests/test_voyage_embedding.py \
  tests/test_voyage_embedding_live.py
make format-check-ai lint-ai typecheck-ai test-ai
docker compose config --quiet
make lint-web lint-api lint-ai
git diff --check
```

The local host virtual environment could not collect the existing Pydantic
models because its Python 3.14 dependency state differed from the repository
runtime, so authoritative tests and type checks were run inside Docker as
required by `CONTRIBUTING.md`.

### Files changed

* `.env.example`
* `compose.yaml`
* `apps/ai/app/settings.py`
* `apps/ai/app/telemetry.py`
* `apps/ai/app/embedding/__init__.py`
* `apps/ai/app/embedding/errors.py`
* `apps/ai/app/embedding/factory.py`
* `apps/ai/app/embedding/fake.py`
* `apps/ai/app/embedding/generation.py`
* `apps/ai/app/embedding/models.py`
* `apps/ai/app/embedding/protocol.py`
* `apps/ai/app/embedding/voyage.py`
* `apps/ai/pyproject.toml`
* `apps/ai/tests/test_chunk_embedding_generation.py`
* `apps/ai/tests/test_embedding_configuration.py`
* `apps/ai/tests/test_embedding_models.py`
* `apps/ai/tests/test_voyage_embedding.py`
* `apps/ai/tests/test_voyage_embedding_live.py`
* `IMPLEMENTATION_GUIDE.md`
* `docs/journal/2026-08-03-r13-s02-implement-embedding-generation.md`
* `tasks.json`

`PROJECT_JOURNEY.md` was also updated locally at the phase boundary and
remains intentionally ignored by Git.

### Verification

* Python formatting and Ruff lint passed for all 65 Python files.
* Mypy passed with no issues across all 64 checked application and test source
  files.
* The complete Python suite passed with 168 tests; the one live Voyage test
  was skipped by design because explicit opt-in credentials were absent.
* Focused contract tests cover canonical profile fingerprinting, immutability,
  purposes, batching, order and identity retention, typed failures, bounded
  retries, secrets, payload minimisation, dimensions, finite values,
  normalisation, token metadata and telemetry privacy.
* `docker compose config --quiet` passed with the new environment contract.
* Next.js, Laravel and Python lint checks passed, including all 114 Laravel
  files checked by Pint.
* `git diff --check` passed.
* The final fixed fingerprint assertion was independently calculated as
  `ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c`
  and passed local formatting/lint checks. A later attempt to repeat the
  Docker gate stalled before execution because Docker Desktop stopped
  responding; the command was interrupted and is not recorded as successful.

Vector persistence, Qdrant topology, generation activation, ingestion-worker
pipeline integration and retrieval remain deferred to their accepted later
stages.

### Acceptance criteria

* Chunk text is embedded in batches.
* Empty content is rejected.
* Dimensions are validated.
* Provider errors are retried only when appropriate.
* Correlation and document identifiers are logged.
* Secrets are loaded through environment configuration.
* Tests use a deterministic fake provider.
* Real-provider integration is tested separately.

### Commit boundary

```bash
git add .env.example compose.yaml apps/ai IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-08-03-r13-s02-implement-embedding-generation.md
git commit -m "Implement chunk embedding generation"
git tag -a phase-13-s02 \
  -m "Complete Stage 13.2: Implement Embedding Generation"
git tag -a phase-13 \
  -m "Complete Phase 13: Embeddings"
```

---

# Phase 14 — Vector Storage

## Phase objective

Persist tenant-isolated chunk vectors and metadata in a dedicated vector database.

---

## Stage 14.1 — Define Vector Database Architecture

### Objective

Confirm Qdrant as the vector store and document collection, tenancy and filtering strategy.

### Status

Completed on 2026-08-04.

### Decision

Qdrant was confirmed and accepted as the V1 vector store, reached only through a
provider-neutral `VectorStore` abstraction, before any Phase 14 implementation code,
in:

```text
docs/adr/0014-define-the-vector-storage-architecture-and-qdrant-topology.md
```

PostgreSQL remains authoritative for documents, canonical chunk text, lineage and
lifecycle — extending, not reopening, ADR 0007's position that the vector layer is a
disposable, rebuildable search projection. Two distinct generation concepts were
introduced to resolve an ambiguity in ADR 0013's "vector generation" term: an
**embedding-space generation** (platform-scoped, tied 1:1 to an `EmbeddingProfile`
fingerprint and to one Qdrant collection, created only by a consequential profile
change) and a **workspace corpus generation** (PostgreSQL-owned, per workspace,
representing the current searchable build of one workspace's corpus, extended
incrementally by ordinary document ingestion and replaced wholesale only by a
coordinated rebuild).

V1 uses a single platform-selected embedding profile across all workspaces (the ADR
0013 Voyage profile); a workspace-override resolution seam is preserved
architecturally but not built. Per-workspace corpus-generation activation supports
staged rollout and cheap rollback of any future coordinated rebuild, independent of
whether workspaces ever diverge on profile.

Collection topology is one Qdrant collection per embedding-space generation, chosen
as a deliberate architectural decision — not because Qdrant is technically incapable
of hosting multiple embedding spaces in one collection — for the lifecycle-isolation,
completeness-verification and retirement reasons recorded in the ADR. A named dense
vector (`dense`) is used from V1. The Qdrant payload is deliberately minimal
(`workspace_id`, `document_id`, `chunk_id`, `workspace_corpus_generation_id`,
`embedding_space_generation_id`), with `workspace_id`, `workspace_corpus_generation_id`
and `document_id` carrying mandatory payload indexes. Point identity is derived
deterministically from embedding-space generation, workspace, workspace corpus
generation and chunk identity. Completeness verification compares expected and
actual point identities, payload identities and vector schema against PostgreSQL's
authoritative chunk set — count equality alone is explicitly insufficient. Activation
is a PostgreSQL/domain operation and is deliberately excluded from `VectorStore`.

The full set of agreed decisions, rejected alternatives and required invariants —
including the migration-concurrency invariant governing candidate-generation
activation, and the generation lifecycle state semantics — is recorded in ADR 0014
rather than duplicated here. ADR 0014 followed an independent architectural review
rather than a direct implementation of the requester's initial preference, and went
through a further round of bounded documentation amendment before acceptance; see the
session journal for what changed and why.

### Session verification

This was an architecture-and-documentation-only session. No migrations, models,
Qdrant client code, or `VectorStore` implementation were introduced. Verification
consisted of:

* independently inspecting ADR 0006, 0007, 0010, 0011, 0012 and 0013,
  `PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md` and `tasks.json` before forming a
  recommendation, rather than starting from the requester's stated preference;
* verifying, on request, whether ADR 0006 required independent per-workspace
  embedding-profile selection in V1 or only workspace-scoped effective
  configuration — resolved as the latter, against ADR 0006's own stated
  classification purpose, before the generation/topology design was finalised;
* checking current Qdrant documentation before finalising claims about named-vector
  and collection capabilities, correcting an earlier draft's overstated framing of
  physical incapability;
* checking the accepted ADR against each Stage 14.1 acceptance criterion below.

### Acceptance criteria

* Tenant isolation strategy is explicit. — Met: `workspace_id` is a mandatory,
  indexed payload filter on every query and mutation; authorisation itself remains a
  Laravel-side concern per ADR 0006, never delegated to Qdrant.
* Payload schema is documented. — Met: the minimal V1 payload field set and its
  required indexes are recorded in ADR 0014's "Minimal Qdrant payload" and "Payload
  indexes" sections.
* Distance metric is justified. — Met: cosine distance, justified against Voyage's
  unit-length normalisation, recorded as embedding-space configuration rather than a
  permanent platform-wide constant.
* Model/dimension changes are handled. — Met: a consequential profile change
  produces a new embedding-space generation and collection; the six-step controlled
  re-embedding workflow (ADR 0013) is given physical shape by the rebuild invariant
  and per-workspace activation.
* Document deletion behaviour is defined. — Met: routine deletion is a scoped
  `VectorStore` delete against the active corpus generation; full cross-system
  deletion orchestration remains explicitly deferred, consistent with ADR 0006 and
  ADR 0007.
* Re-indexing is possible without corrupting active data. — Met: the existing
  workspace corpus generation remains active and searchable throughout any rebuild;
  activation is gated on completeness/compatibility verification and the
  migration-concurrency invariant, so a stale or incomplete candidate can never
  become active.

ADR 0014 was produced through an independent architectural review, not a direct
implementation of the requester's initial preference, followed by two rounds of
requested refinement: the first reconsidered V1 embedding-profile scope and the
collection/generation topology, introducing the embedding-space/workspace-corpus
generation split; the second was a bounded documentation amendment covering ordinary
incremental ingestion versus coordinated rebuild, the migration-concurrency
invariant, corrected Qdrant capability claims, generation lifecycle state semantics,
required payload indexes, and the completeness-verification definition. No
structural renumbering occurred in either round.

### Commit boundary

git add docs/adr/0014-define-the-vector-storage-architecture-and-qdrant-topology.md \
  docs/adr/README.md docs/journal/2026-08-04-r14-s01-define-vector-database-architecture.md \
  IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Document vector storage architecture"
git tag -a phase-14-s01 \
  -m "Complete Stage 14.1: Define Vector Database Architecture"

---

## Stage 14.2 — Add Qdrant Development Service

### Objective

Add Qdrant to Docker Compose with persistent local storage and health checks.

### Status

Complete. Qdrant 1.18.1 now runs as the local vector-store service with a
persistent named volume, a health-gated Compose dependency and an
application-facing URL resolved through Compose DNS.

### Service and network contract

The Compose service is named `qdrant` and uses the explicitly pinned image:

```yaml
image: qdrant/qdrant:v1.18.1
```

The AI API and ingestion worker receive:

```dotenv
QDRANT_URL=http://qdrant:6333
```

Both services wait for Qdrant's health check before starting. Application
services therefore use the stable `qdrant` Compose DNS name rather than the
host-published port. No Qdrant client or application persistence logic was
introduced in this infrastructure-only stage.

The REST API and dashboard are published only on the host loopback interface:

```text
http://localhost:6333/dashboard
```

The Qdrant gRPC and cluster ports are not published to the host. The local
service has no authentication, so this limited exposure is deliberate; a
production deployment must provide its own private networking and security
configuration.

### Persistence and reset behaviour

Qdrant stores local data at `/qdrant/storage`, backed by the Compose named
volume `qdrant_data`. `make down` removes containers and preserves that volume.
The deliberately destructive `make reset` removes all Compose volumes,
including Qdrant data, after its existing interactive confirmation.

The root environment example records the host port and internal service URL:

```dotenv
QDRANT_HTTP_PORT=6333
QDRANT_URL=http://qdrant:6333
```

The repeatable readiness command runs from the AI container so it proves the
same internal network path the application will use:

```bash
make qdrant-status
```

The Compose health check uses a local TCP readiness probe because the pinned
Qdrant image does not provide `curl` or `wget`. `make qdrant-status` separately
calls Qdrant's `/readyz` endpoint and verifies application-facing readiness.

### Commands executed

```bash
docker compose config --quiet
docker compose up --detach qdrant --wait --wait-timeout 120
docker compose up --detach ai --wait --wait-timeout 120
make qdrant-status
docker compose up --detach qdrant --force-recreate --no-deps \
  --wait --wait-timeout 120
make up WAIT_TIMEOUT=180
docker compose ps qdrant ai worker
make format-check lint typecheck test
git diff --check
```

The persistence check created a temporary one-dimensional cosine collection
through the Qdrant HTTP API, recreated the Qdrant container without removing
its named volume, retrieved the still-green collection and then deleted it.
The final collection list was empty, leaving no verification data behind.

### Verification

* `docker compose config --quiet` passed.
* Qdrant reported version `1.18.1` and became healthy through Compose.
* `make qdrant-status` returned `all shards are ready` from the AI container.
* The AI service and worker resolved `http://qdrant:6333`; only REST port 6333
  was published to `127.0.0.1`, while port 6334 remained internal.
* The temporary persistence-check collection survived forced container
  recreation and was removed successfully afterward.
* The complete platform started successfully with `make up WAIT_TIMEOUT=180`.
* Frontend lint and TypeScript checks passed; all 26 frontend tests passed.
* Laravel Pint passed for 114 files; all 127 tests passed with 568 assertions.
* Python Ruff formatting and linting passed, MyPy passed for 64 source files,
  and 168 tests passed; the single credential-dependent live embedding test
  was skipped as designed.
* `git diff --check` passed.

### Acceptance criteria

* Qdrant starts through Compose.
* The image version is pinned.
* Health checks pass.
* Data persists through container recreation.
* The AI service connects using Compose DNS.
* No public exposure is enabled unnecessarily.
* Reset behaviour is documented.

All Stage 14.2 acceptance criteria are satisfied. Collection provisioning,
payload schema, deterministic point identities and the `VectorStore`
implementation remain correctly deferred to Stage 14.3.

### Commit boundary

git add compose.yaml .env.example makefile README.md IMPLEMENTATION_GUIDE.md \
  docs/journal/2026-08-05-r14-s02-add-qdrant-development-service.md tasks.json
git commit -m "Add Qdrant development service"
git tag -a phase-14-s02 \
  -m "Complete Stage 14.2: Add Qdrant Development Service"

---

## Stage 14.3 — Persist Chunk Vectors

### Objective

Establish both persistence foundations required by ADR 0014: PostgreSQL as the
authoritative store for canonical chunks, embedding-profile lineage and generation
lifecycle, and Qdrant as the derived vector projection behind the provider-neutral
Python `VectorStore` boundary.

### Status

Complete.

### Corrected stage boundary

The original stage entry limited its commit boundary to `apps/ai tests`. That was too
narrow and conflicted with ADR 0014's accepted storage-ownership model and with the
R14-S01 journal, which assigns the generation state-machine representation to this
stage. The boundary is corrected before Laravel implementation begins.

This is a stage-allocation correction, not a new architectural decision. ADR 0014 is
unchanged and remains authoritative.

R14-S03 owns:

* canonical persisted chunks, including text, identity, ordinal and provenance;
* embedding-profile lineage and fingerprint persistence required by the generation
  model;
* embedding-space generation persistence and lifecycle representation;
* workspace corpus-generation persistence and lifecycle representation;
* the per-workspace active corpus-generation relationship and relational invariants;
* Laravel enums, models, migrations, relationships, factories and focused database
  tests;
* the provider-neutral Python `VectorStore` contract, immutable request/result
  models, deterministic point identity and isolated Qdrant adapter;
* idempotent Qdrant collection/index creation, bounded point upsert, scoped search,
  count, completeness verification, scoped delete and vector-space removal;
* focused Python tests, including live local-Qdrant acceptance coverage.

R14-S03 does not connect these foundations to the ingestion worker or Document
lifecycle. R14-S04 remains responsible for orchestration across authoritative chunk
persistence, embedding, Qdrant upsert, completeness verification, activation/indexing
outcomes, retries and terminal failures.

### Expected relational changes

* Add forward-safe tables for canonical chunks, embedding profiles,
  embedding-space generations and workspace corpus generations.
* Store canonical chunk text and durable identity, ordinal and provenance in
  PostgreSQL; never store raw vector arrays there.
* Represent generation lifecycles with string-backed enums and database checks, not
  boolean flags.
* Enforce one workspace and one embedding-space generation per workspace corpus
  generation through non-null foreign keys.
* Enforce at most one active workspace corpus generation per workspace at database
  level.
* Enforce that activation can reference only an available embedding-space generation
  through a transactional application operation and focused negative tests; retain
  database constraints for every invariant PostgreSQL can express without a
  cross-table trigger.
* Add model relationships, casts and factories following existing Laravel
  conventions.

### Expected Python/Qdrant changes

* Add the explicitly pinned official Qdrant Python client.
* Keep all Qdrant-specific models, filters, exceptions and transport details inside
  one adapter.
* Derive stable UUID point IDs from embedding-space generation, workspace, workspace
  corpus generation and chunk identity.
* Persist only ADR 0014's five-field payload, with keyword indexes on
  `workspace_id`, `workspace_corpus_generation_id` and `document_id`.
* Require explicit tenant and generation scope for every search, count and delete.
* Surface partial writes as typed failures and make retries safe through deterministic
  upsert identity.
* Verify completeness by identity, payload and vector schema, never by count alone.

### Relational implementation

Four forward migrations establish the PostgreSQL-owned foundation:

* `embedding_profiles` stores the immutable semantic profile snapshot and unique
  SHA-256 fingerprint;
* `embedding_space_generations` stores the Qdrant collection/vector schema lineage
  and its `BUILDING → VERIFYING → AVAILABLE → RETIRING → RETIRED` lifecycle;
* `workspace_corpus_generations` stores each workspace build and its
  `BUILDING → VERIFYING → ACTIVE → SUPERSEDED → RETIRED` lifecycle;
* `document_chunks` stores canonical text, public identity, ordinal, token count,
  chunking configuration/fingerprint and source-element provenance;
* `workspace_corpus_generation_chunks` records which canonical chunks belong to
  each corpus generation without duplicating chunk content.

Composite tenant foreign keys prevent a chunk from referring to a Document in a
different workspace and prevent corpus assignments from crossing workspace
boundaries. A partial unique PostgreSQL index permits at most one `ACTIVE` corpus
generation per workspace. A nullable composite foreign key on `workspaces` stores the
authoritative active-generation pointer and proves the referenced generation belongs
to that same workspace; `Workspace::activeCorpusGeneration()` exposes it. PostgreSQL
triggers require profile/generation dimensions to match, require an active corpus to
use an `AVAILABLE` embedding space, validate a non-null active pointer and prevent
that referenced space leaving `AVAILABLE`. The activation trigger takes a key-share
lock so the invariant also holds under concurrent retirement attempts.

Lifecycle timestamp checks, enum constraints, nonblank chunk text, positive token
counts, unique public identities and fingerprints, and immutable Eloquent models make
invalid state difficult to construct. Raw vector arrays are deliberately absent from
all PostgreSQL tables.

### Python and Qdrant implementation

The official `qdrant-client` is pinned at `1.18.0`. The provider-neutral immutable
models and `VectorStore` protocol contain no Qdrant types. The isolated adapter owns
collection/index creation, compatibility checks, minimal five-field payload
translation, deterministic UUIDv5 point identity, bounded synchronous batch upserts,
scoped search/count/delete, completeness verification and vector-space removal.

Every operational scope requires both workspace and workspace-corpus-generation
identity. Completeness compares deterministic point identities, all required payload
fields, collection schema and each returned vector's named schema; equal counts alone
cannot pass. A failure after an earlier batch succeeded raises a typed partial-write
error containing the persisted point IDs, while deterministic identity makes the
whole request safe to retry.

### Commands executed

```bash
docker compose exec -T ai uv add qdrant-client==1.18.0
docker compose exec -T ai uv run pytest \
  tests/test_vector_store_models.py tests/test_qdrant_vector_store.py
docker compose exec -T ai uv run pytest
docker compose exec -T ai uv run ruff format --check app/vector_store \
  tests/test_vector_store_models.py tests/test_qdrant_vector_store.py
docker compose exec -T ai uv run ruff check app/vector_store \
  tests/test_vector_store_models.py tests/test_qdrant_vector_store.py
docker compose exec -T ai uv run mypy app/vector_store \
  tests/test_vector_store_models.py tests/test_qdrant_vector_store.py
docker compose exec -T api php artisan test \
  --filter=VectorPersistenceFoundationTest
docker compose exec -T postgres dropdb --if-exists \
  --username=rag_platform rag_platform_r14_s03_verify
docker compose exec -T postgres createdb \
  --username=rag_platform rag_platform_r14_s03_verify
docker compose exec -T -e DB_DATABASE=rag_platform_r14_s03_verify api \
  php artisan migrate --force
docker compose exec -T -e DB_DATABASE=rag_platform_r14_s03_verify api \
  php artisan migrate:rollback --force
docker compose exec -T -e DB_DATABASE=rag_platform_r14_s03_verify api \
  php artisan migrate --force
make migrate
make format-check
make lint
make typecheck
make test
make ps
make qdrant-status
docker compose exec -T api composer validate --strict
docker compose config --quiet
git diff --check
```

The disposable PostgreSQL database was dropped after verification. Direct
PostgreSQL checks additionally confirmed rejection of an active corpus backed by a
non-available embedding space, a second active corpus in one workspace, retirement
of a referenced available embedding space, and a cross-workspace chunk. The expected
trigger functions were present after clean reapplication.

### Problems and corrections

* The first PostgreSQL verification command used a nonexistent local role (`rag`);
  inspecting the container environment identified the configured `rag_platform`
  role, after which the isolated verification completed successfully.
* The running development database contained an earlier uncommitted form of migration
  000009. Its empty R14-S03 tables were safely refreshed, and the down migration was
  made tolerant of that pre-review state before the final schema was reapplied.
* The frozen deterministic point-ID expectation was corrected to the UUID generated
  by the documented V1 namespace and canonical identity input.
* Pydantic negative tests were changed to construct fresh models because
  `model_copy(update=...)` intentionally bypasses validation.
* The final schema review strengthened the active-corpus trigger with a PostgreSQL
  key-share lock, closing a concurrent activation/retirement race.
* The final adapter review applied vector-space compatibility validation consistently
  to count, scoped delete and collection removal as well as search and upsert.

### Verification

Run focused Laravel migration/model/action tests, focused Python model and live local
Qdrant adapter tests, clean PostgreSQL migration verification, formatting, linting,
type checking, the complete repository test suites, container health and Qdrant
readiness. Record only commands and results that actually execute.

### Acceptance criteria

* Collections and required keyword indexes are created idempotently.
* Vector dimensions and existing collection compatibility are validated.
* Point IDs are deterministic UUIDv5 values derived from all required identities.
* Tenant and generation identifiers are mandatory payload and operation fields.
* Document identifiers support scoped deletion and re-indexing.
* Bounded batch upserts and typed partial failures are implemented.
* Tests verify workspace, corpus-generation and document filtering.
* PostgreSQL durably owns canonical chunks and generation lifecycle without storing
  raw vectors.
* Database constraints enforce tenant-safe lineage, lifecycle shape, one active
  corpus per workspace and active-to-available generation compatibility.

All Stage 14.3 acceptance criteria are satisfied. Connecting these completed
foundations to the ingestion worker remains Stage 14.4 work and has not begun.

### Commit boundary

git add apps/api apps/ai IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-08-05-r14-s03-persist-chunk-vectors.md
git commit -m "Persist canonical chunks and document vectors"
git tag -a phase-14-s03 \
  -m "Complete Stage 14.3: Persist Chunk Vectors"

---

## Stage 14.4 — Verify and Close the Vector Storage Foundation

### Objective

Verify the completed PostgreSQL and Qdrant foundations against ADR-0014, record
the final Phase 14 implementation boundary, and explicitly defer cross-service
ingestion orchestration to Phase 15.

### Status

Complete.

### Corrected stage boundary

R14-S04 was originally scoped as "Complete Ingestion Pipeline." Architecture
review before implementation began found that scope entirely blocked: connecting
upload, queue consumption, extraction, normalisation, chunking, embedding and
vector persistence into one working pipeline requires a cross-service
orchestration contract between Laravel and the Python ingestion worker that no
accepted ADR settles. ADR 0009 authenticates only the initial
`QUEUED → PROCESSING` claim and explicitly scopes itself no further; ADR 0014
persists canonical chunks and vectors but does not decide how Python's
processing results reach those stores, or how completion and failure become
authoritative Document lifecycle transitions.

This is a stage-allocation correction, not a new architectural decision. ADR
0014 is unchanged and remains authoritative. R14-S04 is rescoped to verification
and closure of the storage foundation R14-S01 through R14-S03 already
completed. It introduces no new application implementation.

R14-S04 does not decide or implement ingestion orchestration. Phase 15
(Stage 15.1 architecture, Stage 15.2 implementation) owns, in full:

* an uploaded Document reaching `INDEXED`;
* authoritative `PROCESSING → INDEXED`/`FAILED` transitions;
* authenticated callback/result reporting from the Python worker to Laravel;
* canonical chunk transfer from Python to Laravel;
* end-to-end observability across the pipeline;
* processing-attempt and callback idempotency;
* duplicate-message resumption;
* SQS acknowledgement, redelivery and dead-letter behaviour;
* complete workspace/Document/generation context propagation;
* end-to-end ingestion tests;
* initial embedding-space and workspace-corpus-generation provisioning.

None of the above is represented as complete by this stage.

### Verified implementation boundary

The Phase 14 foundation satisfies ADR 0014 without adding ingestion
orchestration:

* PostgreSQL owns canonical chunk text, public identity, ordinal, token count,
  chunking configuration and source-element provenance.
* PostgreSQL owns immutable embedding-profile lineage, embedding-space
  generation lifecycle, workspace corpus generation lifecycle and each
  workspace's active corpus pointer. Database constraints and triggers enforce
  tenant-safe relationships, compatible dimensions, usable active embedding
  spaces and at most one active corpus generation per workspace.
* PostgreSQL has no raw-vector column. Qdrant contains the disposable,
  rebuildable vector projection.
* Python exposes a provider-neutral `VectorStore`; Qdrant client types and
  behaviour remain isolated in its adapter. Activation is deliberately absent
  from that boundary.
* Vector operations require explicit workspace and workspace-corpus-generation
  scope. Deterministic UUIDv5 point identities make repeated upserts
  idempotent.
* Collection creation and the `workspace_id`,
  `workspace_corpus_generation_id` and `document_id` payload indexes are
  idempotent. Completeness verification checks expected identities, payload
  values and vector schema rather than accepting equal counts alone.
* The V1 Qdrant collection uses the named `dense` vector with 1,024 dimensions
  and cosine distance, matching ADR 0014.

No application code changed in this closure stage. Its work was to verify the
accepted implementation, record the evidence and close the phase boundary.

### Commands executed

Focused persistence verification:

```bash
docker compose exec -T api php artisan test \
  --filter=VectorPersistenceFoundationTest
docker compose exec -T ai uv run pytest \
  tests/test_vector_store_models.py \
  tests/test_qdrant_vector_store.py
```

A disposable `rag_platform_r14_s04_verify` PostgreSQL database was created and
used for a full migration, full rollback and full reapplication. Direct catalog
queries then verified the authoritative tables, indexes, lifecycle triggers,
`dense` vector-name default and absence of PostgreSQL vector columns. The
database was dropped after verification.

Qdrant durability was tested with a disposable
`r14-s04-persistence-verification` collection: the collection was provisioned
with the ADR 0014 schema and required payload indexes, a point was written, the
Qdrant container was force-recreated without removing its volume, and the
collection schema, indexes, payload and point were verified after restart. The
temporary collection was then removed.

Repository gates:

```bash
make format-check
make lint
make typecheck
make test
make ps
make qdrant-status
docker compose config --quiet
docker compose exec -T api composer validate --strict
```

### Verification results

* Focused Laravel vector-persistence tests: 19 passed, 59 assertions.
* Focused Python vector-store tests: 13 passed against local Qdrant.
* Clean PostgreSQL migration, rollback and reapplication: passed.
* PostgreSQL authority and invariant inspection: passed; the partial unique
  active-corpus index and four lifecycle/relationship triggers were present,
  and no raw-vector storage was found.
* Qdrant persistence across container recreation: passed; the named `dense`
  vector remained 1,024-dimensional with cosine distance, all three payload
  indexes remained present and the test point and five-field payload survived.
* Frontend tests: 7 files and 26 tests passed.
* Laravel suite: 146 tests passed, 627 assertions.
* Python suite: 181 tests passed; the credential-dependent live Voyage test was
  skipped as designed.
* ESLint, Pint (131 files), Ruff formatting and lint, TypeScript and MyPy (73
  source files): passed.
* Compose validation, Composer validation, container health and Qdrant shard
  readiness: passed.

No defects were found. No corrective application change was required.

### Acceptance criteria

* PostgreSQL is authoritative for canonical chunks and generation lifecycle.
* Qdrant is a disposable, rebuildable vector projection.
* Deterministic point identity makes repeated vector upserts safe.
* Vector operations require explicit workspace and workspace-corpus-generation
  scope.
* Qdrant collection and payload-index provisioning is idempotent.
* Completeness verification compares identities, payload values and vector
  schema, not counts alone.
* PostgreSQL stores active corpus state but never raw vectors.
* Migrations, Qdrant persistence tests, service health and repository-wide
  checks pass.
* Phase 14 documentation accurately records the completed storage boundary.

### Commit boundary

```bash
git add PROJECT_ROADMAP.md IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-08-05-r14-s04-verify-and-close-vector-storage-foundation.md
git commit -m "Verify vector storage foundation"
git tag -a phase-14-s04 \
  -m "Complete Stage 14.4: Verify and Close the Vector Storage Foundation"
git tag -a phase-14 \
  -m "Complete Phase 14: Vector Storage"
```

---

# Phase 15 — Ingestion Orchestration

## Phase objective

Decide and implement the authenticated, idempotent cross-service contract that
carries a Document from its existing `PROCESSING` claim (ADR 0009) through
canonical chunk persistence, embedding and vector persistence (ADR 0013, ADR
0014) to an authoritative `INDEXED` or `FAILED` outcome, closing the gap
between Phase 14's completed storage foundation and Phase 16's retrieval work.

## Phase 15 insertion note

Recorded 2026-08-05, arising from architecture review of R14-S04 before its
implementation began. R14-S04's original "Complete Ingestion Pipeline" scope
required deciding a cross-service orchestration contract — authenticated
worker callbacks, canonical chunk transfer, completion/failure reporting,
`event_id` idempotency, SQS acknowledgement, Laravel/Python ownership,
authoritative lifecycle transitions, duplicate-delivery behaviour and initial
generation provisioning — that no prior ADR settles. ADR 0009 authenticates
only the initial `QUEUED → PROCESSING` claim and explicitly scopes itself no
further ("if more internal principals or permission scopes appear, this
narrow protocol should be replaced or superseded rather than expanded into an
improvised general identity system"); ADR 0014 persists canonical chunks and
vectors but does not decide how Python's processing results reach those
Laravel-owned and Qdrant-owned stores.

Given the depth of that gap — both an architecture decision and a full
implementation are required before a Document can reach `INDEXED` — ingestion
orchestration is promoted to its own phase, inserted between Vector Storage
(Phase 14) and Retrieval (Phase 16), rather than folded into a single stage of
either. Every requirement originally listed under R14-S04's "Complete
Ingestion Pipeline" scope moves here in full; R14-S04 itself is rescoped to
verification and closure of the already-completed storage foundation (see
Stage 14.4 above). No completed phase numbering changes; every phase from the
original Phase 15 (Retrieval) onward shifts by one to make room.

---

## Stage 15.1 — Define End-to-End Ingestion Orchestration and Worker Result Contracts

### Objective

Decide ADR 0015: the authenticated, idempotent orchestration contract between
Laravel and the Python ingestion worker that carries a Document from its
existing `PROCESSING` claim (ADR 0009) through canonical chunk persistence,
embedding and vector persistence (ADR 0013, ADR 0014) to an authoritative
`INDEXED` or `FAILED` outcome.

### Status

Completed on 2026-08-06.

### Decision

ADR 0015 was accepted before any Phase 15 implementation code, in:

```text
docs/adr/0015-define-end-to-end-ingestion-orchestration-and-worker-result-contracts.md
```

It supersedes ADR 0009 **in part**: ADR 0009's cryptographic primitives — HMAC-
SHA256, the key-ring model, freshness/replay handling, constant-time
comparison, safe logging — are carried forward unchanged, exactly as ADR
0009's own text anticipated ("if more internal principals or permission
scopes appear, this narrow protocol should be replaced or superseded rather
than expanded"). What is superseded is its claim-only scope: a new,
purpose-scoped `v2` signature protocol (`ingestion.claim`,
`ingestion.chunks.submit`, `ingestion.complete`, `ingestion.fail`) replaces
it, with `v1` retained only as a bounded, claim-only, temporary migration
accommodation that Stage 15.2 must remove and test the removal of, not a
permanent second protocol. Service ownership is unchanged (Laravel owns
Postgres, Documents, lifecycle, canonical chunks and generation lifecycles;
Python owns extraction, normalisation, chunking, embedding, `VectorStore`
and Qdrant), and the existing Document lifecycle is unchanged — no
`INDEXING` state is introduced, since the completion contract is designed as
one atomic Laravel transaction with no durable midpoint for such a state to
describe.

The central new concept is a **processing lease**: a Laravel-owned,
time-bounded grant of current authority over one `event_id`'s in-progress
work, layered on top of the durable `event_id` ADR 0008/0009 already
established. A claim resolves to one of five outcomes — proceed, owned by
another live worker, already completed, permanently failed, or reclaimable
— so a crashed claimant never permanently strands a Document while
preventing two live workers from ever both believing themselves authorised
to complete the same attempt. Chunk **ownership** and submission
**authority** are kept distinct: a chunk belongs durably to the `event_id`
it was recorded against for the life of that attempt, while only the
currently valid lease authorises submitting or mutating chunks right now —
so a successor worker resuming a reclaimed attempt can resume it without
retransmitting or duplicating chunks a predecessor already persisted.
Canonical chunk submission (bounded, repeatable, text and provenance, never
vectors) and final completion (small and referential — manifests, digests,
counts, generation identities, Qdrant verification evidence, never chunk
text or vectors) are separate contracts. Laravel independently recomputes
the chunk count and manifest digest from what it has actually persisted for
that `event_id` and rejects completion on disagreement; it records Python's
Qdrant-side evidence honestly as an authenticated assertion, without
claiming to have independently inspected Qdrant itself.

Lease renewal and SQS visibility extension are governed by one coordinated
timing policy but are explicitly not treated as one atomic distributed
transaction — the ADR defines both partial-success outcomes (lease renewed
without visibility extended, and the reverse) and requires a worker unable
to confirm both are healthy to stop making authoritative callbacks
immediately. Failure classification is domain-owned: only a failure
Python's own processing domain (extraction, chunking, embedding, Qdrant)
classifies as terminal, with a currently valid lease still held, may become
`ingestion.fail`; control-plane exhaustion (an unreachable callback
endpoint, a lease or visibility renewal failure, uncertainty over whether a
prior callback landed) must never be reclassified as a Document processing
failure — it is left to redelivery, fresh-claim status discovery, and DLQ
terminal reconciliation instead. DLQ arrival never mutates PostgreSQL
directly; it requires eventual, idempotent, `event_id`-keyed reconciliation
to an authoritative Laravel outcome. The platform's embedding-space
generation is provisioned explicitly and idempotently at setup, never as a
side effect of the first upload; a workspace's first corpus generation is
created lazily under Laravel's authority at claim time and activated only
after verification, per ADR 0014's existing lifecycle rules.

ADR 0015 also records the roadmap citation clarification this stage's
"Required roadmap clarification" anticipated: ADR-0013's and ADR-0014's
existing "Phase 15" references now resolve to Phase 16, and their
later-phase references shift by one in the same way, following the Phase 15
insertion — a citation correction only, recorded inside ADR-0015 itself
rather than by rewriting either accepted ADR.

The full set of agreed decisions, rejected alternatives, and required
invariants — including the exact `v2` canonical string-to-sign, a verified
normative test vector, and every architectural invariant governing the
lease, the two contracts, and DLQ reconciliation — is recorded in ADR 0015
rather than duplicated here. ADR 0015 went through an independent
architectural review, a first drafting pass, and two further rounds of
bounded amendment (queue/lease coordination and `v1`/`v2` migration policy;
then provisional-chunk reclaim semantics, non-atomic renewal, and narrowed
retry-exhaustion semantics) before acceptance; see the session journal for
what changed in each round.

### Session verification

This was an architecture-and-documentation-only session. No migrations,
models, HTTP endpoints, or worker code were introduced. Verification
consisted of:

* independently inspecting ADR 0007, 0008, 0009, 0010, 0011, 0012, 0013 and
  0014, `PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md` and `tasks.json`
  before forming a recommendation, rather than starting from a stated
  preference;
* computing and independently verifying the `v2` signature test vector
  (reproducing ADR 0009's own published `v1` vector first, to confirm the
  canonicalisation method, before extending it to the six-field `v2` form)
  rather than asserting unverified numbers into an ADR intended to be
  accepted;
* checking the accepted ADR against each Stage 15.1 acceptance criterion
  below.

### Acceptance criteria

* Every new Python-to-Laravel write is authenticated and idempotent. — Met:
  purpose-scoped `v2` HMAC signing plus `event_id`/lease-scoped idempotent
  acceptance for chunk submission, completion and failure reporting.
* Canonical chunk transfer does not require direct Python database access.
  — Met: chunk submission is an authenticated HTTP contract; Python never
  writes to Laravel-owned tables directly, consistent with ADR 0009's
  existing rejection of that alternative.
* Completion and failure reporting map to explicit, authoritative Document
  lifecycle transitions. — Met: only Laravel performs `INDEXED`/`FAILED`
  transitions; Python requests them, purpose-scoped and lease-authorised.
* Duplicate SQS delivery at every stage of the pipeline is proven safe, not
  merely assumed. — Met: the processing lease's five outcomes, `event_id`-
  scoped chunk ownership, and ack-only-after-durable-outcome together cover
  claim, mid-processing, and post-completion redelivery explicitly.
* Initial generation provisioning is defined and does not depend on manual
  operator intervention. — Met: explicit, idempotent platform bootstrap for
  the embedding-space generation; lazy, Laravel-authoritative provisioning
  for a workspace's first corpus generation, resolved at claim time.
* Each of Stage 15.2's acceptance criteria is traceable to a specific
  decision in this ADR. — Met: Stage 15.2's acceptance criteria (below) are
  each satisfied by a named ADR-0015 contract, invariant, or coordination
  rule.

ADR 0015 was produced through an independent architectural review, not a
direct implementation of the requester's initial preference, followed by two
rounds of requested, bounded refinement after the first full draft. No
structural renumbering occurred in either round; both were additive
clarifications to an architecture already approved in principle.

### Commit boundary

git add docs/adr/0015-define-end-to-end-ingestion-orchestration-and-worker-result-contracts.md \
  docs/adr/README.md docs/journal/2026-08-06-r15-s01-define-end-to-end-ingestion-orchestration-and-worker-result-contracts.md \
  IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Document end-to-end ingestion orchestration architecture"
git tag -a phase-15-s01 \
  -m "Complete Stage 15.1: Define End-to-End Ingestion Orchestration and Worker Result Contracts"

---

## Stage 15.2 — Define Ingestion Publication and Recovery Semantics

### Objective

Decide ADR 0016: the explicit provisional-to-published vector lifecycle,
dual retrieval-visibility gate, open/sealed chunk-attempt recovery model,
and complete purpose-scoped worker protocol required to close the
correctness gaps a post-acceptance review of ADR-0015 found before Stage
15.3 implementation began.

### Status

Completed on 2026-08-06.

### Decision

ADR 0016 was accepted before any Stage 15.3 implementation code, in:

```text
docs/adr/0016-define-ingestion-publication-and-recovery-semantics.md
```

It supersedes ADR 0015 **in part** — provisional-vector visibility,
cross-worker chunk recovery, the complete purpose-scoped worker protocol,
and any wording implying the workflow is observationally atomic as one unit
— and ADR 0014 **in part**, narrowly, for its fixed five-field minimal
Qdrant payload, which gains exactly two fields: `event_id` and a
publication-status marker. Nothing else in either ADR is reopened: service
ownership, the unchanged Document lifecycle, the processing lease's five
claim outcomes, the `v1`-to-`v2` migration policy, and ADR-0014's collection
topology, `VectorStore` boundary and generation model all stand exactly as
previously accepted.

The central addition is an explicit, twelve-step publication saga — chunk,
submit, seal, Laravel validates and locks the seal, embed, write Qdrant
points as provisional, verify the provisional set, Laravel durably
authorises publication bound to the exact evidence it approved, publish,
independently verify the published set is complete, report completion, and
only then does Laravel transition the Document to `INDEXED` — described
throughout as an idempotent, recoverable saga of individually transactional
steps, never as one distributed transaction spanning PostgreSQL and Qdrant.
Retrieval visibility requires **both** a Qdrant point marked published
**and** independent PostgreSQL confirmation the Document is `INDEXED`;
neither gate substitutes for the other. Because the publication mutation
itself is a distributed Qdrant operation that can partially succeed,
`ingestion.complete` is reachable only after independent post-publication
verification confirms the complete published set against the exact evidence
Laravel authorised — count equality alone is never sufficient, and
authorisation is bound to that evidence so it can never be reused, even by
a legitimate successor, against a differing point set.

Chunk attempts are explicitly open or sealed: an open attempt is never a
valid resume source and is reset, `event_id`-scoped and idempotently, if its
lease is lost before sealing; a sealed attempt is immutable and resumable by
a successor through a new, narrowly-scoped, lease-gated contract,
`ingestion.attempt.resume`. Sealing must complete before embedding begins,
so a crash after sealing lets a successor skip re-extraction and
re-chunking — work that, unlike chunking's own determinism, cannot actually
be reproduced by recomputation, since fresh per-run extraction-element
identity (ADR 0010) propagates into chunk identity (ADR 0011) across
independent extraction runs of the same source content. The complete `v2`
worker-protocol purpose list is now eight: `ingestion.claim`,
`ingestion.lease.renew`, `ingestion.chunks.submit`, `ingestion.chunks.seal`,
`ingestion.attempt.resume`, `ingestion.publication.authorise`,
`ingestion.complete`, `ingestion.fail` — each independently purpose-signed,
so a signature valid for one can never authorise another. Reclaim reset and
DLQ terminal reconciliation are unified under one `event_id`-scoped,
idempotent cleanup policy that never deletes sealed or published data
outside authoritative attempt state permitting it. Publication authorisation
and completion are recorded in the business-audit layer, not telemetry
alone, and both revalidate Document eligibility before acting so a race with
deletion resolves in favour of deletion, cross-referencing ADR-0006/ADR-0007's
already-deferred deletion orchestration rather than redesigning it here.

The full set of agreed decisions, rejected alternatives and required
invariants — including the normative `v2` test vectors, the exact
post-publication verification checklist, and every recovery outcome for the
crash windows this saga introduces — is recorded in ADR 0016 rather than
duplicated here. ADR 0016 went through an independent post-acceptance
review of ADR-0015, a first full draft, and one further round of bounded
amendment (adding explicit post-publication verification and evidence-bound
publication authorisation, after review found that a partially-succeeded
Qdrant publication mutation was not otherwise excluded from reaching
`ingestion.complete`) before acceptance; see the session journal for what
changed.

### Session verification

This was an architecture-and-documentation-only session. No migrations,
models, HTTP endpoints, or worker code were introduced. Verification
consisted of:

* independently inspecting ADR 0010, 0011, 0014 and 0015, the completed
  Phase 14 implementation, the prior R15-S02 planning stub, and `tasks.json`
  before forming a recommendation;
* tracing the extraction/chunking identity chain (ADR 0010's fresh
  per-run `ExtractedElement` UUIDs through ADR 0011's `NormalisedElement`-
  derived chunk identity) to confirm cross-worker chunk resumption by
  recomputation was genuinely unachievable, not merely under-specified;
* computing and independently verifying a new normative `v2` test vector for
  `ingestion.lease.renew`, using the same method as ADR-0015's own vectors;
* checking the accepted ADR against each Stage 15.2 acceptance criterion
  below.

### Acceptance criteria

* The provisional-to-published vector lifecycle and dual retrieval-
  visibility gate are explicitly defined. — Met.
* Post-publication completeness verification is required before
  `ingestion.complete`, and count equality alone is insufficient. — Met.
* Publication authorisation is bound to immutable, approved evidence, never
  reusable against a differing point set. — Met.
* The open/sealed chunk-attempt recovery model is defined, including why
  cross-worker recomputation is not achievable and how the resume contract
  closes that gap. — Met.
* The complete `v2` purpose list, including lease renewal and resume, is
  defined with purpose-bound signing for each. — Met.
* Reclaim reset and DLQ terminal reconciliation are unified under one
  `event_id`-scoped, idempotent cleanup policy. — Met.
* The ADR-0014 payload amendment is narrow and justified, with index
  recommendations derived from actual operations, not habit. — Met.
* Document deletion during publication is cross-referenced to ADR-0006/
  ADR-0007's existing deferral rather than redesigned. — Met.

ADR 0016 was produced through an independent post-acceptance review, not a
reopening of ADR-0015's approved-in-principle architecture, followed by one
round of requested, bounded refinement after the first full draft closing
the partial-publication verification gap. No structural renumbering
occurred; the refinement was an additive clarification.

### Commit boundary

git add docs/adr/0016-define-ingestion-publication-and-recovery-semantics.md \
  docs/adr/README.md docs/journal/2026-08-06-r15-s02-define-ingestion-publication-and-recovery-semantics.md \
  PROJECT_ROADMAP.md IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Document ingestion publication and recovery semantics"
git tag -a phase-15-s02 \
  -m "Complete Stage 15.2: Define Ingestion Publication and Recovery Semantics"

---

## Stage 15.3 — Implement End-to-End Ingestion Orchestration

### Objective

Implement the accepted ADR-0015 and ADR-0016 orchestration contract
together, completing the upload-to-`INDEXED` ingestion path before
Retrieval (Phase 16) begins.

### Status

Completed on 2026-08-06.

### Implementation

Laravel now owns a durable, event-scoped ingestion attempt that extends the
existing `ingestion_event_claims` record with its authoritative Workspace,
Document, lease generation, open/sealed state, generation identities,
publication evidence and terminal outcome. Lease tokens are returned once to
the worker and stored only as SHA-256 digests. Every mutating operation locks
and revalidates the attempt, Document, Workspace, generation context and
current lease before changing authoritative state.

The internal worker protocol now implements all eight ADR-0016 `v2` purposes:

```text
ingestion.claim
ingestion.lease.renew
ingestion.chunks.submit
ingestion.chunks.seal
ingestion.attempt.resume
ingestion.publication.authorise
ingestion.complete
ingestion.fail
```

Signatures bind the timestamp, HTTP method, request path, body digest,
`event_id` and exact operation purpose. The Laravel and Python implementations
validate the same repository-owned canonicalisation fixtures and normative
vectors. Version `v1` is rejected. Key-ring rotation, timestamp freshness,
constant-time comparison and privacy-safe failure logging remain intact.

Canonical chunks are submitted in bounded batches and persisted in
PostgreSQL with attempt identity, ordinal, text digest and structured source
provenance. Database uniqueness prevents duplicate ordinals or public
identities within an attempt. Repeated identical submissions are idempotent;
conflicting data is rejected. Sealing recomputes the authoritative manifest
from PostgreSQL and makes the attempt immutable. A successor holding a fresh
lease can page through a sealed attempt without repeating extraction or
chunking.

The Python worker now performs the complete recoverable saga: claim, extract,
normalise, chunk, submit, seal, embed, provision and write provisional Qdrant
points, verify them, request evidence-bound publication authorisation,
publish, independently verify the published set, report completion, and only
then acknowledge SQS. Laravel alone activates the corpus generation, updates
the Workspace active-generation pointer and transitions the Document to
`INDEXED`. Permanent source-processing failures are reported through the same
lease-gated protocol; infrastructure and control-plane uncertainty remains
retryable and never becomes a false Document failure.

One heartbeat cadence renews the Laravel processing lease and extends SQS
visibility as two independent operations. If either cannot be confirmed, the
worker stops authoritative work. Open expired attempts are reclaimed by
resetting their attempt-scoped chunks and cleaning their provisional vector
projection. Sealed attempts are preserved and resumed. Duplicate delivery,
terminal discovery and completion reporting are all keyed by `event_id`, and
only authoritative `INDEXED` or `FAILED` outcomes permit acknowledgement.
A dedicated `python -m app.worker --dlq-once` mode provides the deployment
hook for applying the same event-scoped reconciliation rules to the dead-letter
queue.

Qdrant payloads now carry `event_id` and `publication_status` alongside the
ADR-0014 tenant, generation, Document and chunk identities. `VectorStore`
continues to isolate all Qdrant-specific behaviour, including deterministic
point identity, provisional cleanup, idempotent publication and completeness
verification. Search always requires explicit Workspace and corpus-generation
scope and filters to published points. Raw vectors are never stored in
PostgreSQL, and Python never writes directly to it.

An idempotent `ingestion:provision-embedding-space` Artisan command creates
the accepted V1 Voyage embedding profile and available embedding-space
generation explicitly. The first Workspace corpus generation is then created
lazily, under Laravel authority, during a valid claim. Business audit records
capture publication authorisation and terminal outcomes without storing
document content, vectors, credentials or lease tokens.

### Commands executed

```bash
docker compose exec -T api ./vendor/bin/pint
docker compose exec -T api ./vendor/bin/pint --test
docker compose exec -T api php artisan test
docker compose exec -T ai uv run ruff format app tests
docker compose exec -T ai uv run ruff check .
docker compose exec -T ai uv run mypy app tests
docker compose exec -T ai uv run pytest
docker compose exec -T web npm test
docker compose exec -T web npm run lint
docker compose exec -T web npx tsc --noEmit
docker compose exec -T -e NODE_ENV=production web npm run build
docker compose config --quiet
docker compose build api publisher ai worker
docker compose up --detach --force-recreate --no-deps api publisher ai worker
docker compose up --detach --wait --wait-timeout 180
docker compose ps
docker compose exec -T api php artisan ingestion:provision-embedding-space
git diff --check
```

A disposable PostgreSQL database was also migrated from empty through every
migration, including the new orchestration foundation, and then removed. Its
attempt nullability, foreign keys, check constraints and indexes were inspected
directly. The Laravel and Python embedding configuration fingerprints were
compared at runtime and both resolved to:

```text
ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c
```

### Verification results

* Laravel: 155 tests passed with 668 assertions.
* Python: 198 tests passed; the existing credential-dependent live Voyage
  test was skipped as designed.
* Frontend: 26 tests passed across seven files.
* Pint passed across 151 files; Ruff formatting/lint, MyPy across 80 source
  files, ESLint and TypeScript all passed.
* The production Next.js build passed with 11 routes. An initial invocation
  inherited a nonstandard host `NODE_ENV`; explicitly supplying the required
  production value corrected the environment and the build passed without a
  code change.
* Clean PostgreSQL migration and direct constraint inspection passed.
* Shared PHP/Python canonicalisation, HMAC and point-identity conformance
  vectors passed.
* Compose validation, rebuilt service images and all container health checks
  passed, including the ingestion worker.
* Provisioning was idempotent and the cross-language embedding fingerprint
  matched exactly.
* `git diff --check` passed.

### Acceptance criteria

* An uploaded Document reaches `INDEXED`. — Met through the tested complete
  orchestration saga and authoritative Laravel completion transaction.
* Authoritative `PROCESSING → INDEXED`/`FAILED` transitions are implemented
  per ADR-0015 and ADR-0016. — Met; Python requests outcomes, while Laravel
  validates and performs both terminal transitions.
* Authenticated callback/result reporting from the Python worker to Laravel
  is implemented for all eight `v2` purposes, each independently
  purpose-signed. — Met, including cross-purpose rejection tests.
* Canonical chunk transfer, sealing, and sealed-attempt resume are
  implemented without direct Python database access. — Met.
* The provisional-to-published vector lifecycle, evidence-bound publication
  authorisation, and post-publication completeness verification are
  implemented; `ingestion.complete` is unreachable until verification
  passes. — Met.
* Retrieval-visibility gating (published Qdrant point and PostgreSQL
  `INDEXED`, both required) is implemented and enforced wherever vectors
  are queried. — Met at the storage boundary; Python search filters to
  published points, while Phase 16 retrieval must independently confirm the
  authoritative PostgreSQL `INDEXED` gate before exposing results.
* Each pipeline stage is observable end to end. — Met through trace-context
  propagation, privacy-safe structured telemetry and durable business audit
  events for publication and terminal outcomes.
* Processing-attempt, lease-renewal, and callback idempotency hold under
  retry. — Met.
* Duplicate-message resumption does not duplicate chunks, vectors, or
  lifecycle transitions. — Met through attempt-scoped database uniqueness,
  deterministic point identity and idempotent terminal actions.
* SQS acknowledgement, redelivery and dead-letter behaviour are implemented
  and verified. — Met; only authoritative terminal outcomes acknowledge, and
  the DLQ reconciliation entry point uses the same attempt policy.
* Reclaim reset and DLQ terminal reconciliation share one implemented,
  `event_id`-scoped cleanup policy. — Met.
* Workspace, Document and generation context survives every stage of the
  pipeline. — Met and revalidated by Laravel at every authoritative mutation.
* End-to-end ingestion tests exist and pass, including sealed-attempt
  resumption and partial-publication recovery. — Met.
* Initial embedding-space and workspace-corpus-generation provisioning is
  implemented. — Met through explicit idempotent platform provisioning and
  lazy, locked Workspace generation creation.

### Commit boundary

```bash
git add .env.example compose.yaml apps/api apps/ai \
  contracts/http/ingestion-worker IMPLEMENTATION_GUIDE.md tasks.json \
  docs/journal/2026-08-06-r15-s03-implement-end-to-end-ingestion-orchestration.md
git commit -m "Complete end-to-end document ingestion orchestration"
git tag -a phase-15-s03 \
  -m "Complete Stage 15.3: Implement End-to-End Ingestion Orchestration"
git tag -a phase-15 \
  -m "Complete Phase 15: Ingestion Orchestration"
```

---

# Phase 16 — Retrieval

## Phase objective

Retrieve relevant, tenant-safe source chunks for a user query.

## Phase 16 restructuring note

Recorded 2026-08-03. Phase 16 was originally scoped as four stages (contract,
semantic implementation, evaluation, enhancements). Before any Phase 16
implementation began, this was restructured to seven stages so that document
freshness/archival semantics and the evaluation/quality-gate harness are
architectural decisions made explicitly, with their own ADRs, rather than
implicit assumptions inside the retrieval contract or an under-scoped
evaluation task. No completed stage numbering was changed by this
restructuring — Phase 16 had not yet started.

---

## Stage 16.1 — Define Document Versioning and Temporal Authority

### Objective

Resolve ADR-0007's deliberately deferred versioning question: define what a
version is, how versions relate across time within one stable identity, and
what "authoritative at time T" honestly means, so that retrieval (Stage
16.2) and evaluation (Stage 16.5) have a settled definition to build
eligibility against. Originally scoped narrower, as "Document Freshness and
Archival Policy"; broadened before drafting began once independent review
established that retrieval's actual requirement — `CURRENT`, `VALID_AT_DATE`
and `COMPARE` — is a versioning decision, not an archival flag, and that
ADR-0007 had already named this exact shape as the anticipated future
decision.

### Status

Completed on 2026-08-07.

### Decision

ADR 0017 was accepted before any Stage 16.2 implementation or architecture
work began, in:

```text
docs/adr/0017-define-document-versioning-and-temporal-authority.md
```

It supersedes ADR-0007 **in part** — specifically its *"no versioning for
now"* position, the decision that every upload is an independent, unrelated
Document with no notion of supersession. Everything else ADR-0007 decided
stands unchanged: the technical processing lifecycle
(`UPLOADING → UPLOADED → QUEUED → PROCESSING → INDEXED`, `FAILED` from
`PROCESSING`, `DELETING → DELETED`), the three-layer relational/object/vector
separation, deletion as an asynchronous auditable lifecycle, and the
principle that a Document is an identity and a lifecycle, not a file. No
other accepted ADR is reopened; ADR-0016's dual retrieval-visibility gate
(published Qdrant point **and** PostgreSQL `INDEXED`) remains the
unconditional structural prerequisite for retrievability, with this ADR's
governance/temporal model applying as a third, additive gate on top of it.

The central additions are: `DocumentFamily` as the stable identity every
Document belongs to (exactly one, no optionality, requiring a backfill
migration for existing Documents); explicit, linear, immutable version
lineage (a chain, not a branching graph); and one derivation rule — `CURRENT`
is `VALID_AT_DATE` evaluated at the present moment — computed at query time
from three explicit, non-derived per-version timestamps
(`effective_from`, `approved_at`, `withdrawn_at`), never a stored,
scheduler-flipped flag. `authority_start = max(effective_from, approved_at)`
is the moment a version can first become authoritative — requiring both its
scheduled date to have arrived and genuine governance approval, so a delayed
approval can never retroactively authorise dates before it happened. A
version "attains authority" only if it reaches `authority_start` while still
governance-eligible, and — the correction that closes the model's central
failure mode — only if doing so does not move authority history backward
relative to the family's own declared lineage: a predecessor can never
attain authority after its own named successor already has, enforced by
rejecting the offending approval outright at approval time, with the
derivation itself independently re-asserting the same rule as a defence-in-
depth backstop. A predecessor's authority window closes permanently the
moment its successor attains authority, and a later withdrawal of that
successor never reopens it — no resurrection of a superseded predecessor.
Withdrawal is forward-only throughout; historical `VALID_AT_DATE` answers
never change because of a later withdrawal.

Governance state (`DRAFT → APPROVED → WITHDRAWN`, `WITHDRAWN` reachable only
from `APPROVED`) is a deliberately minimal model, orthogonal to the existing
technical processing lifecycle — a version can be `INDEXED` while still
`DRAFT`, mirroring ADR-0014's precedent of separate, coordinated state
machines rather than one collapsed machine. An unapproved `DRAFT` that is
abandoned is not a `WITHDRAWN` transition; it was never governance-eligible
and never attains authority regardless. Correcting an already-recorded
`approved_at` or `withdrawn_at` requires a permission distinct from ordinary
approve/withdraw authority, an explicit recorded reason, and its own
business-audit record — ordinary transitions always record the actual
current time and are never backdated directly.

Document applicability to organisational locations is modelled as a single,
generic, self-referencing `OrganisationalLocation` hierarchy (not hard-coded
`Region`/`Site` tables), recorded as an immutable snapshot on each version at
creation time; `DocumentFamily` holds only a mutable creation-time default
used to seed new versions, never consulted when resolving an existing
version's eligibility.

The full set of agreed decisions, worked examples, rejected alternatives and
architectural invariants — including the three structural constraints
unambiguous succession depends on (`effective_from` uniqueness,
`authority_start` uniqueness, and lineage-monotonic ordering) — is recorded
in ADR 0017 rather than duplicated here. ADR 0017 went through an
independent architectural review of the proposed Phase 16 direction before
any drafting began, a first full draft, and three rounds of bounded
amendment (correcting a predecessor-resurrection derivation bug and moving
applicability to per-version snapshots; introducing `authority_start` to
close a retroactive-authorisation-through-late-approval gap and adding
backdated-correction guardrails; and adding the lineage-monotonic ordering
invariant plus correcting `DRAFT`-versus-`WITHDRAWN` wording) before
acceptance; see the session journal for what changed at each round.

### Session verification

This was an architecture-and-documentation-only session. No migrations,
models, HTTP endpoints, or retrieval code were introduced. Verification
consisted of:

* an independent architectural review of the proposed Phase 16 direction —
  covering document versioning, `RetrievalPlanner`, `EligibilityResolver`,
  the retrieval contract, evaluation and reranking — conducted before any
  ADR drafting began, resulting in a revised Stage 16.1–16.7 sequence;
* tracing each worked temporal-authority example by hand (predecessor
  resurrection, retroactive authorisation via late approval, and lineage-
  order violation via delayed predecessor approval) to confirm each
  correction actually closes the failure it targets;
* checking the accepted ADR against each Stage 16.1 acceptance criterion
  below;
* confirming, after each amendment round, that only the ADR file itself had
  changed and that no other accepted ADR or application code was modified.

### Acceptance criteria

* Versioning is defined by explicitly and formally resolving ADR-0007's
  deferred decision, not by silently reinterpreting or rewriting it. — Met.
* `CURRENT` and `VALID_AT_DATE` share one derivation rule, computed at query
  time, with no scheduled job's correctness load-bearing for temporal
  authority. — Met.
* A withdrawn version's predecessor is never silently resurrected. — Met.
* A version cannot be treated as authoritative for a date before it was
  genuinely approved, regardless of its scheduled effective date. — Met.
* Authority history cannot move backward relative to explicit version
  lineage; an older predecessor can never attain authority after its own
  successor already has. — Met.
* Governance state remains orthogonal to, and independent of, ADR-0007's
  technical processing lifecycle. — Met.
* Document applicability to organisational locations is recorded per version
  as an immutable snapshot, using a generic hierarchy rather than hard-coded
  levels. — Met.
* ADR-0016's dual retrieval-visibility gate is unchanged; this ADR's model
  applies as an additive third gate, never a redefinition of `INDEXED`. —
  Met.

ADR 0017 was produced through an independent architectural review, a first
full draft, and three rounds of requested, bounded refinement, each closing
a specific correctness gap identified on review rather than reopening
already-agreed decisions. No structural renumbering occurred.

### Commit boundary

git add docs/adr/0017-define-document-versioning-and-temporal-authority.md \
  docs/adr/README.md docs/journal/2026-08-07-r16-s01-define-document-versioning-and-temporal-authority.md \
  PROJECT_ROADMAP.md IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Define document versioning and temporal authority"
git tag -a phase-16-s01 \
  -m "Complete Stage 16.1: Define Document Versioning and Temporal Authority"

---

## Stage 16.2 — Define Retrieval Planning, Eligibility and the Retriever Contract

### Objective

Define how an authorised user's question becomes a resolved retrieval
request: the provider-neutral, LLM-backed `RetrievalPlanner` and typed
`RetrievalPlan`; the Laravel-owned, deterministic `EligibilityResolver` and
`EligibleRetrievalScope`, consuming ADR-0017's temporal-authority model;
the provider-neutral `Retriever`; a controlled retrieval outcome taxonomy;
and a new synchronous Laravel-to-Python protocol — so that Stage 16.4
(Implement Semantic Retrieval) has a settled contract to build against.
Originally scoped as "Define Retrieval Contract"; broadened to its actual
title once drafting confirmed the session's real scope spans planning,
eligibility and the retriever boundary together, not a single narrow
input/output contract.

### Status

Completed on 2026-08-07.

### Decision

ADR 0018 was accepted before any Stage 16.3/16.4 implementation began, in:

```text
docs/adr/0018-define-retrieval-planning-eligibility-and-the-retriever-contract.md
```

It consumes ADR-0017 without reopening it, and extends ADR-0016's dual
retrieval-visibility gate as a third, additive gate rather than redefining
it. It supersedes no other accepted ADR; a new, independently-versioned
Laravel-to-Python synchronous protocol (`rc1`) is added alongside, not
replacing, ADR-0009/0015/0016's asynchronous ingestion-worker protocol.

The central decision is the request flow: Laravel resolves
`AuthorisedKnowledgeScope` from the authenticated user; Laravel calls
Python's provider-neutral, LLM-backed `RetrievalPlanner` (`rc1`, purpose
`retrieval.plan`) to classify the question into a typed `RetrievalPlan` —
`temporal_mode` (`CURRENT`/`VALID_AT_DATE`/`COMPARE`/`CLARIFICATION_REQUIRED`),
the `retrieval_queries` decomposition seam ADR-0013 already committed to
(V1: always exactly one), and an optional, singular `applicability_reference`
semantic hint; Laravel's deterministic, narrowing-only `EligibilityResolver`
combines the plan, the authorised scope, and ADR-0017's authoritative
temporal/governance/lineage data into an `EligibleRetrievalScope`; Laravel
calls Python's provider-neutral `Retriever` (`rc1`, purpose
`retrieval.search`), which performs scoped vector search only and returns
candidate identities, scores and lineage — never PostgreSQL access, direct
or indirect; Laravel then batch-hydrates chunk text/provenance, rechecks
eligibility against the returned candidates, and assembles the final
`RetrievalResult` against a controlled outcome taxonomy (`EVIDENCE_FOUND`,
`NO_ELIGIBLE_EVIDENCE`, `NO_RETRIEVAL_CANDIDATES`, `TEMPORAL_SCOPE_UNRESOLVED`,
`COMPARISON_SCOPE_INCOMPLETE`, `CLARIFICATION_REQUIRED`, `RETRIEVAL_FAILED`).

`COMPARE` resolves two independently-labelled sides (`PRIMARY`/`COMPARISON`)
via symbolic anchors (`CURRENT`, `AT_DATE(date)`, `PREVIOUS`) the planner
never resolves to IDs itself; either side failing to resolve safely
produces `COMPARISON_SCOPE_INCOMPLETE`, never a silent substitute.
`applicability_reference` is resolved deterministically by
`EligibilityResolver` against authoritative `OrganisationalLocation` names
and aliases, and validated against `AuthorisedKnowledgeScope`, before
narrowing anything; an unresolved or ambiguous reference produces
`CLARIFICATION_REQUIRED` rather than being silently dropped or guessed.
Raw retrieval scores are never treated as calibrated probabilities, and V1
defines no similarity threshold — every non-empty candidate set is
`EVIDENCE_FOUND`, with evidence-quality judgement deliberately deferred to
the evaluation and hybrid-retrieval/reranking architecture (Stage 16.6,
Stage 16.8, per the corrected sequence below).

The new `rc1` protocol (Retrieval Call, version 1) is independently
versioned from, and shares no principal, key ring or purpose namespace
with, ingestion's `v1`/`v2` protocol: a new `retrieval-caller` principal,
Laravel signs/Python verifies (reversed from ingestion's direction), a
seven-field string-to-sign (adding a signed `request_id` beyond the
six-field ingestion shape), mandatory authenticated TLS stated explicitly
(HMAC provides authentication/integrity only, never confidentiality), and a
bounded, freshness-window-scoped server-side replay-suppression cache —
deliberately lighter than ingestion's durable event ledger, since
retrieval is a synchronous, read-only call with no lease or attempt to
make idempotent. A normative, independently cross-verified test vector for
`retrieval.search` is recorded in the ADR.

The full set of agreed decisions, worked examples, rejected alternatives
and architectural invariants — including the complete metadata
classification (security/eligibility/descriptive), the
`EligibleRetrievalScope` shape, and forward-compatibility commitments for
hybrid retrieval, reranking and evaluation — is recorded in ADR 0018
rather than duplicated here. ADR 0018 went through an independent
architectural review of the proposed Phase 16 direction before any
drafting began, a first full draft, and two rounds of bounded amendment
(removing direct Python PostgreSQL access and closing the
eligibility-staleness trade-off by moving hydration and a final recheck to
Laravel, adding the `applicability_reference` contract, and correcting
`NO_SEMANTIC_MATCH` to the truthful, count-based `NO_RETRIEVAL_CANDIDATES`
in one round; adding `rc1`'s mandatory-TLS requirement and bounded replay
defence in the other) before acceptance; see the session journal for what
changed at each round.

### Session verification

This was an architecture-and-documentation-only session. No migrations,
models, HTTP endpoints, or retrieval code were introduced. Verification
consisted of:

* independent inspection of ADR-0017, ADR-0016, ADR-0014, ADR-0013,
  ADR-0012, ADR-0009, ADR-0007 and ADR-0006 before drafting, so every
  consumed concept was grounded in its actual accepted text rather than an
  approximation of it;
* computing and independently cross-verifying (via two separate
  HMAC-SHA256 implementations) two successive normative `rc1` test
  vectors — a six-field vector superseded by the seven-field vector once
  `request_id` was added — before trusting either;
* tracing each rejected design (Python PostgreSQL hydration, a raw-score
  `NO_SEMANTIC_MATCH` threshold, a timestamp-only replay defence) against
  the specific correctness or confidentiality gap it left open, to confirm
  each correction actually closes the gap it targets;
* checking the accepted ADR against each Stage 16.2 acceptance criterion
  below;
* confirming, after each amendment round and again before acceptance, that
  only the ADR file itself had changed and that no other accepted ADR or
  application code was modified.

### Acceptance criteria

* Retrieval's request flow, from an authorised question to a typed
  `RetrievalResult`, is fully specified. — Met.
* `RetrievalPlanner` is provider-neutral and LLM-backed for V1,
  structurally prevented from authorising, resolving IDs, or touching
  PostgreSQL/Qdrant. — Met.
* `EligibilityResolver` is Laravel-owned, deterministic, and
  narrowing-only, consuming ADR-0017's temporal-authority model without
  redeciding it. — Met.
* The Python `Retriever` has zero PostgreSQL access; batch hydration and
  the final eligibility recheck are Laravel's responsibility. — Met.
* `COMPARE` resolves two independently-labelled, never-merged sides, with
  no silent substitute version. — Met.
* `applicability_reference` is deterministically resolved and validated
  before narrowing anything, never invented, never a hard filter pushed to
  Qdrant. — Met.
* The retrieval outcome taxonomy makes no untruthful claim about semantic
  match quality; V1 defines no similarity threshold. — Met.
* The new `rc1` protocol is independently versioned, requires
  authenticated TLS, and includes a bounded replay defence appropriate to
  a synchronous, read-only call. — Met.
* ADR-0016's dual gate remains unchanged and unconditional; this ADR's
  model is additive only. — Met.

ADR 0018 was produced through an independent architectural review, a first
full draft, and two rounds of requested, bounded refinement, each closing
a specific correctness, security, or truthfulness gap identified on review
rather than reopening already-agreed decisions. No structural renumbering
of Phase 16 occurred as a result of ADR-0018's own content, but ADR-0018
did surface that Phase 16 was missing an implementation stage for ADR-0017
— see the restructuring below.

### Commit boundary

git add docs/adr/0018-define-retrieval-planning-eligibility-and-the-retriever-contract.md \
  docs/adr/README.md docs/journal/2026-08-07-r16-s02-define-retrieval-planning-eligibility-and-the-retriever-contract.md \
  PROJECT_ROADMAP.md IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Define retrieval planning, eligibility and the retriever contract"
git tag -a phase-16-s02 \
  -m "Complete Stage 16.2: Define Retrieval Planning, Eligibility and the Retriever Contract"

---

## Phase 16 restructuring note (second)

Recorded 2026-08-07, arising from ADR-0018's own "Roadmap clarification"
section. ADR-0018's review found that Phase 16, as sequenced after Stage
16.1 (ADR-0017), moved directly from defining the versioning/temporal-
authority domain model to defining the retrieval contract, without a
session that actually builds ADR-0017's relational/domain foundation (the
`DocumentFamily` backfill migration, lineage and governance tables, the
structural constraints "Unambiguous temporal succession" fixes) before
retrieval implementation would need it. Phase 16 is corrected from seven
stages to eight: a new Stage 16.3 is inserted, and every stage after it is
renumbered by one. No completed stage's own record is rewritten by this
correction — Stage 16.1's and Stage 16.2's completed records, commits and
tags are unchanged.

---

## Stage 16.3 — Implement Document Versioning and Temporal Authority Foundation

### Objective

Implement ADR-0017's relational/domain model: `DocumentFamily`, explicit
linear version lineage, the `DRAFT`/`APPROVED`/`WITHDRAWN` governance
model, per-version `OrganisationalLocation` applicability snapshots, and
the structural constraints unambiguous temporal succession depends on —
the foundation Stage 16.4 (Implement Semantic Retrieval) and
`EligibilityResolver` (ADR-0018, Stage 16.2) both require.

### Status

Completed on 2026-08-07.

### Implementation

The Laravel API now implements ADR-0017's relational/domain foundation. A backfilling migration creates one workspace-owned DocumentFamily for every existing
Document, records legacy versions conservatively as DRAFT with effective_from set
to their creation timestamp, and creates a sealed universal applicability snapshot.
New uploads atomically create a family, first version and snapshot; CreateDocumentVersion adds an immutable successor within the same family and copies
preceding applicability unless replaced explicitly.

Document governance uses string-backed DRAFT, APPROVED and WITHDRAWN states and
explicit effective_from, approved_at and withdrawn_at facts. ApproveDocumentVersion,
WithdrawDocumentVersion and RescheduleDocumentVersion enforce transition rules under
transactions and row locks. ResolveAuthoritativeDocument derives CURRENT and
VALID_AT_DATE through DocumentAuthorityTimeline using authority_start =
max(effective_from, approved_at), lineage order, cancellation before attainment and
half-open authority windows. No current flag or scheduler was introduced.

Ordinary governance requires an owner or administrator membership. Historical
timestamp correction is owner-only, requires a non-empty reason, and writes old/new
values to a distinct governance audit event. The workspace-scoped
OrganisationalLocation adjacency list rejects cycles, supports aliases and arbitrary
depth, and feeds immutable per-version applicability snapshots. Mutable family
defaults remain creation-time convenience data only.

PostgreSQL constraints and triggers enforce same-workspace references,
governance/timestamp consistency, earlier predecessors, one root per family, one
successor per version, approved effective-date and authority-start uniqueness,
acyclic location ancestry, and sealed applicability cardinality and immutability.
Eloquent models, relationships, enum/datetime casts and factories expose the
foundation without routes or retrieval behaviour.

### Session verification

* `docker compose exec -T api php artisan test` — 166 tests passed with
  707 assertions, including 11 focused R16-S03 scenarios.
* `make lint`, `make format-check` and `make typecheck` — passed across
  Next.js, Laravel and Python.
* Frontend tests passed with 26 tests after rerunning Vitest with one worker;
  Python passed 198 tests with one credential-dependent live test skipped.
* A new isolated PostgreSQL database migrated through migration 000012. A synthetic
  legacy Document verified the backfill: family present, DRAFT, effective_from equal
  to created_at, and a sealed universal snapshot. All four lineage/temporal indexes
  were inspected.
* `docker compose ps` reported all health-checked services healthy, and
  `git diff --check` passed.

The first aggregate `make test` attempt ended with exit 137 when the web
container exhausted its memory allocation. The service was restarted and the
same frontend suite passed serially; API and AI then passed separately. A second
migrate:fresh on one reused PostgreSQL database exposed a pre-existing Phase 14
function-cleanup issue. A genuinely new database passed, so shared historical
migrations were not rewritten.

### Acceptance criteria

All Stage 16.3 criteria are met: every Document has one family; authority is
query-derived without retroactive approval or predecessor resurrection; cancelled
future versions are skipped; non-monotonic approval is rejected; future versions can
be rescheduled before attainment; corrections are owner-only, reasoned and audited;
and immutable applicability snapshots plus arbitrary location depth have focused tests.

### Commit boundary

git add apps/api IMPLEMENTATION_GUIDE.md tasks.json docs/journal/2026-08-07-r16-s03-implement-document-versioning-and-temporal-authority-foundation.md
git commit -m "Implement document versioning and temporal authority foundation"

---

## Stage 16.4 — Implement Semantic Retrieval

### Objective

Implement ADR-0018's complete Laravel-to-Python semantic-retrieval path against ADR-0017's completed temporal-authority and applicability foundation.

### Status

Completed on 2026-08-07.

### Implementation

The end-to-end semantic-retrieval path accepted by ADR-0017 and ADR-0018 is
implemented across Laravel, Python and shared contracts. Laravel builds the
authenticated `AuthorisedKnowledgeScope`, calls the provider-neutral Python
`RetrievalPlanner`, resolves the resulting typed plan deterministically against
PostgreSQL temporal-authority and applicability facts, and sends only the
resulting `EligibleRetrievalScope` to Python for vector retrieval.

The independently-versioned `rc1` protocol uses a dedicated retrieval caller,
purpose-bound HMAC signatures, signed request identities, bounded freshness and
replay defence, compact canonical JSON, fresh request IDs on retry, bounded
timeouts and production HTTPS enforcement. Shared JSON Schemas and a normative
canonicalisation vector are validated independently by both languages. The
retrieval key ring and purpose namespace remain separate from ingestion's
worker protocol.

Python exposes authenticated plan and search endpoints. Its structured-output
planner is isolated behind `RetrievalPlanner`, and its PostgreSQL-free
`Retriever` validates embedding-profile compatibility, embeds the query for the
query purpose, and searches exclusively through `VectorStore`. Qdrant receives
explicit workspace, active corpus-generation and bounded eligible-document
scope. `COMPARE` sides are searched independently and remain labelled in the
returned candidate lineage.

Laravel batch-hydrates candidate chunks from authoritative PostgreSQL and
performs the final eligibility recheck before returning any evidence. Candidates
that became stale during the call are discarded. Typed results distinguish
evidence, valid empty results, unresolved temporal/comparison scope,
clarification and operational failure without introducing an uncalibrated score
threshold. Retrieval telemetry is allowlisted and excludes raw questions, chunk
text, candidate content, credentials and signatures.

The public API boundary is `POST
/api/workspaces/{workspacePublicId}/retrieval`; it remains protected by the
existing authenticated, verified Laravel route group. No frontend, migration,
answer-generation, citation, evaluation, hybrid-search or reranking behaviour
was introduced.

### Session verification

* `make format-check`, `make lint` and `make typecheck` passed across Laravel,
  Python and Next.js; mypy checked 92 Python source and test files.
* The full Laravel suite passed 177 tests with 736 assertions.
* The full Python suite passed 209 tests with one credential-dependent live test
  skipped as designed.
* The full frontend suite passed 26 tests across seven files.
* Focused retrieval and Qdrant Python tests passed 22 scenarios; focused Laravel
  semantic-retrieval tests passed nine scenarios with 25 assertions; the
  existing temporal-authority suite passed 11 scenarios with 39 assertions.
* Docker Compose configuration validation, service health checks, Laravel and
  Python route inspection, contract JSON parsing and `git diff --check` passed.
* Python's configured V1 embedding-profile fingerprint matched Laravel's
  persisted fingerprint exactly.

### Problems and corrections

Focused verification found and corrected three implementation defects before
review: a Laravel collection filter whose callback signature did not match
Collection's value-and-key invocation; a stale-candidate path that initially
returned the eligibility outcome instead of the truthful final
`NO_RETRIEVAL_CANDIDATES` result; and a test whose second HTTP fake remained
shadowed by the first callback. The empty-result and operational-failure cases
were separated so their distinct contracts are exercised independently.

### Acceptance criteria

* A provider-neutral, V1 LLM-backed `RetrievalPlanner` produces typed `RetrievalPlan` values for `CURRENT`, `VALID_AT_DATE`, `COMPARE` and `CLARIFICATION_REQUIRED`; clarification short-circuits before eligibility or search. — Met.
* Laravel consumes `AuthorisedKnowledgeScope`; its deterministic, narrowing-only `EligibilityResolver` applies ADR-0017 temporal authority, governance, lineage, locations and aliases to construct typed `EligibleRetrievalScope` values for single- and two-sided comparison retrieval. — Met.
* Laravel calls Python synchronously through the independently-versioned, purpose-scoped `rc1` protocol with signed request identity, freshness/replay defence, bounded timeouts and the authenticated-TLS production requirement defined by ADR-0018. — Met.
* Python's PostgreSQL-free `Retriever` validates embedding-profile compatibility, embeds bounded queries and searches only through `VectorStore` with explicit workspace, active-corpus-generation and eligible-document scope plus bounded optional document filters and result limits. — Met.
* Python returns candidate identity, raw score and lineage only; Laravel batch-hydrates canonical chunk text/provenance from PostgreSQL and performs the final defensive eligibility recheck before exposing evidence. — Met.
* Laravel assembles typed `RetrievalResult` values using ADR-0018's controlled outcomes, including evidence, empty candidates, no eligible evidence, unresolved temporal/comparison scope, clarification and operational failure without inventing an evidence threshold. — Met.
* Cross-language contracts and tests cover canonicalisation/signing, replay and purpose isolation, plan validation, temporal and applicability eligibility, `COMPARE`, profile compatibility, vector filtering/ranking, hydration, final recheck, bounded results, empty results and cross-tenant isolation. — Met.
* Evaluation, hybrid retrieval, reranking, evidence thresholds, answer generation and citations remain explicitly outside Stage 16.4. — Met.

### Commit boundary

git add apps/api apps/ai contracts tests IMPLEMENTATION_GUIDE.md tasks.json docs/journal/2026-08-07-r16-s04-implement-semantic-retrieval.md
git commit -m "Implement semantic document retrieval"

---

## Stage 16.5 — Define Retrieval Evaluation and Quality Gates

### Objective

Define a repository-owned evaluation harness and quality-gate policy that
grows with the pipeline, starting with retrieval and extending to
generation (Stage 17.4) without redesign. Originally scoped as "Define
Evaluation and Quality-Gate Architecture"; retitled to match the accepted
ADR once drafting confirmed the session decides retrieval evaluation
specifically, with generation evaluation named as an extension point
rather than designed here.

### Status

Completed on 2026-08-07.

### Decision

ADR 0019 was accepted before any Stage 16.6 implementation began, in:

```text
docs/adr/0019-define-retrieval-evaluation-and-quality-gates.md
```

It fulfils ADR-0013's forward evaluation commitment, consumes ADR-0017 and
ADR-0018 without reopening either, and supersedes no accepted ADR. Codex
correctly paused before beginning Stage 16.6 implementation directly
against this stage's original, four-year-old stub — which predated
ADR-0017/ADR-0018 and collapsed retrieval and generation metrics into one
undifferentiated catalogue — and independently recommended a
repository-owned harness with optional framework adapters. ADR 0019 is
that architecture, reviewed and refined rather than transcribed verbatim.

The governing principle: retrieval evaluation is a first-class
architectural capability, not a testing activity, existing to provide
objective evidence that a change genuinely improves the system before it
becomes an accepted baseline — and no single composite score is ever
computed in its place, since a blended number can improve while a real
regression hides inside it.

The repository owns the evaluation corpus, its JSON-Schema-defined
structure, stable case/evidence identities, deterministic metrics, the
experiment/result schema, baseline lineage, and quality-gate policy — no
external framework owns any of them. The corpus is versioned and, once
used for an accepted baseline, immutable; a labelling correction produces
a new corpus version rather than silently changing an existing baseline's
meaning. Evaluation case identity (`case_id`) is stable across phrasing
variants. Relevance ground truth is anchored to `Document`/`DocumentFamily`/
version identity plus a corpus-authored canonical text excerpt, resolved
against retrieved chunk text at evaluation time — deliberately never
anchored to `ExtractedElement`, `NormalisedElement`, or `Chunk` identity,
none of which ADR-0010/ADR-0016 guarantee stable across an independent
extraction run, let alone a chunking-strategy change the harness exists
specifically to compare.

Expectations and results are layered to match ADR-0018's own pipeline
stages — planner, eligibility, retrieval relevance, operational, with
generation quality named but deferred to Stage 17.4 — so a failure is
diagnosable by stage rather than reported as one blended result.
Deterministic metrics (Recall@K, Precision@K, MRR, nDCG) remain
application-owned; no numerical release threshold is invented in the ADR,
deferred instead to Stage 16.6's first measured baseline. First-class
slice metrics (temporal modes, tables, multi-evidence, applicability,
paraphrase, isolation, adversarial, and more) prevent a strong aggregate
from hiding a collapse in one important case family. Absolute invariants
(cross-workspace, unauthorised, temporally/applicability-ineligible
evidence; a lost case; non-reproducible metrics) block release regardless
of any comparative-metric improvement and are never offset by one. An
experiment run never becomes the accepted baseline automatically; baseline
promotion is a distinct, deliberately recorded governance action,
structurally excluding the "regress → overwrite baseline → pass"
anti-pattern. V1 uses a documented, human-reviewed release gate, not
automatic promotion.

A provider-neutral `ModelAssistedEvaluator` boundary is introduced and
built in V1 — not deferred — with a concrete `RagasEvaluator` adapter as
its first implementation, translating between application-owned request/
result types and Ragas's own shapes entirely inside that one adapter. Only
Ragas's context-relevance metric (question plus retrieved evidence, no
generated answer required) is wired into Phase 16, as an advisory signal
alongside, never in place of, the deterministic metrics; faithfulness,
answer relevancy and answer correctness remain deferred to Stage 17.4,
which extends this same adapter rather than building a second one. A
model-assisted metric may graduate from advisory to a comparative,
baseline-tracked quality metric once its stability is demonstrated; it may
never become the sole authority for a deterministically-testable property
such as workspace isolation, authorisation, temporal eligibility, or
applicability, regardless of demonstrated maturity.

The full set of agreed decisions, required V1 case families, the reports
model, and golden regressions is recorded in ADR 0019 rather than
duplicated here. ADR 0019 went through an independent review of Codex's
own architecture recommendation before drafting, a first full draft, and
two rounds of bounded amendment (removing direct reliance on unstable
pipeline-generated identifiers for relevance ground truth in favour of
source-anchored text excerpts, in one round; introducing and building the
`ModelAssistedEvaluator`/`RagasEvaluator` boundary in V1 rather than
deferring it, in the other, plus a short philosophy statement added before
acceptance) before acceptance; see the session journal for what changed at
each round.

### Session verification

This was an architecture-and-documentation-only session. No migrations,
models, HTTP endpoints, or evaluation code were introduced. Verification
consisted of:

* independent inspection of ADR-0013, ADR-0017, ADR-0018, ADR-0012 and
  ADR-0006, and of Codex's own R16-S05 architecture recommendation, before
  drafting, so the ADR built on settled precedent rather than an
  approximation of it;
* tracing the extraction/chunking identity chain (ADR-0010's fresh
  per-run element identity, via ADR-0016's already-documented consequence
  for chunk identity) to confirm no pipeline-generated identifier is
  actually stable enough to anchor evaluation ground truth to, before
  correcting the draft's relevance-identity strategy;
* checking the accepted ADR against each Stage 16.5 acceptance criterion
  below;
* confirming, after each amendment round and again before acceptance, that
  only the ADR file itself had changed and that no other accepted ADR or
  application code was modified.

### Acceptance criteria

* The corpus schema, stable identities and deterministic metrics are
  defined independently of any single evaluation framework, and remain
  repository-owned. — Met.
* Required case coverage (temporal, applicability, isolation, adversarial,
  and more) is named explicitly, even where V1 only populates a subset. —
  Met.
* Relevance ground truth is anchored to source content stable across
  chunking/extraction changes, never to a pipeline-generated identifier. —
  Met.
* The quality-gate policy distinguishes absolute invariants from
  comparative quality, and is defined even though its enforcement starts
  manual. — Met.
* A provider-neutral `ModelAssistedEvaluator` boundary is built in V1 with
  a concrete Ragas adapter, isolated so no Ragas-specific type leaks
  outside it. — Met.
* Stage 17.4 (Add Answer Evaluation) can extend this same harness and
  adapter rather than defining its own. — Met.

ADR 0019 was produced through an independent review of an
implementer-originated recommendation, a first full draft, and two rounds
of requested, bounded refinement, each closing a specific correctness or
directional gap identified on review rather than reopening already-agreed
decisions. No structural renumbering of Phase 16 resulted from this
session.

### Commit boundary

git add docs/adr/0019-define-retrieval-evaluation-and-quality-gates.md \
  docs/adr/README.md docs/journal/2026-08-07-r16-s05-define-retrieval-evaluation-and-quality-gates.md \
  PROJECT_ROADMAP.md IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Define retrieval evaluation and quality gates"
git tag -a phase-16-s05 \
  -m "Complete Stage 16.5: Define Retrieval Evaluation and Quality Gates"

### Post-acceptance clarification

Before Stage 16.6 implementation began, the required implementation review
identified bounded ambiguities and two factual overclaims in ADR-0019. Accepted
ADR-0020 records the corrections without rewriting ADR-0019: repository-owned
`EvidenceUnit` semantics; distinct-unit metric and duplicate-credit rules;
correct adversarial input ownership; injected evaluator-model configuration;
offline and opt-in live Ragas testing; retrieval-only Phase 16 evaluator
requests; aggregate context-relevance semantics; membership-scoped V1 security
cases; corpus/policy content digests; case-first variant aggregation; and the
deterministic-versus-stochastic reproducibility distinction.

---

## Stage 16.6 — Implement Retrieval Evaluation

### Objective

Implement ADR-0019 and ADR-0020's repository-owned evaluation harness and
measure the Stage 16.4 semantic-retrieval baseline against a curated,
versioned question-and-source corpus.

### Status

Completed on 2026-08-07.

### Implementation

The repository now owns versioned JSON Schemas and immutable application types
for the evaluation corpus, quality policy, experiment result, accepted-baseline
promotion and manual release gate. Canonical JSON SHA-256 digests bind corpus
and policy content to experiment lineage, so a version label cannot conceal an
in-place mutation. Experiment execution, comparison, manual review and baseline
promotion remain separate commands and records.

The synthetic V1 corpus contains 23 stable semantic cases and 25 question
variants across 33 slices. It covers ADR-0017/0018's temporal modes and edge
cases, applicability and location inheritance, comparison, prose, tables,
multi-evidence, phrasing variation, workspace membership/concealment, controlled
empty outcomes and adversarial input. Every `EvidenceUnit` is anchored to a
committed source excerpt plus durable document family/version identity, never a
generated chunk or element identifier.

The deterministic `normalised-token-coverage-v1` matcher supports combined
coverage across multiple chunks. Recall@K, Precision@K, MRR and nDCG operate on
distinct evidence units, award duplicate evidence only once and evaluate
`COMPARE`'s `PRIMARY` and `COMPARISON` sides independently before aggregation.
Question variants aggregate within their semantic case before equally weighted
case and slice aggregation. Lost cases and supplied absolute-invariant failures
remain explicit rather than being hidden by an overall score.

`ModelAssistedEvaluator` is provider neutral and application owned. The first
adapter, `RagasEvaluator`, contains every Ragas-specific import and shape,
receives its evaluator model/client through injection, evaluates context
relevance over each query's complete retrieved context set and translates any
provider/framework failure into a controlled advisory result. Normal tests use
the deterministic fake; the real Ragas-plus-provider test is explicitly opt-in.
Ragas 0.4.3 and its compatible LangChain integration are pinned. The Python 3.14
ARM development image includes the build toolchain required by Ragas's locked
`scikit-network` dependency while the dependency layer remains reproducible.

`make evaluation-run` writes outside the repository and records the exact Git
revision supplied by the host. The accepted V1 result was generated only after
implementation commit `bcd04346eb3e662bcf79279e589fe1a1ce2063d5` existed. The
accepted experiment identifier is `retrieval-v1-offline-baseline`; corpus
version 1 has digest
`d7c44d45780dc327870458224c995f71fa1ad98117706f489164f48999665ba0`, and policy
version 1 has digest
`f362010a8cc5239e8ce36759b5fa8eee2d3b5d22b69717ec8a2c199acb80b83f`.
The result has no absolute failures. David Oliver recorded an `ACCEPTED` manual
gate and deliberately promoted that exact experiment as the initial baseline;
the checked-in self-comparison report records a passing zero-delta reference.

#### Post-closure corpus correction

Review after the initial promotion found that V1's
`temporal.predecessor-resurrection` case incorrectly rewarded resurrection of
an earlier predecessor after its attained successor was withdrawn. ADR-0017
explicitly prohibits that behaviour, and the Laravel authority resolver already
implemented the correct invariant. In accordance with ADR-0019's corpus
immutability rule, V1 was preserved unchanged and corpus V2 was created rather
than edited in place.

V2 expects `NO_ELIGIBLE_EVIDENCE`, declares no EvidenceUnit and records no
candidate for that case. Corrective commit
`735654291e2f5e085f83e98f1229768c0237edaf` added V2 and made it the default
offline corpus. The accepted experiment is `retrieval-v2-offline-baseline`;
its corpus digest is
`0e78f8e57a3d9c358ae08bdf7e97ded151cc4111cf934f48342427a2a187c1af`.
David Oliver accepted the corrected manual gate and deliberately promoted V2,
which supersedes V1 as the current baseline while retaining V1 as immutable
historical evidence. The offline `model_assisted` results use the explicitly
identified `deterministic-fake` evaluator; live RAGAS was not used to produce
either accepted baseline.

### Commands and verification

* `docker compose build ai worker` rebuilt the locked Python environment.
* `docker compose run --rm --no-deps ai uv sync --locked` refreshed the existing
  development dependency volume after the Ragas dependency change.
* `make lint`, `make format-check`, `make typecheck` and `make test` passed.
* The frontend suite passed 26 tests; Laravel passed 177 tests with 736
  assertions; Python passed 229 tests with two credential-gated live tests
  skipped as designed.
* The focused evaluation suite passed 20 tests with the opt-in live Ragas test
  skipped. Corpus/schema/source anchoring, deterministic metrics, multi-chunk
  credit, side-separated comparison, case-first aggregation, digest mutation,
  governance and controlled evaluator failures are covered.
* `NODE_ENV=production npm run build` completed the Next.js production build.
  The first attempt inside the development container correctly exposed its
  non-production `NODE_ENV`; rerunning with the production build environment
  passed without changing frontend code.
* `make evaluation-run` generated the accepted result from the immutable
  implementation commit. Separate `promote`, `gate` and `compare` commands
  produced the checked-in promotion, manual decision and report.
* Docker Compose configuration validation, service health, JSON Schema
  validation, dependency imports and `git diff --check` passed.

### Problems and corrections

Ragas 0.4.3 transitively requires `scikit-network`, for which no Python 3.14 ARM
wheel exists. The image first built dependencies successfully in a compiler
stage, but repository-wide verification showed that an existing Compose venv
also needs to compile the locked package when refreshed. The development image
was corrected to provide that toolchain, the shared volume was synchronised in
a one-off container, and the complete gate then passed.

Final metric review also found that combined multi-chunk evidence initially
contributed to recall without receiving first credit at the rank where coverage
became complete, and that comparison evidence lacked explicit side identity.
Both were corrected before commit: prefix coverage now determines first credit,
ground truth carries its side, and per-side metrics are retained in the result.

### Acceptance criteria

* Repository-owned corpus, policy, result, promotion and gate artefacts validate
  against their schemas and content digests detect mutation. — Met.
* Distinct source-anchored evidence, combined multi-chunk coverage, duplicate
  handling and side-separated comparison do not depend on generated IDs. — Met.
* Metrics reproduce from recorded inputs and variants aggregate case first. — Met.
* Layered correctness, slice results and absolute failures remain inspectable. — Met.
* Deterministic invariant failures cannot be offset by comparative quality. — Met.
* Ragas remains isolated, injected, advisory, offline-testable and opt-in live. — Met.
* The initial baseline, comparison, promotion and accepted manual gate are
  versioned repository artefacts bound to the implementation commit. — Met.
* Dependency compatibility and repository-wide quality checks pass. — Met.

### Commit boundary

git commit -m "Add retrieval evaluation suite"
git commit -m "Record initial retrieval evaluation baseline"
git tag -a phase-16-s06 \
  -m "Complete Stage 16.6: Implement Retrieval Evaluation"

---

## Stage 16.7 — Define Hybrid Retrieval and Reranking Architecture

### Objective

Define the hybrid retrieval pipeline as a sequence of independently
configured narrowing stages — dense and sparse candidate retrieval,
application-owned fusion, reranking, and a calibrated evidence-threshold
policy — while preserving deterministic eligibility, provider neutrality,
and measurable improvement over the Stage 16.6 baseline through ADR-0019's
evaluation harness.

### Status

Completed on 2026-08-08.

### Decision

ADR 0021 was accepted before any Stage 16.8 implementation began, in:

```text
docs/adr/0021-define-hybrid-retrieval-and-reranking-architecture.md
```

It extends ADR-0014's vector storage model (a symmetric sparse-space
generation alongside embedding-space generation, reusing the existing
named-vector and rebuild/activation machinery ADR-0014 already
anticipated) and ADR-0018's pipeline and outcome taxonomy (a new `rc1`
purpose, `retrieval.rerank`, already named as a legitimate future
extension by ADR-0018; one new outcome, `INSUFFICIENT_EVIDENCE`, exercising
the calibrated-acceptance policy ADR-0018 explicitly deferred to this
session). It supersedes no accepted ADR and consumes ADR-0017, ADR-0019
and ADR-0020 unchanged.

The V1 pipeline: `AuthorisedKnowledgeScope` → `RetrievalPlanner` →
`EligibilityResolver` → `EligibleRetrievalScope` → dense retrieval fused
with sparse retrieval (application-owned RRF) → Laravel hydration and
eligibility recheck → Voyage `rerank-2.5` reranking → a final Laravel
eligibility recheck → `EvidenceThresholdPolicy` → final evidence. Every
stage narrows; none may widen the eligible evidence universe
`EligibilityResolver` established.

A provider-neutral `SparseEncoder` boundary, with FastEmbed running
SPLADE++ (`prithivida/Splade_PP_en_v1`) as the V1 implementation — a
learned, corpus-independent sparse encoder, required (not merely
preferred) by this platform's shared-collection tenancy model: BM25's
corpus-statistical term weights would either couple one workspace's
ranking to other workspaces' term distributions (collection-wide
statistics) or turn every single-document ingestion into a workspace-wide
re-weighting operation (per-workspace statistics), violating ADR-0006's
and ADR-0014's already-accepted invariants either way. `SparseEmbeddingProfile`
is immutable and fingerprinted exactly as `EmbeddingProfile` already is,
records its supported input-length bound, and `SparseEncoder` never
silently truncates an over-length input. Sparse vectors live as an
additional named vector on existing Qdrant points — no new collection, no
duplicated canonical text — via a new sparse-space generation, symmetric
to embedding-space generation. Enabling hybrid retrieval for a workspace
is a coordinated corpus rebuild producing a complete new point set under
newly-derived point identities (ADR-0014's identity derivation includes
the generation identity itself), verified complete across both the dense
and sparse axes before activation; sparse-profile and sparse-space-
generation identity and lineage require real PostgreSQL migrations,
models, and relationships, honestly acknowledged rather than assumed away.

Fusion stays application-owned — never Qdrant-native — for provider
neutrality, deterministic testability, per-stage evaluation lineage, and
adapter portability. `FusionStrategy` is introduced with Reciprocal Rank
Fusion as its V1, fully deterministic implementation: 1-based ranks, a
versioned `rrf_k`, canonical-chunk-identity deduplication, at most one
contribution per candidate per list, preserved per-list rank/score
lineage, and a fixed tie-break (fused score descending, then best source
rank ascending, then canonical chunk identity ascending — never provider
return order). `COMPARE` sides are fused independently, never merged.

Six independently-versioned candidate-pipeline parameters —
`dense_candidate_k`, `sparse_candidate_k`, `fusion_candidate_k`,
`reranker_candidate_k`, `evidence_threshold`, `final_evidence_k` — each
answer a distinct optimisation question and are semantically uncoupled,
while remaining structurally bounded by pipeline data flow (a downstream
stage can never be configured to expect more candidates than an upstream
stage actually produced). None is hard-coded as an architectural constant;
Codex's recommended starting values are recorded as the initial
experimental configuration only, calibrated for production by Stage 16.8
against ADR-0019's evaluation harness.

`Reranker` is a provider-neutral boundary mirroring `Embedder`'s exact
disciplines, with Voyage `rerank-2.5` as V1: no silent truncation, a typed
failure taxonomy, a deterministic fake, opt-in credential-dependent live
tests, and an injected, configuration-owned client. The Voyage adapter
strictly validates every provider response before returning it; Laravel
independently validates reranked candidate identities and lineage before
acting on them. Python never reads PostgreSQL; Laravel hydrates canonical
text and rechecks eligibility both before sending candidates to the
Reranker and after receiving reranked results back — two round trips, not
one, extending ADR-0018's existing hydration boundary rather than
replacing it. `retrieval.rerank` is a new `rc1` purpose inheriting every
existing `rc1` requirement in full (TLS, purpose-scoped HMAC, versioning,
workspace binding, signed `request_id`/replay suppression, freshness,
key rotation, trace propagation), with ADR-0012's privacy allowlist
applied explicitly since this call's request body carries real chunk text.

`EvidenceThresholdPolicy` finally exercises the calibrated-acceptance
policy ADR-0018 deferred to this session. Ownership is pinned explicitly:
Python computes and returns scores and lineage only; Laravel owns policy
persistence/resolution, threshold application, `final_evidence_k`
enforcement, authoritative outcome selection (including the new
`INSUFFICIENT_EVIDENCE` and post-threshold `COMPARISON_SCOPE_INCOMPLETE`),
the final eligibility recheck, and the evidence set forwarded to
generation — Python never independently decides evidence is "good enough."
A policy is immutably bound to the exact reranker, sparse-profile,
embedding-profile, fusion, candidate-configuration, and calibration-corpus
lineage it was calibrated against, and Laravel rejects applying it to
mismatched lineage. Stage 16.8 must select `evidence_threshold` (and any
configuration claimed as an improvement) on a calibration/tuning split and
assess it against a separate held-out acceptance split that never
influenced selection.

Rollback reuses ADR-0014's generation lifecycle precisely: an explicit,
atomic `SUPERSEDED → ACTIVE` operation (retention-window check,
completeness re-verification, atomic demote-and-promote, audit record),
never a direct pointer or identifier mutation, and never a request-time,
undeclared dense-only fallback — a mid-request sparse/reranker failure
produces `RETRIEVAL_FAILED` instead.

`R16-S08`'s implementation boundary is explicitly broad — `apps/ai`,
`apps/api`, `contracts`, PostgreSQL migrations/models/relationships,
ingestion/generation-completeness changes, Qdrant collection/vector
configuration, cross-service tests, evaluation/calibration artefacts,
configuration/dependency files, and factual guide/tracker/journal
updates — superseding the narrower "apps/ai plus tests" framing this
stage's original stub implied.

The full set of agreed decisions, worked examples, rejected alternatives
and architectural invariants is recorded in ADR 0021 rather than
duplicated here. ADR 0021 went through an independent architectural review
of Codex's implementation-driven recommendation before any drafting began,
a first full draft, and two rounds of bounded amendment (making the six
candidate-pipeline parameters' independence and structural bounds
explicit, and correcting the philosophy wording, in one round; correcting
adversarial-case-style factual gaps — monotonic candidate bounds,
deterministic RRF tie-break, honest dense/sparse rebuild and migration
semantics, `SparseEncoder` input-length behaviour, `EvidenceThresholdPolicy`
ownership/identity-binding/calibration-split, rollback lifecycle
correctness, `retrieval.rerank` security, strict reranker response
validation, and the corrected SPLADE++ "only" claim, in the other) before
acceptance; see the session journal for what changed at each round.

### Session verification

This was an architecture-and-documentation-only session. No migrations,
models, HTTP endpoints, or retrieval code were introduced. Verification
consisted of:

* independent inspection of ADR-0014, ADR-0017, ADR-0018, ADR-0019 and
  ADR-0020, and of Codex's implementation-driven recommendation, before
  drafting, so every consumed concept was grounded in its actual accepted
  text rather than an approximation of it;
* tracing ADR-0014's deterministic point-identity derivation to confirm a
  hybrid-enabled generation genuinely produces new point identities,
  before correcting the first draft's "dense vectors reused unchanged"
  claim;
* independently reasoning through why BM25's corpus-statistical weights
  are structurally incompatible with this platform's shared-collection
  tenancy and cheap-incremental-ingestion invariants, rather than treating
  SPLADE++'s selection as a given;
* confirming, after each amendment round and again before acceptance, that
  only the ADR file itself had changed and that no other accepted ADR or
  application code was modified;
* checking the accepted ADR against each Stage 16.7 acceptance criterion
  below.

### Acceptance criteria

* `SparseEncoder`, `FusionStrategy` and `Reranker` are each provider-
  neutral, matching the `Embedder`/`VectorStore` replaceability pattern. —
  Met.
* Fusion, thresholds and abstention are each explicit, typed, versioned
  decisions, never implicit behaviour or an invented numerical constant. —
  Met.
* Workspace, authorisation and temporal/applicability eligibility remain
  mandatory and Laravel-owned through fusion, reranking and thresholding —
  Python never independently authorises evidence. — Met.
* The candidate pipeline's six parameters are independently versioned and
  structurally, not semantically, bounded. — Met.
* Hybrid rollout reuses ADR-0014's generation lifecycle, including a
  lifecycle-correct rollback operation; no silent request-time downgrade
  is possible. — Met.
* `R16-S08`'s implementation boundary is stated honestly, including the
  required PostgreSQL migrations. — Met.

ADR 0021 was produced through an independent architectural review, a first
full draft, and two rounds of requested, bounded refinement, each closing
a specific independence, determinism, honesty, or security gap identified
on review rather than reopening already-agreed decisions. No structural
renumbering of Phase 16 resulted from this session.

### Commit boundary

git add docs/adr/0021-define-hybrid-retrieval-and-reranking-architecture.md \
  docs/adr/README.md docs/journal/2026-08-08-r16-s07-define-hybrid-retrieval-and-reranking-architecture.md \
  PROJECT_ROADMAP.md IMPLEMENTATION_GUIDE.md tasks.json
git commit -m "Define hybrid retrieval and reranking architecture"
git tag -a phase-16-s07 \
  -m "Complete Stage 16.7: Define Hybrid Retrieval and Reranking Architecture"

---

## Stage 16.8 — Implement Hybrid Retrieval and Reranking

### Objective

Implement the Stage 16.7 (ADR-0021) architecture in full: the
`SparseEncoder` boundary (FastEmbed/SPLADE++), the sparse-space generation
extending ADR-0014's model, deterministic application-owned RRF fusion,
the `Reranker` boundary (Voyage `rerank-2.5`), the extended two-round-trip
Laravel hydration sequence and the new `retrieval.rerank` `rc1` purpose,
and the Laravel-owned `EvidenceThresholdPolicy` — calibrated against
ADR-0019's evaluation harness using a held-out acceptance split, never
invented as a hard-coded number.

### Status

Completed, engineering-evaluated and calibration-complete. After
the preliminary uncommitted runs, the repository established an immutable V3
calibration population and executed CAL-EXP-0003 once from exact commit
`e3a356d5872f43611572c33c0d8f2ee09e5e8002`. Post-provider compatibility
passed and the predeclared policy retained `0.337890625` for that exact lineage.
The threshold is calibrated but is not held-out accepted or production
promoted. EXP-0008 closed the current retrieval engineering block without
accessing the sealed held-out population; held-out acceptance and deliberate
production activation remain separate future release decisions rather than
unfinished hybrid-retrieval implementation.

### Implementation

* Added a provider-neutral `SparseEncoder`, an immutable and fingerprinted
  SPLADE++ profile, a FastEmbed adapter with explicit input-length checks,
  and deterministic fakes. FastEmbed is locked to the compatible 0.7 release
  line and the model artefacts are pinned to repository
  `Qdrant/Splade_PP_en_v1` revision
  `efcd182bc7eb351e81a9445752d4388c2bab500b`; its resolved transitive
  dependency set passed the complete Python regression suite.
* Added a provider-neutral `FusionStrategy` and deterministic RRF
  implementation with 1-based ranks, one contribution per source list,
  canonical-chunk deduplication, retained rank/score lineage and the precise
  ADR-0021 tie-break. `COMPARE` sides are fused independently.
* Added a provider-neutral `Reranker`, deterministic fake and isolated Voyage
  `rerank-2.5` adapter. The adapter uses an injected client, disables provider
  truncation, classifies failures, retries only transient failures and rejects
  unknown/duplicate candidates, invalid ranks, non-finite scores, malformed
  usage and incorrect model lineage.
  `COMPARE` sides are reranked independently and response identity is the
  side-qualified `(side, chunk_id)` pair, so the same canonical chunk may
  legitimately occur on both independently resolved temporal sides without
  merging their rankings.
* Extended the purpose-scoped HMAC `rc1` protocol with
  `retrieval.rerank`, `retrieval.corpus.rebuild` and
  `retrieval.corpus.verify` operations and repository-owned JSON Schemas.
  Reranking telemetry records safe counts and lineage only, never query or
  chunk text.
* Extended Qdrant's existing collection with a named sparse vector, explicit
  sparse-generation payload/index scope, hybrid upserts and sparse search.
  Completeness verification now validates exact point identities, dense and
  sparse vector schema, and payload lineage rather than counts alone.
* Added PostgreSQL persistence for immutable sparse profiles and sparse-space
  generations, evidence-threshold policy lineage/configuration and audited
  generation rollback. Database constraints enforce fingerprints, lifecycle
  timestamps, monotonic candidate bounds, threshold range, one active policy
  per dense/sparse lineage, compatible available sparse space for active
  hybrid corpora and protection against retiring an actively referenced
  sparse space. Threshold precision preserves Voyage's calibrated score
  boundary without rounding it to a different policy. PostgreSQL stores no raw
  vectors.
* Added explicit Laravel commands and actions for sparse-space provisioning,
  bounded/resumable hybrid corpus rebuild, exact dual-axis verification,
  atomic activation and audited `SUPERSEDED -> ACTIVE` rollback. The old
  active generation remains serving until the replacement verifies and
  activates; deterministic point identities make retries idempotent.
* Extended ingestion so an explicitly selected sparse lineage produces and
  verifies dense and sparse vectors together. Laravel remains authoritative
  for generation and lifecycle transitions; Python still has no PostgreSQL
  access and all vector operations remain behind `VectorStore`.
* Extended semantic retrieval to resolve an exact active
  `EvidenceThresholdPolicy`, validate search/reranker lineage, hydrate and
  eligibility-check before reranking, recheck after reranking, apply the
  Laravel-owned threshold and final evidence bound, and return
  `INSUFFICIENT_EVIDENCE` or post-threshold
  `COMPARISON_SCOPE_INCOMPLETE` explicitly. Any sparse/reranker/policy
  failure produces `RETRIEVAL_FAILED`; there is no silent dense-only
  downgrade.
* Added a versioned hybrid calibration/held-out split, source-anchored live
  candidate set, deterministic calibration mechanics and a rate-limit-aware
  Voyage calibration command. Observation identity is candidate-specific while
  split isolation is enforced at case level. The checked-in offline fixture is
  explicitly labelled `deterministic-fake` and verifies mechanics only. A
  preliminary live run proposed `0.337890625`, with precision, recall and F1 of
  `1.0` on calibration and untouched held-out cases; it is verification
  evidence rather than an accepted policy because the implementation was not
  committed when it ran.
* Added an opt-in live pipeline evaluation that creates and removes a disposable
  Qdrant collection, embeds the repository evaluation chunks with Voyage,
  computes pinned SPLADE++ vectors, executes real dense and sparse searches,
  applies deterministic RRF, reranks through Voyage and evaluates the final
  thresholded evidence with ADR-0019's harness. Provider calls are paced at the
  actual HTTP-call boundary for account-specific rate limits; ordinary tests
  remain wholly offline.

### Required ADR

docs/adr/0021-define-hybrid-retrieval-and-reranking-architecture.md
(accepted `R16-S07`)

### Acceptance criteria

* Sparse and dense candidates are fused deterministically before
  reranking, not merged ad hoc per call site. — Met.
* Reranking reduces a broader candidate set to a smaller evidence set
  through the `Reranker` contract, not a direct Voyage SDK call. — Met.
* Evidence below the calibrated `evidence_threshold` results in explicit
  `INSUFFICIENT_EVIDENCE`, not a low-confidence answer presented as if
  grounded. — Met. The exact-lineage calibrated value remains unpromoted;
  production activation is a separate release decision.
* Workspace membership, authorisation, temporal authority (ADR-0017) and
  applicability eligibility are verified through the complete
  fused/reranked path, not only the semantic baseline. — Met.
* `evidence_threshold` and any configuration claimed as an improvement are
  selected on a calibration/tuning split and assessed on a separate
  held-out acceptance split that did not influence selection, using
  Stage 16.6's evaluation harness. — Calibration selection and physical split
  isolation are implemented and tested. CAL-EXP-0003 mechanically retained
  `0.337890625`. The sealed held-out population was not accessed during this
  closure, so no held-out-acceptance or production-promotion claim is made.
* No stage can be configured to exceed the candidates an upstream stage
  actually produced; statically-knowable violations are rejected at
  configuration-validation time. — Met in database, application and Python
  contract validation.
* Rollback is the lifecycle-correct, atomic, audited `SUPERSEDED -> ACTIVE`
  operation; a mid-request sparse/reranker failure returns
  `RETRIEVAL_FAILED`, never a silent dense-only downgrade. — Met.
* Tests cover fusion determinism, reranking, threshold/abstention
  behaviour, isolation, monotonic candidate bounds, and rollback lifecycle
  transitions. — Met.

### Commands and verification

* The live Voyage `rerank-2.5` adapter smoke test passed. The preliminary
  rate-limit-paced calibration scored 17 source-anchored passages in eight
  case-isolated cases using 276 provider input tokens.
* The complete Python suite passed 253 tests with three opt-in live tests
  skipped; Ruff lint and formatting passed, and Mypy reported no issues in
  130 source files.
* The complete Laravel suite passed 188 tests with 786 assertions; Pint
  passed across 209 files.
* The frontend suite passed 26 tests; ESLint and TypeScript passed. The
  Next.js production build passed when run with `NODE_ENV=production`. An
  initial attempt inherited the development container's
  `NODE_ENV=development` and failed during framework prerendering; no
  frontend change was required.
* Every migration, including the new hybrid foundation migration, ran cleanly
  against a newly-created disposable PostgreSQL database. Read-only catalogue
  inspection confirmed the expected lifecycle constraints, partial active-
  policy index and sparse-space protection triggers; the disposable database
  was then removed.
* Qdrant integration coverage verified idempotent collection/index
  provisioning, dual-vector upsert/search and exact dense/sparse completeness
  checks.
* Shared schemas, HMAC purposes, dependency locks, `git diff --check` and the
  repository-wide formatting/lint/type/test gates passed.
* The live end-to-end run used dense `40`, sparse `40`, fusion `15`, reranker
  `15`, threshold `0.337890625`, final evidence `5` and RRF `60`. Against the
  same corpus and eligibility fixture, dense-only at K=3 scored recall
  `0.9565217391`, precision `0.1739130435`, MRR `0.5` and nDCG
  `0.9404752067`; hybrid at final K=5 scored recall `1.0`, precision
  `0.1130434783`, MRR `0.5014492754` and nDCG `0.9516022960`. Both had zero
  hard failures and perfect planner, eligibility and outcome accuracy. Because
  the compared K values differ, precision is reported rather than claimed as a
  like-for-like regression. The accepted recorded V2 dense baseline remains
  recall `1.0`, precision `0.1884057971`, MRR `0.5543478261` and nDCG
  `0.9919767338`; it is retained as historical lineage, but is not treated as a
  direct provider comparison because it used recorded observations rather than
  this live source-anchored Qdrant run.
* Hybrid achieved recall `1.0` on `CURRENT`, `VALID_AT_DATE`, `COMPARE`,
  applicability, adversarial and every held-out acceptance case. The held-out
  evidence cases each returned five threshold-qualified candidates (precision
  at K `0.2`), demonstrating that the source-anchored calibration result did
  not establish sufficient selectivity for the actual retrieval distribution.
* The live run processed 246 Voyage embedding input tokens (estimated
  `$0.00002952` under the configured embedding cost), 2,207 reranker input
  tokens and 11 chunks across 15 queries. Search latency was 403.5 ms mean,
  417.1 ms p50 and 767.2 ms p95. Recorded rerank wall time includes deliberate
  25-second provider pacing (24.6 s mean; 49.4 s maximum for the two-sided
  `COMPARE` call) and therefore is not represented as Voyage model latency.
  No reranker price was configured, so no reranker cost was invented.

### Final engineering closure

CAL-EXP-0003 retained `0.337890625` after an exact-commit provider pass and
provider-free replay over the compatible V3 calibration population. Its
controlled-rejection diagnosis found two planner semantic errors, ten benchmark
expectations inconsistent with ADR-0018's workspace-wide relevance boundary,
and thirteen relevance-versus-answer-sufficiency failures; it did not identify
a scalar-threshold implementation defect.

EXP-0008 then executed the final ten-case, 31-variant V3 engineering regression
population once from commit
`a21431bc0f9137978f3c4d082619954f8814bd9d`. It recorded case-first Recall@K
`0.9667`, clean-upstream Recall@K `1.0000`, benchmark precision `0.2100`, MRR
`0.9333` and nDCG@K `0.9157`. Every one of the 36 correctly scoped expected
EvidenceUnits remained present at Dense, Sparse, union, fusion, reranker,
threshold and final-evidence stages. No downstream retrieval defect was
demonstrated.

Planner correctness was `29/31`; eligibility and outcome correctness were each
`30/31`. The remaining body-map and fire-marshal observations are accepted
content/event-time versus document-authority-time classification risk. The
planner remained fail-closed and no semantic retry-to-success occurred.

The retrieval core is therefore mature for the current phase and the planner
is accepted with known residual risk. Do not reopen planner/retrieval tuning
merely to improve this engineering benchmark. Revisit only for a future
regression, a material recurring real-corpus pattern, a systemic sealed-
acceptance result, or a deliberately approved planner architecture stage.

The sealed held-out population remains untouched. Before any future production
activation, assess and deliberately accept the exact frozen policy lineage,
keeping its provider, model, adapter, dense/sparse profiles, RRF, candidate
configuration and corpus digest binding intact.

### Commit boundary

git add IMPLEMENTATION_GUIDE.md PROJECT_ROADMAP.md tasks.json \
  docs/evaluation docs/journal/2026-08-08-r16-s08-implement-hybrid-retrieval-and-reranking.md
git commit -m "Close EXP-0008 retrieval engineering baseline"

---

# Phase 17 — Grounded Generation

## Phase objective

Generate answers that are constrained by retrieved evidence and accompanied by verifiable citations.

---

## Stage 17.1 — Define Generation Provider Boundary

### Objective

Define the architectural boundary for grounded answer generation: a
provider-neutral interface, the sufficiency/outcome model, the citation and
evidence-lineage contract, and the Laravel/Python ownership split — so
Stage 17.2 has a settled contract to implement prompt assembly against.

### Status

Completed on 2026-08-16.

### Decision

ADR 0023 was accepted in:

```text
docs/adr/0023-define-the-provider-neutral-grounded-generation-architecture-and-contract.md
```

Full rationale, alternatives and consequences live in the ADR; summarised
here without duplication:

* a provider-neutral `Generator.generate(GenerationRequest) -> GenerationResult`
  boundary, with OpenAI/gpt-5-mini recorded as the initial V1 adapter only —
  not an architectural lock-in;
* generation, not a separate LLM sufficiency judge, owns whether the
  supplied evidence can materially answer the question;
* three first-class outcomes — `ANSWERED`, `QUALIFIED`,
  `INSUFFICIENT_EVIDENCE` — with enforced structural invariants, preferring
  a useful qualified answer over unnecessary refusal;
* `answer_parts[]` as the sole authoritative generated representation —
  evidence-bound, natural-prose, no independent free-text answer field —
  with all persistent identity (`AnswerPart`, `EvidenceSnapshot`) assigned
  by Laravel, never the provider;
* durable, per-answer `EvidenceSnapshot`s storing cited text verbatim,
  resolving the citation/re-extraction design constraint deferred since
  2026-07-30;
* deterministic context-packing ownership split (Laravel: evidence set,
  `COMPARE` structure, order, policy; Python: provider-specific rendering
  and token measurement only), with a typed
  `GENERATION_CONTEXT_BUDGET_EXCEEDED` failure kept structurally distinct
  from `INSUFFICIENT_EVIDENCE`;
* hostile evidence treated as untrusted data, with no autonomous
  retrieval/tool capability granted to the generator;
* a bounded operational-retry taxonomy with no semantic retry-to-success;
* a versioned `generation_fingerprint`, extending the same snapshot-plus-
  fingerprint idiom already used for chunking and embeddings;
* the existing `rc1` protocol extended with one new purpose,
  `generation.answer`, rather than a new protocol;
* Phase 18 streaming explicitly deferred, inheriting only the
  validate-before-authoritative constraint;
* Stage 17.4 evaluation extends the existing `ModelAssistedEvaluator`/
  `RagasEvaluator` boundary (ADR-0019/0020) rather than a new subsystem.

ADR 0023 was produced through an independent architectural review, a first
full draft, one bounded revision round addressing five specific review
findings plus an editorial deduplication pass, and a final pre-acceptance
contradiction check against every ADR it cites. No structural renumbering
occurred.

### Session verification

This was an architecture-and-documentation-only session. No application
code, migrations, contracts, prompts, provider adapters, or evaluation
cases were created; no provider was called. Verification consisted of:

* an independent architectural review of the proposed Phase 17 direction,
  conducted before ADR drafting began;
* a bounded final review against five specific findings (insufficiency-
  reason scope, `AnswerPart` identity ownership, context-budget failure
  typing, context-packing ownership, and the gpt-5-mini rationale), plus one
  editorial deduplication pass;
* a final pre-acceptance contradiction check confirming ADR-0023 remains
  compatible with every accepted ADR it cites — Laravel/Python ownership
  (ADR-0002), `rc1` authentication (ADR-0018, extended by ADR-0021),
  provider-neutral boundaries (ADR-0013, ADR-0018, ADR-0021), canonical
  chunk/provenance and extraction-run immutability (ADR-0010, ADR-0011),
  audit and lineage (ADR-0006), and the evaluation boundary (ADR-0019,
  ADR-0020);
* confirming no retrieval, planning, eligibility, fusion, reranking, or
  threshold-calibration content was reopened.

### Acceptance criteria

* Provider SDK use is isolated to one adapter. — Met.
* Model/provider/adapter identity is explicit and versioned, not hard-coded
  as a permanent choice. — Met.
* Requests have bounded timeouts. — Met, inherited unchanged from `rc1`'s
  existing requirement (ADR-0018), extended to `generation.answer`.
* Usage metadata can be captured. — Met; ownership assigned (Python:
  usage/latency; Laravel: Search/RAG audit persistence, ADR-0006).
* Tests do not require paid API calls. — Committed by the accepted
  architecture (a deterministic fake `Generator`, mirroring `Embedder`/
  `RetrievalPlanner`); building it is Stage 17.3's responsibility, not this
  session's.
* Prompt versions are traceable. — Met; `prompt_version` is an explicit
  fingerprint component.

### Commit boundary

git add docs/adr/0023-define-the-provider-neutral-grounded-generation-architecture-and-contract.md \
  docs/adr/README.md IMPLEMENTATION_GUIDE.md PROJECT_ROADMAP.md tasks.json \
  docs/journal/2026-08-16-r17-s01-define-generation-provider-boundary.md
git commit -m "Define generation provider boundary"
git tag -a phase-17-s01 \
  -m "Complete Stage 17.1: Define Generation Provider Boundary"

---

## Stage 17.2 — Build Grounded Prompt Assembly

### Objective

Build the provider-neutral generation foundation ADR-0023 defines: the
language-neutral `GenerationRequest`/`GenerationResult` contracts, the
`ANSWERED`/`QUALIFIED`/`INSUFFICIENT_EVIDENCE` outcome model and its
structural invariants, Laravel's deterministic context-packing policy and
`GenerationRequest` assembly, `GeneratedAnswer`/`AnswerPart`/
`EvidenceSnapshot` persistence, deterministic `GenerationResult`
validation, the generation configuration snapshot/fingerprint contract, and
the provider-neutral `Generator` interface/protocol foundation (Python
request/result models, the `rc1` `generation.answer` contract extension) —
without yet building the concrete provider adapter or writing prompt
wording. This stage is not restricted to `apps/ai`; per ADR-0023, most of
this ownership sits in `apps/api`.

### Status

Completed provider-free and accepted on 2026-08-17. No provider adapter,
prompt wording or live call is present; those remain Stage 17.3 ownership.

### Scope (per ADR-0023's ownership split)

* language-neutral generation contracts (`contracts/`);
* `GenerationOutcome` and related enums/value objects;
* `GenerationRequest`/`GenerationResult` contract definitions;
* `answer_parts[]` structural invariants;
* Laravel deterministic context-packing policy (evidence set,
  `PRIMARY`/`COMPARISON` structure, order, omission policy);
* request-scoped evidence-handle (`evidence_id`) mapping;
* `GenerationRequest` assembly, including the generation assembly input
  (question, `RetrievalResult`, already-resolved temporal/applicability
  facts, correlation context — an implementation interpretation of
  ADR-0023's ownership boundary, recorded in the R17-S01/R17-S02
  implementation-readiness journal, not a separate ADR section);
* deterministic `GenerationResult` validation (citation membership,
  outcome invariants, evidence-identity checks);
* `GeneratedAnswer`/`AnswerPart`/`EvidenceSnapshot` persistence design and
  migrations/models, with application-owned identity assignment;
* the `generation_fingerprint` contract and its versioned configuration
  set;
* Python-side provider-neutral request/result models and the `Generator`
  interface/protocol foundation (no concrete adapter yet);
* the `rc1` `generation.answer` contract extension, including its
  three-shape response envelope (`GenerationResult` /
  `GENERATION_CONTEXT_BUDGET_EXCEEDED` structural failure / provider
  failure).

Explicitly NOT this stage: exact provider-specific prompt rendering, actual
system/user/evidence prompt wording, the OpenAI adapter, or any live
provider call — all Stage 17.3.

### Acceptance criteria

* Contracts are provider-neutral; no provider-specific field exists outside
  what Stage 17.3's adapter will own.
* `GenerationResult`'s outcome/`answer_parts`/`unsupported_aspects`/
  `insufficiency_reason` invariants are enforced deterministically.
* Context-packing ownership matches ADR-0023: Laravel decides evidence
  set/order/policy; no provider-specific token measurement lives in
  Laravel.
* `GENERATION_CONTEXT_BUDGET_EXCEEDED` is representable and structurally
  distinct from `INSUFFICIENT_EVIDENCE` and from a provider failure.
* `EvidenceSnapshot` persists cited text verbatim with real, existing
  lineage identifiers (`document_chunk_id`, `document_id`,
  `ingestion_event_claim_id` — not invented fields).
* `AnswerPart`/`EvidenceSnapshot` persistent identity is assigned by
  Laravel, never accepted from the provider-facing result.
* Tests do not require a paid API call (a deterministic fake `Generator`
  exercises the contract).

### Implementation evidence

* `contracts/http/retrieval-call/rc1/generation-answer-v1.schema.json` and
  `generation-answer-response-v1.schema.json` define the language-neutral
  request and mutually exclusive response envelope.
* Laravel owns canonical assembly, whole-evidence packing, request-scoped
  handles, structural validation, fingerprinting and transactional
  `GeneratedAnswer`/`AnswerPart`/`EvidenceSnapshot` persistence.
* Python owns immutable provider-neutral models, the `Generator` protocol,
  typed boundary failures and a deterministic test double. The runtime
  dependency fails closed until R17-S03 supplies a concrete adapter.
* Focused and repository-wide provider-free verification is recorded in
  `docs/journal/2026-08-16-r17-s02-build-grounded-prompt-assembly.md`.

### Commit boundary

git add contracts apps/api apps/ai docs/adr tests
git commit -m "Build grounded generation contracts and application boundary"

---

## Stage 17.3 — Generate Answers with Citations

### Objective

Implement concrete grounded generation against the Stage 17.2 contract:
deterministic provider-specific prompt rendering, the OpenAI/gpt-5-mini
adapter (per ADR-0023, initial V1 profile only), exact prompt wording and
versioning, strict structured-output plumbing, provider/model-specific
token measurement, bounded provider retry, and provider failure mapping —
producing real, validated `GenerationResult`s end to end.

### Design note

The citation and re-extraction design constraint recorded in
`PROJECT_ROADMAP.md` was resolved by ADR-0023 (`EvidenceSnapshot`); this
stage implements that design, it does not re-decide it.

### Status

Completed and provider-verified on 2026-08-17. R17-S04 has not begun.

### Scope

* deterministic provider-specific input rendering (a pure function of
  `GenerationRequest` and prompt version);
* exact grounded-generation system/user/evidence prompt wording and
  version;
* the OpenAI adapter and gpt-5-mini integration;
* strict structured-output provider plumbing, confirmed reliable for
  generation specifically — not assumed from `RetrievalPlanner`'s narrower
  classification use of the same model, per ADR-0023;
* provider/model-specific token measurement reconciling Laravel's
  proposed package against real provider limits;
* bounded provider retry implementation and provider failure mapping;
* real provider verification (isolated, opt-in, no paid calls in ordinary
  tests).

### Implementation evidence

* `apps/ai/app/generation/prompt.py` owns the exact
  `grounded-generation-v2` system prompt. V2 makes the ANSWERED / QUALIFIED /
  INSUFFICIENT_EVIDENCE decision order explicit after bounded live V1 evidence
  showed missing requested details could otherwise cause excessive refusal.
* `apps/ai/app/generation/openai_adapter.py` owns deterministic minimal
  rendering, the adapter-private strict response model, accepted local
  gpt-5-mini token measurement, bounded transport retry and typed provider
  failure mapping. OpenAI SDK automatic retry is disabled.
* `apps/ai/app/generation/factory.py` and the authenticated existing rc1 route
  wire the concrete adapter without changing the provider-neutral request,
  result or response-envelope contracts.
* The initial profile is OpenAI `gpt-5-mini`, `generation-result-v1`,
  `grounded-generation-v2`, `openai-responses-v1`, reasoning effort `low`,
  maximum output 4,096 tokens and a 400,000-token context window. Its
  fingerprint is
  `40a18f357fbc864ff54781e607300c3374dd65829563fc2b334a2876de19b2f5`.
* Provider-free and bounded live verification, including the preserved V1
  outcome-selection failures and the separate five-call V2 correction check,
  is recorded in
  `docs/journal/2026-08-17-r17-s03-generate-answers-with-citations.md`.

### Acceptance criteria

* Answers cite source identifiers via `answer_parts[].evidence_ids`.
* Citations map to real, authorised evidence supplied in the request.
* Unsupported citations are rejected deterministically by Stage 17.2's
  validation layer, not silently accepted.
* Insufficient evidence is handled honestly (`INSUFFICIENT_EVIDENCE`, with
  a bounded `insufficiency_reason`).
* The answer cannot cite another tenant's material.
* Provider failures, and `GENERATION_CONTEXT_BUDGET_EXCEEDED`, are
  represented clearly and never conflated with each other or with
  `INSUFFICIENT_EVIDENCE`.
* Tests cover grounded, qualified, ungrounded and structural-failure
  cases.

### Commit boundary

git add apps/ai apps/api tests
git commit -m "Generate grounded answers with citations"

---

## Stage 17.4 — Add Answer Evaluation

### Objective

Evaluate groundedness, citation correctness and answer usefulness.

### Design constraint

Extend the repository-owned evaluation and quality-gate harness defined in
Stage 16.5 (`docs/adr/ADR-XXX-evaluation-and-quality-gate-architecture.md`)
with generation-specific metrics, rather than defining a second, separate
harness. See also `PROJECT_ROADMAP.md`'s "Design constraint — Quality
lineage across the pipeline."

### Status

Completed on 2026-08-17. Phase 17 is complete.

### Implementation evidence

* The repository-owned `ModelAssistedEvaluator` boundary now carries typed
  generation-result context and per-metric observations without coupling the
  application contract to an evaluator framework.
* `generation-evaluation-v2` evaluates a versioned 13-case synthetic
  population covering ANSWERED, QUALIFIED and INSUFFICIENT_EVIDENCE outcomes,
  citation membership, multi-evidence synthesis, hostile evidence and
  grounded absence. Deterministic checks remain authoritative.
* Stage 17 answer semantics use
  `openai-grounded-answer-evaluator/v1`, OpenAI `gpt-5-mini`, representation
  `generation-result-evaluation-v1` and prompt
  `grounded-answer-evaluator-v1`; fingerprint
  `9d923db3b89472a9f832b37117a6f22b45b3553bff962a88935db6ac82d9ead7`.
* Ragas remains available for Phase 16 context relevance. GEN-EXP-0001
  preserves the original Ragas answer-metric behaviour as immutable
  historical evidence; Ragas is not the Stage 17 grounded-answer evaluator.
* GEN-EXP-0002 is an evaluator-only replay of GEN-EXP-0001's byte-identical
  application observations. It made zero generation calls and did not
  selectively rerun generation. The corrected result is advisory engineering
  evidence, not unseen-generalisation evidence.
* Reports distinguish total, successfully scored, evaluator-failed and
  unevaluable AnswerParts. Every metric separately reports eligible, scored,
  failed and unevaluable counts, coverage, and the mean over successful
  scores. Unavailable cost remains `null`, never zero.

### Verified result

The authoritative deterministic result is 13/13 outcome correctness (6/6
ANSWERED, 5/5 QUALIFIED and 2/2 INSUFFICIENT_EVIDENCE), 13/13 citation
membership, 11/11 valid AnswerParts, zero invented evidence handles, zero
over-refusal, zero overclaiming, zero unsupported quantity/date/actor/
authority/procedure invention, zero internal-identifier leakage and zero
hostile-evidence leakage in the one hostile case.

The corrected advisory semantic replay scored groundedness, factual precision
and completeness at 1.0000 over 11/11 eligible AnswerParts, QUALIFIED
usefulness at 1.0000 over 5/5 cases and insufficiency correctness at 1.0000
over 2/2 cases, with no evaluator failures or unevaluable AnswerParts. The
evaluator uses the same base model as generation, so these model-assisted
scores are advisory and do not prove universal generation quality.

### Acceptance criteria

* Evaluation examples are versioned.
* Citation mappings can be checked automatically.
* Model-graded metrics are not treated as unquestionable truth.
* Human-review fields are supported.
* Prompt/model changes can be compared.

All criteria are satisfied by the versioned population, deterministic
contract/citation checks, preserved generation/evaluator lineage and immutable
GEN-EXP-0001/GEN-EXP-0002 artefacts. No genuine generation defect was
demonstrated by this bounded population.

### Phase 17 acceptance gate

Phase 17 now supports the accepted ADR-0023 boundary end-to-end: authorised
retrieved evidence is assembled into a `GenerationRequest`, processed through
the provider-neutral `Generator` and concrete OpenAI adapter, returned as a
typed ANSWERED / QUALIFIED / INSUFFICIENT_EVIDENCE result with citation-bound
natural AnswerParts, deterministically validated and persisted by Laravel with
durable evidence lineage, and measured through bounded answer-quality
evaluation. ADR-0023 remains authoritative; the evaluator correction required
no new ADR.

### Commit boundary

```text
git commit -m "Close grounded generation evaluation"
```
* Regression thresholds are documented.

### Commit boundary

git add tests docs scripts
git commit -m "Add grounded answer evaluation"

---

# Phase 18 — Conversation and Streaming

## Phase objective

Expose the RAG workflow as a persistent, streaming conversational experience.

---

## Stage 18.1 — Define Conversation Domain

### Objective

Model conversations, messages, citations and generation metadata.

### Status

Completed on 2026-08-18 through accepted ADR-0024. Architecture and
documentation only; no R18-S02 application implementation has begun.

### Accepted domain boundary

* `Conversation` is tenant-owned and contains immutable-ordinal visible turns.
* `Message` persists `USER` and `ASSISTANT` roles. Assistant messages are
  discriminated as `GROUNDED_ANSWER`, `CLARIFICATION` or `NO_ANSWER`, each
  backed by the authoritative record ADR-0024 requires.
* `GenerationRun` owns execution lifecycle, retry/idempotency, cancellation,
  model/configuration lineage and usage metadata independently of a browser
  connection.
* Citation authority remains ADR-0023's `GeneratedAnswer` → `AnswerPart` →
  `EvidenceSnapshot` model; conversation persistence does not duplicate or
  weaken it.
* Contextualisation and retrieval outcomes receive durable, typed snapshots so
  every visible controlled response has auditable application-owned lineage.
* Provisional streamed candidates and resumable chat events are explicitly
  non-authoritative. Only fully validated final generation becomes the durable
  grounded answer.

### Accepted ownership and handoff

Laravel remains the identity, tenancy, authorisation, retrieval, eligibility,
orchestration, persistence and browser-streaming boundary. Python owns only
provider-neutral model capabilities and provider-specific execution behind
authenticated `rc1` calls; it never retrieves or resolves authority,
applicability or tenancy.

Generation runs only after Laravel receives `EVIDENCE_FOUND` with a non-empty,
currently authorised final evidence set. Every other retrieval outcome follows
ADR-0024's explicit controlled handoff and never reaches the Generator.

ADR-0024 also fixes the later transport boundary: a new authenticated
`generation.stream` response may carry provisional `AnswerPartCandidate`
events, while Laravel validates, durably sequences and projects application-
owned events over SSE. `generation.answer` remains the complete-result fallback.
Connection-independent execution uses Laravel queued jobs; the framework queue
configuration already exists, but no application-owned queued generation job
has yet been implemented.

### Acceptance criteria

* Conversations are tenant-owned.
* Messages record user and assistant roles.
* Citations are persisted.
* Model and prompt versions are traceable.
* Usage metadata can be recorded.
* Conversation deletion semantics are defined.
* Cross-tenant access is prohibited.

### Verification

* ADR-0024 is `Accepted` and indexed.
* Its dated post-acceptance implementation-readiness clarification completely
  maps all eight existing `RetrievalOutcome` values without changing the
  accepted Laravel/Python ownership boundary.
* The distinction between genuine `RETRIEVAL_FAILED`, preserved
  `TEMPORAL_SCOPE_UNRESOLVED`, controlled no-answer outcomes and clarification
  remains explicit.
* Queue wording reflects the repository truth: framework/configuration support
  exists; application-owned queued generation work does not yet exist.
* No application, planner, retrieval, threshold, calibration or benchmark
  behaviour changed and no provider was called.

### Commit boundary

git add docs/adr IMPLEMENTATION_GUIDE.md PROJECT_ROADMAP.md tasks.json docs/journal
git commit -m "Define conversation domain"

---

## Stage 18.2 — Implement Chat Orchestration API

### Objective

Coordinate retrieval and generation through a stable Laravel-facing API.

### Status

Completed on 2026-08-18.

### Planned flow

1. Laravel authorises the user and tenant.
2. Laravel durably persists the user message and generation run once.
3. Connection-independent Laravel orchestration contextualises through the
   provider-neutral Python boundary where required.
4. Laravel plans, resolves eligibility and retrieves tenant-filtered evidence.
5. Laravel applies ADR-0024's complete retrieval-outcome handoff; only
   `EVIDENCE_FOUND` reaches generation assembly.
6. Python generates against the application-owned evidence package.
7. Laravel validates and atomically persists the authoritative answer,
   citations, lineage and terminal run state.
8. The API exposes the durable conversation/run state; incremental browser
   delivery remains R18-S03.

### Acceptance criteria

* Laravel remains the identity and authorisation boundary.
* AI requests carry trusted tenant context.
* User messages are persisted once.
* Failures do not create inconsistent conversation history.
* Correlation identifiers span services.
* Timeouts and cancellation are handled.
* Integration tests cover the complete request.

### Implementation evidence

* Added tenant-scoped conversations, immutable messages, durable generation
  runs, contextualisation/retrieval snapshots and controlled assistant-response
  persistence, with additive links to Phase 17 `GeneratedAnswer` authority.
* Added idempotent submission/retry, cancellation, one-active-run enforcement,
  durable database-queue execution and stale-run reconciliation.
* Added authenticated `conversation.contextualize` rc1 contracts. Python only
  contextualises bounded history; Laravel retains authorisation, retrieval,
  handoff, generation assembly, validation and atomic persistence.
* Implemented the complete ADR-0024 outcome handoff. Browser SSE remains R18-S03.

### Verification

* Focused Laravel conversation/retrieval/generation: 52 tests, 266 assertions.
* Containerised shared-contract/conversation integration: 9 tests, 66 assertions.
* Python 3.14 contextualisation/shared-contract tests: 7 passed.
* Provider-free Python regression: 532 passed, 3 skipped; three unrelated
  environment-sensitive failures remained in the generic non-experiment runtime.
* Full Laravel regression: 277 passed, 2 skipped; eight historical V3 harness
  tests require their specialised immutable runtime mount.
* Pint, Ruff, Ruff format, Mypy, PHP syntax, Compose configuration and
  `git diff --check` passed. No provider calls were made.

### Commit boundary

git add apps/api apps/ai contracts
git commit -m "Implement chat orchestration API"

---

## Stage 18.3 — Implement Streaming Responses

### Objective

Stream generated answer tokens or events to the browser.

### Status

Completed on 2026-08-18.

### Implementation evidence

* Added the distinct authenticated `generation.stream` rc1 purpose. Python
  consumes the provider's structured response stream, emits only complete
  provider-neutral `AnswerPartCandidate`s as NDJSON frames and finishes with
  one independently complete typed result or failure event.
* Laravel validates every frame, sequence, request identity and candidate
  citation before recording an application-owned projection. Invalid streams
  fail closed and never fall back mid-call to an apparently successful result.
* Added bounded, immutable `ChatDeliveryEvent` persistence, opaque run-scoped
  provisional citation references, final persistent citation reconciliation,
  expiry/purge policy and explicit progress/completion/failure/cancellation
  events. Provisional rows never become `Message`, `AnswerPart` or
  `EvidenceSnapshot` authority.
* Added an authenticated tenant-scoped SSE endpoint with no-buffering headers,
  monotonic event IDs, `Last-Event-ID` replay and connection-independent run
  execution. Disconnect never cancels work; the existing cancellation command
  stops consumption and closes upstream streaming where possible.
* Added the credentialed browser EventSource client used by R18-S04, including
  sequence de-duplication, terminal closure and malformed-event failure.

### Verification

* Focused Laravel generation/conversation/streaming: 22 tests, 106 assertions.
* Focused Python 3.14 generation contracts/adapter: 34 passed; Ruff, format and
  Mypy passed.
* Browser SSE client: 1 Vitest test; ESLint and TypeScript passed.
* Full Python: 548 passed, 4 skipped; two V3 engineering tests require the
  specialised `/evaluation/engineering` mount. Full Mypy passed. Four unrelated
  historical experiment-test import-order findings remain in the broad Ruff
  invocation; all changed Python paths pass.
* Full Laravel: 239 passed and 2 skipped in the generic host environment; the
  remaining historical ingestion/evaluation tests require configured storage,
  contract and immutable evaluation mounts. All changed Laravel paths and the
  focused integration boundary pass Pint and tests.
* Shared JSON contracts, PHP syntax, `git diff --check` and provider-free SDK
  stream-signature inspection passed. No provider calls were made.

### Acceptance criteria

* Streaming transport is documented.
* The browser receives incremental output.
* Completion is represented explicitly.
* Errors are represented explicitly.
* Cancellation releases upstream work where possible.
* Partial messages do not become silently complete.
* Citations are delivered consistently.
* Streaming tests exist.

### Commit boundary

git add apps/web apps/api apps/ai contracts
git commit -m "Add streaming chat responses"

---

## Stage 18.4 — Build Chat Interface

### Objective

Create the tenant-aware conversational UI.

### Status

Completed on 2026-08-18.

### Planned capabilities

* conversation list;
* new conversation;
* message composer;
* streaming answer display;
* source citations;
* loading, empty and error states;
* document filters;
* accessible keyboard behaviour;
* retry or regenerate where appropriate.

### Acceptance criteria

* Users can create conversations.
* Users can send messages.
* Responses stream visibly.
* Citations are inspectable.
* Conversations are tenant-scoped.
* Errors do not destroy prior messages.
* The interface is keyboard accessible.
* Critical interactions are tested.

### Actual implementation

* Added a tenant-scoped conversation workspace with history navigation,
  empty/loading/error states and an idempotent new-conversation flow.
* Added the accessible message composer: Enter submits, Shift+Enter preserves a
  newline, competing sends are disabled while a run is active, and cancellation
  remains available for the active run.
* Connected the replayable R18-S03 SSE client to visible progress and explicitly
  provisional answer parts. Terminal events replace provisional content with
  the authoritative persisted transcript; connection errors preserve both the
  conversation and the active-run constraint while EventSource reconnects.
* Projected the already-authoritative, workspace-scoped answer-part and
  `EvidenceSnapshot` graph through the conversation resource, then added
  expandable source citation references, cited text and provenance. Citations
  therefore remain inspectable after navigation or refresh rather than existing
  only in a live delivery event. Retry controls cover eligible failed runs, and
  historical messages remain readable when a later request or connection fails.
* Kept retrieval scope truthful: the V1 interface states that all documents
  eligible for the workspace and question are searched. It does not fabricate a
  user-selectable document-filter contract that the orchestration API does not
  expose.
* Kept the existing document upload surface available behind a secondary
  disclosure so chat is the primary workspace experience without removing prior
  capability.

### Verification evidence

* `npm test` — 9 files, 30 tests passed.
* `npm run lint` — passed.
* `./node_modules/.bin/tsc --noEmit` — passed.
* `npm run build` — passed; Next.js compiled, type-checked and generated all
  routes successfully. The existing multiple-lockfile root warning remains.
* `php artisan test --filter=ConversationOrchestrationTest` — 8 tests and 71
  assertions passed.
* `./vendor/bin/pint --test` on the changed API/test paths — passed.
* Full `php artisan test` — 239 tests passed; 6 failures and 43 errors were
  caused by the host test environment's absent evaluation/contract mounts and
  unconfigured document-storage bucket. The focused conversation suite is
  green; no R18-S04 assertion failed.
* `git diff --check` — passed.
* No provider calls were made.

### Commit boundary

git add apps/web
git commit -m "Build streaming RAG chat interface"

### Phase 18 acceptance gate

Closed on 2026-08-18 after the completed R18-S01 through R18-S04 boundaries
received an independent two-pass implementation review across Laravel, Python
and the browser surface.

The review confirmed the accepted ADR-0024 boundary: fail-closed incremental
candidate validation; separation of provisional delivery from atomic
authoritative persistence; HMAC-backed run-scoped provisional citations; the
PostgreSQL single-active-run invariant; complete retrieval-outcome mapping;
bounded Python contextualisation/generation ownership; genuine Responses API
streaming; and strict terminal-result reconciliation. The apparent Python
`except A, B:` syntax finding was verified as a false positive from using
Python 3.13 rather than the repository's required Python 3.14 / PEP 758 syntax.

The visual review also identified and removed unrelated legacy product
branding from the shared web shells. The committed regression test now binds
the landing, authentication and authenticated application surfaces to the
Dolved product identity. No conversation, retrieval or generation semantics
changed.

Acceptance evidence:

* independent full-stack review: no blocking issues;
* Python container verification: 40 focused tests passed; Ruff and Mypy clean;
* R18-S04 web verification: 10 files and 31 tests passed, ESLint, TypeScript
  and production build passed;
* focused Laravel conversation verification: 8 tests and 71 assertions passed;
* temporary visual-review fixture removed before closure;
* no provider calls were repeated for closure.

---

# Phase 19 — Administration

## Phase objective

Provide operational visibility and safe tenant-level controls.

### Accepted architecture

[ADR-0025](docs/adr/0025-define-the-administration-and-tenant-control-plane.md)
is the authoritative Phase 19 control-plane architecture. It retains the fixed
`OWNER`/`ADMIN`/`MEMBER` role model, keeps administrative authority and durable
state transitions in Laravel, and limits Python to provider-native ingestion and
vector operations under authenticated, Laravel-scoped contracts. Document
deletion is asynchronous and must establish ingestion quiescence before verified
vector, object and relational cleanup; historical `EvidenceSnapshot` records
remain independently inspectable after their source chunks are removed. Usage
visibility is tenant-scoped, explicitly non-billing-grade, and depends on new
content-free historical activity and normalised usage records rather than being
derived solely from live content rows.

---

## Stage 19.1 — Build Document Administration

### Objective

Allow authorised users to inspect, retry and delete documents.

### Status

Completed on 2026-08-19.

### Planned capabilities

* document list;
* processing status;
* extraction warnings;
* failure reason;
* retry ingestion;
* delete document;
* filter and search;
* inspect source metadata.

### Implementation boundary

Implement ADR-0025's document-administration boundary across Laravel, Python and
the web application. Laravel owns authorization, authoritative lifecycle state,
retry idempotency, deletion orchestration, persistence and final cleanup. Python
transports typed extraction warnings, participates in ingestion cancellation and
performs only the bounded provider-native vector deletion Laravel authorises.
Cross-language lifecycle operations remain purpose-scoped and authenticated.
Ordinary members retain document read access but cannot invoke retry or deletion.
Historical `EvidenceSnapshot` rows are preserved and made independent of the
disposable source chunk before hard chunk deletion is enabled.

### Acceptance criteria

* Permissions are enforced server-side.
* Status reflects the real ingestion state.
* Retry is idempotent.
* Delete removes or schedules removal of derived vectors.
* Failures are understandable without exposing secrets.
* Cross-tenant administration is impossible.

### Actual implementation

* Added tenant-scoped document list and detail resources with bounded filtering,
  search and pagination. The read model exposes authoritative lifecycle state,
  source metadata, safe failure details, persisted extraction warnings and
  visibly stalled deletion operations without exposing storage keys or worker
  credentials.
* Added server-authorised owner/admin retry and delete capabilities while
  retaining read access for ordinary active members. Retry reuses the immutable
  source identity, is serialized under the document lock, and uses a durable
  per-document idempotency-key ledger before publishing through the existing
  ingestion outbox.
* Added asynchronous document deletion as a Laravel-owned durable operation.
  `DELETING` blocks new claims and lease renewal; active attempts either report
  the new `Cancelled` terminal state over a distinct HMAC purpose or expire.
  Only then does Laravel durably enumerate the union of attempt and corpus-
  assignment generation scopes and publish the typed deletion event.
* Added the bounded Python deletion worker path. It accepts only Laravel's exact
  typed scopes, uses filter-based `delete(scope)` rather than collection
  removal, verifies zero remaining points and distinguishes authoritative
  collection absence from retryable unavailability and malformed scope.
* Added final Laravel cleanup of the source object, corpus pivots and content-
  bearing chunks only after verified vector cleanup. Existing and future
  `EvidenceSnapshot` rows own stable source-chunk/event lineage, their chunk FK
  becomes nullable with `nullOnDelete`, and historical citations explicitly
  report that their source document has been removed.
* Transported extraction/normalisation warnings already produced by Python into
  the validated publication evidence persisted by Laravel. Successful retry
  clears the document's previous failure fields only when ingestion actually
  reaches its terminal success state.
* Added the workspace document-administration interface with authoritative
  status cards, filtering, pagination, safe diagnostics, retry and an explicit
  asynchronous deletion confirmation that preserves prior conversation
  citations.

### Verification evidence

* Focused Laravel administration, snapshot, claim, publication and end-to-end
  ingestion suites: 79 tests and 389 assertions passed.
* Full Laravel suite: 287 tests passed and 2 skipped; 8 evaluation-definition
  tests require `/evaluation/engineering`, which is intentionally absent from
  the current isolated conversation runtime. No R19-S01 test failed.
* Python R19-S01 deletion/ingestion/worker/Qdrant suite: 35 tests passed.
* Environment-compatible broad Python suite: 544 tests passed and 3 skipped;
  the remaining 2 tests require the intentionally absent engineering
  expectations mount. The live-provider collector and one repository-path-
  specific generation collector were excluded from this provider-free gate.
* Ruff passed for the application and changed tests; Ruff formatting reported
  139 files already formatted; Mypy passed all 136 application source files.
* Web verification: 11 test files and 33 tests passed; ESLint and TypeScript
  passed; the production Next.js build completed successfully.
* Pint, JSON parsing and `git diff --check` passed.
* No provider calls were made.

### Commit boundary

git add apps/api apps/ai apps/web contracts
git commit -m "Add document administration"

---

## Stage 19.2 — Build Tenant and Membership Administration

### Objective

Allow authorised tenant administrators to manage members and roles.

### Implementation boundary

Implement ADR-0025's fixed-role capability matrix, invitation lifecycle,
membership removal, guarded ownership transfer, bounded stream reauthorization
and business-audit requirements. Laravel remains the sole authority for tenant,
membership and role state; the browser only requests authorised transitions.

### Status

Completed on 2026-08-19.

### Implemented boundary

* Added durable, tenant-scoped membership public identities, invitations,
  idempotent administration commands and privacy-safe business audit events.
* Enforced ADR-0025's fixed owner/admin/member capability matrix for listing,
  invitation issue/revoke/accept, promotion/demotion, removal, voluntary leave
  and ownership transfer. Ownership transfer locks both memberships and
  demotes the outgoing owner before promoting the target so the existing
  non-deferrable one-owner index is never violated.
* Invitations store only a token digest, return the raw link once, require the
  matching verified email, expire after the configurable seven-day window and
  remain valid independently of email delivery. Reissue closes the prior
  pending invitation and the database enforces one pending identity per
  workspace and normalized email.
* Added scheduled expiry materialisation, safe success/failure audits and
  repeat-safe mutation handling without logging invitation tokens or content.
* Added bounded membership reauthorization to existing SSE delivery loops.
  Revocation emits a safe terminal event before any further answer content.
* Added the owner/admin membership and invitation UI, ordinary-member leave
  control and verified-email invitation acceptance surface. Laravel-provided
  capabilities remain authoritative.

### Verification evidence

* 38 focused Laravel tests and 199 assertions passed across persistence,
  workspace access, membership administration and conversation streaming.
* The container-wide Laravel run passed 297 tests with two skips; eight
  historical V3 engineering tests require an intentionally absent
  `/evaluation/engineering` mount and were unchanged by this stage.
* 36 web tests passed with ESLint, TypeScript and the production Next.js build
  clean. The invitation surface also passed a local visual layout check.
* Pint and `git diff --check` passed. No provider calls were made.

### Acceptance criteria

* Members can be listed.
* Invitations can be issued and revoked where supported.
* Roles can be changed by authorised users.
* A tenant cannot accidentally lose every administrator unless deliberately supported.
* Permission changes take effect consistently.
* Audit events are recorded for sensitive changes.

### Commit boundary

git add apps/api apps/web
git commit -m "Add tenant membership administration"

---

## Stage 19.3 — Add Usage Visibility

### Objective

Expose document, storage, ingestion and model-usage information.

### Status

Implemented and verified in R19-S03. The workspace page exposes the initial
owner/admin-only usage surface; a later UI-design phase may reorganise this
surface without changing the usage ownership or aggregation contract.

### Planned metrics

* document count;
* storage usage;
* chunks indexed;
* ingestion failures;
* queries;
* embedding usage;
* generation token usage;
* estimated provider cost where available.

### Implementation boundary

Implement ADR-0025's distinction between current gauges and deletion-independent
historical interval activity. Add content-free activity records and normalised,
stage-aware usage persistence; do not infer historical usage solely from live
messages, runs or answers. Python may parse provider-native usage and calculate
estimates from versioned provider/model pricing snapshots, while Laravel owns
tenant aggregation, authorization, labelling and presentation. Unavailable values
remain unavailable rather than zero, and the surface is not billing-grade.

### Acceptance criteria

* Usage is tenant-scoped.
* Metrics have defined units and time ranges.
* Provider-reported usage is distinguished from estimates.
* Users cannot infer another tenant’s activity.
* Expensive aggregation is controlled.
* Data freshness is visible.

### Verified implementation

Laravel now persists content-free, deletion-independent workspace activity and
normalised usage observations. Current document, logical-source-byte and
indexed-chunk gauges are calculated separately from bounded 7-day, 30-day and
current-month activity. Ingestion, retrieval and generation boundaries record
stage-aware request, retry, token, latency and cost lineage without storing
source text, questions, answers or provider payloads. Provider-reported,
estimated, unavailable and genuinely local zero-cost values remain distinct.

The owner/admin-only workspace usage query is tenant-scoped, range-bounded and
aggregated by operation, provider, model, cost basis and pricing snapshot.
Ordinary members are denied the surface. The initial web presentation labels
freshness, units and non-billing-grade estimates and keeps current gauges
visually separate from historical interval activity.

The live UI review also closed two adjacent product-boundary defects. Active
Dolved surfaces, local mail identity, configuration examples and telemetry
namespaces no longer inherit unrelated legacy product branding. Authentication
entry points now preserve Laravel's signed verification flow, return typed JSON
for already-authenticated API attempts and recover stale login/register pages
without cross-origin HTML redirects. These corrections do not change retrieval,
planner, threshold, calibration or benchmark behaviour.

Verification covered focused and broad Laravel/Python suites, the complete web
suite, a production web build, Pint, Ruff, Mypy, ESLint, TypeScript and
`git diff --check`. Live browser checks exercised registration validation,
verification return, signed-out login, authenticated stale-page handling,
workspace loading and sign-out. No provider calls were made.

### Commit boundary

git add apps/api apps/ai apps/web contracts
git commit -m "Add tenant usage visibility"

---

# Phase 20 — Observability and Operations

## Phase objective

Operationalise ADR-0012's existing OpenTelemetry foundation so failures,
latency and cross-service behaviour are diagnosable without creating a
second telemetry system or weakening privacy, tenancy or application
correctness.

### Accepted architecture

Implement [ADR-0026](docs/adr/0026-operationalise-platform-observability-and-incident-response.md).
ADR-0012's OpenTelemetry SDK and Collector boundary remains canonical.
Business audit, tenant usage and operational telemetry remain distinct.
Telemetry and logging failures must never fail ordinary requests or jobs.

---

## Stage 20.1 — Standardise Structured Logging

### Objective

Emit machine-readable, correlated logs from every service.

### Status

Completed 2026-08-20.

### Planned common fields

* timestamp;
* level;
* service;
* environment;
* stable, versioned event name;
* trace and span identifiers where available;
* durable correlation and request identifiers where available;
* bounded operation kind and outcome/failure class;
* duration with an explicit unit;
* allowlisted workspace/document/conversation/run identifiers where safe;
* exception type and allowlisted frame metadata only.

### Acceptance criteria

* Logs are structured consistently.
* Laravel, Python HTTP/worker processes and Next.js server code use the
  shared vocabulary through one central boundary per language.
* Secrets, source-document content and arbitrary exception messages are
  excluded centrally by allowlist, including exception/stack paths.
* Errors retain exception type and bounded frame metadata without locals,
  arguments or uncontrolled messages.
* A formatter/handler failure cannot fail the observed request or job.
* Logging configuration differs appropriately by environment.
* Provider-free negative privacy tests cover ordinary fields and exceptions
  in all three services.

### Implementation evidence

* Laravel, Python HTTP/worker processes and Next.js server code now emit a
  shared allowlisted JSON vocabulary with stable event names, service and
  environment identity, safe durable identifiers, and trace/span correlation
  where an active context exists.
* Central formatter boundaries discard arbitrary application fields,
  exception messages, locals and arguments. Exceptions retain only their type
  and bounded frame location; failure-isolated handlers prevent logging faults
  from failing the observed request or job.
* Laravel's stderr channel retains local debug visibility while production
  remains information-level. Python HTTP and worker entry points install the
  same privacy-safe formatter. Next.js server-side transport failures use its
  matching structured boundary.
* Focused privacy and failure-isolation verification passed in every service:
  Laravel 3 tests / 11 assertions, Python 33 focused tests, and the complete
  web suite of 17 files / 52 tests.
* The collectable Python suite passed 548 tests with 3 skipped and the Laravel
  suite passed 306 tests with 2 skipped. Their remaining two and eight failures
  respectively are the pre-existing evaluation tests whose intentionally
  absent `/evaluation/engineering/` fixtures are not part of this runtime.
* Ruff lint/format, Mypy, Pint, ESLint, TypeScript, JSON validation and
  `git diff --check` passed. No provider calls were made.

### Commit boundary

git add apps/web apps/api apps/ai docs
git commit -m "Standardise structured platform logging"

---

## Stage 20.2 — Operational Metrics, Platform Operations Foundation and Dashboard

### Objective

Establish the separately-authorised platform operations plane, complete the
operational metric surface and expose a curated, bounded health dashboard.

### Status

Completed 2026-08-20.

### Planned scope

* immutable user public identity, disable state and `ADMINISTRATOR` platform role;
* live platform-administrator authorization, session invalidation and
  last-active-administrator protection;
* dedicated deployment credential and non-browser bootstrap/grant/revoke/
  recovery command;
* atomic platform command and audit records;
* operational metric gap closure across API, ingestion, retrieval,
  generation, conversation, deletion, providers and dependencies;
* a predefined-query-only internal metrics reader;
* a curated platform health dashboard with explicit unavailable/freshness
  states and tenancy/platform-authorization negative tests.

### Acceptance criteria

* Platform authority is independent of every workspace role in both directions.
* Bootstrap and role mutation are authenticated, idempotent and durably audited.
* Operational metrics have stable names, explicit units and bounded labels;
  workspace/user/document/run identifiers never become metric labels.
* The browser cannot submit PromQL or any other backend query language.
* Backend failures render explicitly unavailable and never affect ordinary use.
* Stage 20.2 is independently complete before Stage 20.3 consumes its authority.

### Implementation evidence

* Users now have an immutable public identity, optional disable state and one
  nullable platform role (`ADMINISTRATOR`). A live Gate keeps this authority
  independent of workspace membership in both directions, and disabling or
  deleting a user invalidates database sessions.
* A dedicated versioned deployment credential authenticates the non-browser
  bootstrap/grant/revoke/recovery command. Idempotent command records, role
  mutation and content-free audit events commit atomically, with locked
  last-active-administrator protection and no browser self-promotion path.
* Laravel and Python now emit bounded operation, dependency, queue-age/depth
  and stuck-operation metrics across ingestion, retrieval, reranking,
  generation, conversation delivery and deletion. First accepted answer-part
  latency is measured from existing generation-run state. No tenant or entity
  identity is a metric label, and telemetry remains failure-isolated.
* Laravel's operational reader owns nine fixed, bounded Prometheus queries,
  filters backend labels and caps result sets. The browser cannot supply a
  backend query. Metrics failures and missing observations are represented as
  unavailable rather than zero.
* The server-rendered `/app/operations` dashboard is discoverable only by a
  live platform administrator. It provides the curated health view and a
  separate link to the local specialist Grafana console without embedding it.
* Provider-free focused verification passed: Laravel 16 tests / 136
  assertions, Python 53 retrieval/provider/metrics tests, and the complete web
  suite of 18 files / 56 tests. The collectable Python suite passed 549 tests
  with 3 skipped; Laravel passed 314 tests with 2 skipped. Their remaining two
  and eight failures are the known evaluation tests whose isolated
  `/evaluation/engineering/` inputs are physically absent.
* PostgreSQL migration SQL preflight, Ruff lint/format, Mypy, Pint, ESLint,
  TypeScript, JSON validation and `git diff --check` passed. No provider calls
  were made.

### Commit boundary

git add apps infrastructure docs
git commit -m "Build platform operations visibility"

---

## Stage 20.3 — Trace Coverage, Collector Sampling and Operational-Policy Reconciliation

### Objective

Complete cross-service trace coverage, add Collector-owned probabilistic
sampling and implement the authenticated desired-policy reconciliation boundary.

### Status

Completed on 2026-08-20.

### Planned scope

* Laravel-to-Python rc1 W3C trace propagation;
* retry/deletion outbox trace propagation and missing first-instance spans;
* trace-attribute allowlist reconciliation;
* the pinned Collector's `probabilistic_sampler` processor with no competing
  application-side ratio sampler;
* versioned desired sampling/retention policy and required-target manifests;
* append-only deployment attempts, authenticated acknowledgements and
  compare-and-set derived status;
* dedicated reconciliation HMAC credentials and protocol;
* platform-admin policy/status UI and real local `ACTIVE` proof.

### Acceptance criteria

* One trace context propagates across HTTP, queues and external providers.
* Sensitive content is absent and attributes remain allowlisted.
* Sampling is enforced only by the Collector and is trace-ID consistent.
* Desired policy is never presented as active without matching authenticated
  per-setting/per-target evidence.
* Stale, replayed, conflicting and superseded acknowledgements fail closed.
* Local traces and reconciliation state are inspectable without affecting
  ordinary application correctness.

### Implemented result

* Laravel injects the active W3C `traceparent`/`tracestate` into all
  synchronous rc1 calls to Python. Retry and deletion outbox events retain the
  originating context, queued generation restores its parent, and previously
  missing administration, contextualisation, generation, reranking and
  deletion spans use the shared privacy allowlists.
* The pinned OpenTelemetry Collector owns the only ratio sampler. Its traces
  pipeline uses the trace-ID-consistent `probabilistic_sampler`; Laravel and
  Python remain AlwaysOn/default so competing application sampling cannot
  create partial service traces.
* Operational policy is immutable and versioned. A required-target manifest
  expands each setting into independently derived `PENDING`, `ACTIVE` or
  `FAILED` targets; append-only deployment attempts and a current-attempt
  pointer prevent stale or superseded acknowledgements changing current
  state. Desired state is never represented as applied state.
* The `or1` reconciliation protocol uses dedicated rotatable HMAC credentials,
  timestamp and nonce replay protection, bounded bodies, purpose/path/plan/
  attempt binding, generic rejection responses and privacy-safe logs. The
  platform operations page shows aggregate and per-target state and only
  records desired policy; the reconciler independently verifies effective
  Collector configuration before acknowledging it.
* A real local reconciliation moved the Collector sampling target to `ACTIVE`;
  the other four settings remained truthfully `PENDING` because their targets
  had not acknowledged them. A real Tempo trace joined Laravel API, outbox
  publisher and Python worker spans under one trace ID while synthetic content
  and entity metric labels remained absent.

### Verification evidence

* Focused Laravel acceptance: 57 tests / 338 assertions.
* Web: 19 files / 58 tests; ESLint and TypeScript passed.
* Python provider/trace focus: 54 tests; Ruff lint/format and focused Mypy over
  142 source files passed. The collectable provider-free suite passed 549
  tests with 3 skipped; its two remaining failures require the intentionally
  absent engineering expectations fixture.
* Laravel passed 320 tests with 2 skipped; its eight remaining failures require
  the intentionally absent engineering corpus fixture.
* PostgreSQL migration SQL preflight and local migration, Pint, pinned
  Collector `validate`, JSON validation and `git diff --check` passed. No
  provider calls were made.

### Commit boundary

git add apps infrastructure docs
git commit -m "Complete trace and telemetry policy operations"

---

## Stage 20.4 — Define SLOs, Alerts and Runbooks

### Objective

Define calibrated service-level indicators, actionable alert conditions and
operational runbooks without treating controlled product outcomes as failures.

### Status

Completed 2026-08-20.

### Planned alerts

* API error-rate increase;
* queue backlog;
* dead-letter messages;
* ingestion failure spike;
* database unavailable;
* vector store unavailable;
* provider failure or rate limiting;
* latency threshold breach;
* storage capacity concern.

### Acceptance criteria

* Every alert has an owner or response expectation.
* Alerts describe impact rather than only internal symptoms.
* Thresholds are justified.
* Runbooks exist.
* Alerts avoid obvious noise.
* Local or test alert verification is possible where practical.
* SLI numerator/denominator rules reuse ADR-0023/ADR-0024 outcome semantics.
* Main admin shows curated alert/SLO state while Grafana/Alertmanager retains
  acknowledgement, silencing and deep-diagnostic ownership.

### Architecture reconciliation

Accepted ADR-0026 already supplies the durable architecture required by this
architecture-mode stage, so no new ADR was needed. The implementation uses
Prometheus recording/alert rules as the SLI and alert source, a separately
pinned Alertmanager with a no-delivery local receiver seam, and bounded
read-only summaries in Dolved. Alertmanager remains the only alert-state,
grouping, acknowledgement and silence owner.

The provisional 99.0% rolling-28-day API and conversation objectives are
labelled `PROVISIONAL_UNMEASURED`. Empty or unreachable time series report
`No representative data` or `Unavailable`, never a perfect score. Conversation
technical success reuses ADR-0024 exactly: `completed`,
`retrieval_no_answer` and `clarification_required` are successes; `failed` is
failure; `cancelled` is excluded. Final latency SLOs, multi-window burn alerts,
telemetry-absence paging and capacity alerts remain explicitly deferred until
representative production/environment signals exist.

### Implemented boundary

* Repository-owned Prometheus configuration loads six SLO recording rules and
  ten bounded operational alerts into the pinned LGTM Prometheus.
* Pinned Alertmanager `v0.33.1` provides a local inspection UI/API and a
  configurable future receiver boundary without contacting a person locally.
* Alert rules carry severity, subsystem, operator ownership, impact, response
  expectation and a stable runbook link. Urgent and warning inhibition prevents
  duplicate lower-severity noise.
* The platform operations adapter performs only predefined PromQL, caps results
  and active alerts, allowlists alert fields and rejects arbitrary specialist
  labels/annotations from the curated surface.
* The platform operations page shows provisional SLO status and active alerts,
  with separate Grafana and Alertmanager links; it implements no alert mutation.
* Runbooks preserve normal state machines and bounded provider retry policy and
  prohibit manual completion or correctness tuning during incident response.

### Verification evidence

* Pinned Prometheus `promtool` validated one config and 16 rules; provider-free
  rule simulations passed for controlled outcomes, sustained technical failure
  and isolated rate limiting.
* Pinned Alertmanager `amtool` validated the receiver/inhibition config; the
  live local service reached `ready` on `v0.33.1` and Prometheus reported all 16
  loaded rules healthy.
* Focused Laravel platform operations: 8 tests / 64 assertions.
* Focused web operations/alert governance: 2 files / 5 tests; the full web
  suite passed 20 files / 61 tests; ESLint and TypeScript passed.
* The Laravel suite passed 322 tests with 2 skipped; its 8 remaining failures
  require the intentionally absent `/evaluation/engineering/corpus.json`
  fixture and are unrelated to this stage.
* The live Laravel reader returned two SLOs, available Alertmanager state and
  zero active alerts without fabricating a healthy SLO value.
* Pint, Compose validation, JSON validation and `git diff --check` passed. No
  provider calls were made.

### Commit boundary

git add .env.example compose.yaml apps/api apps/web infrastructure/observability docs/operations docs/journal/2026-08-20-r20-s04-define-operational-alerts.md tasks.json PROJECT_ROADMAP.md IMPLEMENTATION_GUIDE.md
git commit -m "Define operational SLOs alerts and runbooks"

---

## Phase 20 acceptance gate

Completed 2026-08-21.

An independent architecture review confirmed the implemented Phase 20
boundary against all nine areas of Accepted ADR-0026. Seven areas were
conformant without correction. The two bounded findings were closed before
the gate:

* `make test` now verifies that the actual pinned Collector image still ships
  the `probabilistic_sampler` processor, validates the committed Collector
  configuration, and checks that sampling precedes batching in the traces
  pipeline;
* ADR-0026 records a dated post-acceptance factual clarification that the
  existing ingestion-worker credential family authenticates both ingestion
  and deletion through distinct signed purposes. The observability
  reconciliation and platform-administration credentials remain separate.

The pre-Phase-20 verification debt recorded at the Phase 19 gate was also
removed in focused commit `fb294ed`: full Mypy now passes, historical V3
evaluation tests use explicit portable test fixtures without weakening
lineage checks, and current versus superseded V3 experiment boundaries remain
fail closed.

Final repository acceptance passed `make format-check lint typecheck test ps`
and `make aws-status`: Laravel 330 passed / 2 skipped / 1,606 assertions;
Python 562 passed / 4 skipped; Ruff lint/format, Mypy over 211 source files,
Pint, ESLint, TypeScript and the pinned-Collector configuration guard all
passed; all required containers were healthy and local AWS resources
reconciled. No provider calls or protected evaluation splits were used.

---

# Phase 21 — Product Experience and Interface Design

## Phase objective

Give the product's chat, document and administration surfaces one coherent,
accessible, production-quality interface built on a shared design system,
rather than treating visual design as incidental polish layered onto
individual pages after the fact.

### Scope note

This phase is product-wide, not administration-only. Phase 19 delivered
administration functionality without a design pass; without a shared design
system applied consistently across chat, documents, the shared shell and
administration, a redesigned administration surface would read as a
different application from the rest of the product. Administration receives
particular emphasis in Stage 21.2 because it introduces the platform's first
real information-architecture and destructive-action surfaces, but the
design system itself (Stage 21.1) and the interface-state work (Stage 21.3)
apply everywhere.

---

## Stage 21.1 — Define the Product Design System

### Objective

Establish one shared design system — typography, spacing, colour,
components, navigation and responsive rules — that the whole product draws
from, while preserving the established Dolved identity.

### Status

Completed on 2026-08-21.

### Planned scope

ADR-0027 is the accepted architecture boundary for this stage. In addition to
the design-system primitives below, Stage 21.1 establishes its route-backed
adaptive shell and development/test-only component-reference route. The
bounded citation presentation-contract extension and authorised document
detail route are implemented later in Stage 21.3.

* typography scale and usage rules;
* spacing and layout grid;
* colour palette, including light/dark and status/semantic colours;
* a shared component library (buttons, forms, tables, dialogs, badges,
  navigation);
* a consistent navigation and information-shell pattern across chat,
  documents and administration;
* responsive breakpoints and behaviour;
* an accessibility baseline (target conformance level, keyboard-focus and
  contrast rules) that every later stage is built against.
* the accepted route hierarchy, including canonical conversation URLs,
  workspace administration routes and `/app/platform/operations`;
* a development/test-only component-reference route for visual review.

### Acceptance criteria

* The design system is documented and has a single source of truth.
* Existing Dolved branding and identity are preserved, not replaced.
* Components have defined states (default, hover, focus, disabled, loading,
  error) rather than being designed only for the success case.
* Navigation and layout are consistent across chat, documents and
  administration.
* The accessibility baseline is explicit and testable, not aspirational.
* The system is usable by later stages without further architectural
  negotiation.
* Route identity, conversation selection and active navigation follow
  ADR-0027 rather than client-only selection state.
* The component-reference surface is absent from production builds.

### Delivered implementation

* Installed Tailwind CSS v4, the repository-owned shadcn/Radix component
  foundation, Lucide icons and explicit `next-themes` dark/light selection.
* Added the two-layer semantic token contract, Source Sans 3 interface type,
  Sora lowercase wordmark and provider-free completeness/contrast guards.
* Added shared buttons, fields, cards, badges, status, notice, loading, empty,
  dialog, menu, tabs, pagination, evidence and streaming-state primitives.
* Consolidated authenticated navigation into one adaptive shell with a desktop
  sidebar and mobile drawer, capability-gated destinations and route-backed
  active state.
* Added canonical conversation, document, workspace-administration and
  platform-operations routes without changing their server-owned authority.
* Added the development/test-only `/design-system` component reference. Human
  visual review accepted its dark and light presentation after correcting the
  legacy reset that had overridden utility spacing and the authentication
  story panel's dark-theme contrast.

### Verification evidence

The complete web suite passed 24 files / 72 tests. ESLint, TypeScript, the
production Next.js build, `git diff --check`, semantic token completeness,
required foreground/background contrast pairs, route-backed navigation and
authority-visibility tests all passed. The local web, API, AI, PostgreSQL and
Qdrant services were healthy; the landing and reference surfaces returned HTTP
200. No provider calls were made and no retrieval, planner, threshold,
calibration or benchmark behavior changed.

### Commit boundary

git add apps/web docs
git commit -m "Define the product design system"

---

## Stage 21.2 — Design the Administration Experience

### Objective

Design clear document, membership and usage information architecture for
the administration surface Phase 19 built, grounded in ADR-0025's actual
owner/admin/member capability model.

### Status

Completed on 2026-08-21.

Implementation evidence:

* the administration overview, people, invitations and usage destinations are
  distinct route-backed sections inside the shared application shell;
* membership actions render only from the server-provided ADR-0025 capability
  object, with owner and administrator authority described explicitly;
* document deletion, member removal, role changes, ownership transfer and
  workspace departure use accessible confirmation dialogs rather than browser
  prompts;
* document retry/deletion, invitation validity and usage availability now have
  explicit pending, failed, resolved and unavailable presentations;
* the development/test-only `/design-system/administration` route presents the
  complete administration component set with representative fixture state for
  review (the authenticated application itself retains ADR-0027's adaptive
  left-sidebar shell);
* visual review accepted the information architecture, with destructive-button
  padding retained as a final Stage 21.4 in-context tuning note;
* web verification passed 24 test files / 72 tests, ESLint, TypeScript, the
  production build and `git diff --check`.

### Planned scope

* document, membership and usage information architecture and navigation;
* visible, unambiguous owner/admin/member capability boundaries in the
  interface itself, not only enforced server-side;
* confirmation flows for destructive operations (document deletion, member
  removal, ownership transfer);
* retry, deletion and invitation flow design, including their failure,
  in-progress and already-resolved states.

### Acceptance criteria

* Information architecture reflects ADR-0025's actual capability matrix,
  not an assumed or simplified one.
* A member without a capability cannot be misled into believing they have
  it by the interface.
* Every destructive operation requires an explicit, unambiguous
  confirmation step.
* Retry, deletion and invitation flows have designed states for their
  in-progress, failed and already-resolved outcomes, not only their
  success path.
* The administration design is visually and structurally consistent with
  the Stage 21.1 design system, not a separate visual language.

### Commit boundary

git add apps/web docs
git commit -m "Design the administration experience"

---

## Stage 21.3 — Implement Complete Interface States

### Objective

Implement every interface state the product's real behaviour can produce,
across chat, documents and administration, not only the success path.

### Status

Completed on 2026-08-21.

Implementation evidence:

* routed chat now renders explicit loading skeletons, empty guidance, grounded
  success, provisional streaming, controlled/insufficient outcomes, cancelled
  and retryable failures using the shared component language;
* mid-stream authorization loss clears provisional output, explains that
  workspace access ended and permanently disables the composer for that
  mounted session;
* durable grounded answers display outcome, unsupported-aspect and
  insufficiency information without hiding prior conversation content;
* multi-document upload exposes waiting, initialising, transferring,
  verifying, completed and independently retryable failed files with accessible
  progress state;
* invitation acceptance gives a bounded safe explanation for expired, revoked,
  already-resolved and wrong-account outcomes without exposing internals;
* administration's failed ingestion, asynchronous deletion, empty, partial and
  unavailable usage states remain implemented through the R21-S02 surfaces;
* web verification passed 24 test files / 73 tests, ESLint, TypeScript, the
  production build and `git diff --check`.

### Planned states

* loading;
* empty;
* success;
* partial data;
* unavailable usage/cost figures;
* failed ingestion;
* deleting (in-progress, asynchronous);
* permission loss (mid-session revocation);
* expired invitations.

### Acceptance criteria

* Every state above is implemented, not just visually mocked.
* States are responsive across representative desktop and mobile sizes.
* Interactive elements are keyboard-operable and have visible focus states.
* No interface surface silently shows nothing when data is unavailable,
  loading or partial.
* Error and unavailable states explain what happened without exposing
  internal detail.

### Commit boundary

git add apps/web
git commit -m "Implement complete interface states"

---

## Stage 21.4 — Visual and Usability Acceptance

### Objective

Validate the redesigned interface against real application behaviour and
data before accepting this phase, closing the explicit design acceptance
gate this phase exists to provide.

### Status

Completed on 2026-08-21.

Implementation evidence:

* the running application was reviewed with real workspace, document,
  membership, invitation, usage and Platform Operations data across desktop
  and mobile layouts in both themes;
* navigation now closes after mobile selection, presents one unambiguous active
  destination, preserves white foregrounds on brand surfaces and exposes sign
  out from the named account menu;
* invitation links wrap safely and provide a copy affordance, recent
  conversations no longer overlap and destructive document/member actions use
  a compact optical treatment without reducing their accessible target;
* authentication branding and mail identity consistently use Dolved, verified
  sign-in/registration flows expose safe actionable errors and the dedicated
  Platform Operations login context remains bounded to its exact return route;
* Platform Operations was reviewed and reorganised visually into legible
  status, telemetry, alert and desired-policy regions without changing the
  underlying ADR-0026 semantics;
* server/client timestamp rendering is deterministic in UTC, preventing the
  live document-list hydration failure discovered during acceptance;
* shared component regression coverage, ESLint, TypeScript, the production
  build and `git diff --check` passed at closure.

### Verification performed

* review against real application data, not static mockups;
* screenshots captured at representative desktop and mobile sizes;
* accessibility checks against the Stage 21.1 baseline;
* regression tests for shared UI components;
* a live walkthrough of chat, documents and administration together, to
  confirm they read as one product.

### Acceptance criteria

* Screenshots exist for representative desktop and mobile sizes across
  chat, documents and administration.
* Accessibility checks pass against the documented baseline.
* Shared UI components have regression test coverage.
* The review is conducted against a running application with real data,
  not isolated mockups.
* No page reads as visually or structurally inconsistent with the rest of
  the product.

### Commit boundary

git add apps/web docs
git commit -m "Complete visual and usability acceptance for product experience"

---

## Stage 21.5 — Split Platform Operations into Route-Backed Sections

### Objective

Implement accepted ADR-0028 by separating the existing Platform Operations
dashboard into route-backed Overview, Active alerts, Global telemetry and
Operational policy destinations inside the shared adaptive shell.

### Status

Completed on 2026-08-21.

### Delivered implementation

* split Platform Operations into independently addressable Overview, Active
  alerts, Global telemetry and Operational policy routes inside the shared
  adaptive shell;
* added route-derived platform contextual navigation on desktop and mobile,
  retaining one stable capability-gated primary destination;
* corrected the platform-administrator gate to conceal denial as `404`, added
  an exact four-route safe-login-return allowlist and made every owning route
  map concealed results through its shared `notFound()` presentation;
* preserved ADR-0026 operational ownership while keeping the overview concise
  and moving complete alert, telemetry and policy information to their owning
  routes;
* made policy mutation fail closed when platform authority is lost after page
  load, without retry-to-success or permission disclosure;
* completed the final ADR-0027 conformance work: live product surfaces consume
  semantic tokens; Laravel owns the safe citation/source presentation; document
  and conversation deep links are server-authorised and tenant-safe; typed
  timeout, retraction and ordinary failures remain distinct; and chat uses one
  deliberately bounded live-status region rather than announcing transcript
  mutations.

### Verification evidence

The exact closure tree passed 115 web tests, 332 Laravel tests with 2 skipped
and 1,634 assertions, and 562 Python tests with 4 skipped. ESLint, TypeScript,
the production Next.js build, Pint, Ruff format/lint, Mypy, the pinned Collector
configuration guard and `git diff --check` all passed. Focused route, citation,
concealment, theme/token and accessibility regressions passed. Direct visual
review covered all four Platform Operations routes, both themes, desktop and a
390-pixel mobile viewport; no horizontal overflow remained and mobile route
selection dismissed the drawer. An independent final review found no new
regressions or accepted-ADR deviations.

### Planned scope

* retain one capability-gated `Platform operations` entry in the stable
  primary navigation region;
* add the four-entry Platform Operations contextual region and its mobile
  drawer equivalent;
* preserve `/app/platform/operations` as Overview and add `/alerts`,
  `/telemetry` and `/policy` specialist routes;
* redistribute the existing curated snapshot without changing telemetry,
  alerting, SLO or policy semantics and without introducing another API;
* correct the platform-operations gate to the ADR-0026-required concealed
  `404` response for authenticated non-platform-administrators, scoped to
  that gate only;
* preserve framework-neutral typed data results, with each owning route
  mapping `concealed` to its shared not-found presentation;
* extend the login return target through an exact four-route allowlist only;
* fail closed when platform authority is revoked between Policy page load and
  mutation submission, with no retry-to-success or permission disclosure.

### Acceptance criteria

* All four destinations are independently addressable, refreshable and
  selected from route-derived contextual navigation state.
* Overview contains concise summaries and links rather than duplicating the
  complete specialist interfaces.
* Direct access, login return and mid-session authority loss preserve the
  authorization and concealment boundary defined by ADR-0028.
* Alerts, telemetry and policy retain their applicable empty, partial, stale,
  unavailable and failure states without fabricating healthy or zero values.
* Desktop/mobile navigation, keyboard operation, focus, themes and WCAG 2.2 AA
  presentation pass the existing ADR-0027 acceptance baseline.
* Laravel and web regression coverage proves the correction is limited to the
  Platform Operations authority plane.

### Commit boundary

git add apps/api apps/web docs tasks.json IMPLEMENTATION_GUIDE.md PROJECT_ROADMAP.md
git commit -m "Split platform operations into routed sections"

---

## Phase 21 acceptance gate

Closed on 2026-08-21. The shared product language now applies across public,
authentication, chat, document, workspace-administration and platform-
operations surfaces. ADR-0027's citation, route-ownership, typed-state and
accessibility requirements and ADR-0028's Platform Operations route split were
verified against the running application and complete repository suite. No
retrieval, planner, threshold, calibration, benchmark or provider behaviour
changed during closure.

---

# Phase 22 — Testing and Quality Strategy

## Phase objective

Create a layered test strategy that catches regressions without requiring every check to be an expensive end-to-end test.

---

## Stage 22.1 — Establish Test Taxonomy

### Objective

Document which behaviours belong in unit, integration, contract, feature and end-to-end tests.

### Status

Completed on 2026-08-22.

### Planned categories

* frontend component tests;
* frontend integration tests;
* Laravel unit tests;
* Laravel feature tests;
* Python unit tests;
* Python integration tests;
* event-contract tests;
* HTTP-contract tests;
* infrastructure tests;
* end-to-end platform tests;
* retrieval evaluation;
* generation evaluation.

### Acceptance criteria

* Test categories have clear purposes.
* External providers can be faked.
* Critical tenant boundaries have automated tests.
* Expensive tests are separated from fast feedback.
* Test data strategy is documented.
* CI stages can map to the taxonomy.

### Delivered implementation

* Accepted ADR-0029 as the platform-wide testing and contract-verification
  boundary after implementation-readiness review against the actual runners,
  adapters, baselines, contracts and Compose mounts.
* Published `docs/testing/README.md` as the operational reference for the
  fifteen test categories, test-data ownership, cleanup, failure evidence,
  flake/timeout/quarantine rules and the Phase 23 CI handoff.
* Separated provider-free historical policy enforcement, deterministic
  current-pipeline regression, generation-evidence integrity and optional
  live quality evidence without allowing one category to overclaim another.
* Recorded the isolated `dolved-e2e` identity, compact Level-1 fixture corpus,
  physical mount exclusion and deterministic provider/heavyweight-model
  boundary that later E2E stages must implement fail closed.
* Renamed the existing Laravel feature test from
  `EndToEndIngestionOrchestrationTest` to
  `IngestionOrchestrationFeatureTest`; only the filename and PHP class name
  changed, preserving every assertion and runtime behaviour.

### Verification evidence

The renamed Laravel feature test passed unchanged. PHP formatting, JSON
validation, documentation/reference checks and `git diff --check` passed. The
accepted ADR remains documentation/architecture only: no provider call, runtime
dependency, application behaviour, retrieval configuration, threshold,
calibration population, benchmark or held-out content changed.

### Commit boundary

git add docs
git commit -m "Document platform testing strategy"

---

## Stage 22.2 — Add Contract Tests

### Objective

Verify that Laravel and Python agree on shared HTTP and event contracts.

### Status

Completed on 2026-08-22.

### Acceptance criteria

* Contracts are versioned.
* Producers validate emitted payloads.
* Consumers validate received payloads.
* Breaking changes fail tests.
* Example payloads remain valid.
* Unsupported versions are tested.
* Contract generation or duplication is controlled.

### Delivered implementation

* Added versioned request and response schemas for all nine ingestion-worker
  operations and all three document-deletion-worker operations, with one
  shared valid/invalid fixture inventory consumed independently by PHPUnit and
  Pytest.
* Added twelve shared HMAC signing vectors and proved that the real Python
  signer and Laravel verifier agree on the complete signed operation surface.
* Proved that the real Python ingestion and deletion clients emit the same
  canonical request structures bound by the shared fixtures; claim fixtures
  also remain valid against their authoritative event contracts.
* Added one shared Retrieval rc1 fixture inventory covering every request and
  response schema, including unknown fields, missing required fields, invalid
  values and unsupported contract versions in both languages.
* Added `make evaluation-policy-gate`, including non-zero process propagation
  for an existing policy failure and version-aware loading of the immutable V1
  promoted baseline through the existing comparison-only adapter.
* Added `make evaluation-generation-verify`, which makes no provider or
  evaluator calls and validates each historical generation run according to
  its own checksum-declared inventory and schema version. It verifies
  population and observation bindings and recomputes only deterministic
  outcome, citation, over-refusal, overclaiming and leakage evidence.

### Verification evidence

The shared contract tests passed in PHPUnit (176 assertions) and Pytest. The
full Laravel suite passed with 336 tests and 1,789 assertions (two intentional
skips); the full Python suite passed with 570 tests (four skips); and the web
suite passed with 115 tests. Both provider-free evaluation gates passed, while
a temporary degraded retrieval candidate failed with a non-zero exit and
`regression:recall_at_k`. All 35 affected JSON Schemas passed Draft 2020-12
meta-validation. Pint, ESLint, Ruff lint/format, TypeScript, Mypy, the Collector
configuration test and `git diff --check` passed. No provider calls were made.

### Commit boundary

git add contracts apps/api apps/ai scripts makefile docs
git commit -m "Add shared contract tests"

---

## Stage 22.3 — Add End-to-End Ingestion Tests

### Objective

Test the complete document path from upload to searchable vectors.

### Status

Complete. The first exact-commit deterministic orchestration-regression
baseline was explicitly reviewed, promoted and verified provider-free, and the
isolated ingestion acceptance journey passes with its current deterministic
profile lineage.

### Planned location

tests/end-to-end/

### Implemented boundary

* `compose.e2e.yaml`, `.env.e2e` and `scripts/e2e/` define a disposable
  `dolved-e2e` project with no external-provider credentials and fail-closed
  deterministic adapter selection.
* The Playwright journey uses the real browser, Next.js, Laravel, PostgreSQL,
  LocalStack S3/SQS, publisher, Python worker and Qdrant. It proves upload,
  outbox publication, queue processing, real parsing/chunking, signed completion,
  indexed status, retrievability, typed corrupt-document failure and cross-tenant
  concealment.
* Test-only Laravel commands bootstrap deterministic identities, approve the
  uploaded fixture and provision the isolated deterministic dense/sparse
  generation. They refuse to run outside the E2E environment and are covered by
  provider-free tests.
* Python settings and factories select deterministic embedding, sparse,
  reranking and catalogue-planning adapters only for the approved isolated
  environments. Unknown catalogue questions and incomplete/mixed profiles fail
  closed.
* `make test-splade-integration` independently loads the configured real SPLADE
  model and performs bounded structural inference; the Playwright suite remains
  deterministic and does not claim real-model quality.
* `make evaluation-retrieval-current-candidate` executes the current retrieval
  implementation against the approved 42-case / 126-variant engineering
  snapshot and the independent 93-version source catalogue. A private,
  environment-guarded Laravel command first persists the real organisation,
  document, authority and applicability state and invokes the production
  `EligibilityResolver` for every authored plan. Its typed artefact is then
  consumed by Python for deterministic dense/sparse encoding, real Qdrant, RRF
  and deterministic reranking. Expected EvidenceUnits are used only after
  retrieval for scoring; they never create search inputs or eligibility scopes.
* The exact-run eligibility artefact digest records the repository commit while
  a separate semantic comparability digest binds resolver source/configuration,
  population mappings, fixed time and resolved outcomes into the deterministic
  profile without making repository identity a comparison variable. Candidate
  output is explicitly marked **CANDIDATE — NOT PROMOTED**.
  `make evaluation-retrieval-current` can compare only against a checksum-valid,
  human-promoted deterministic baseline and cannot create or refresh that
  baseline itself.

### Verification evidence

* Clean `make test-e2e`: 1 Playwright journey passed and the isolated stack and
  volumes were removed after success.
* `make test-splade-integration`: 1 passed using the real configured SPLADE
  model.
* Focused real-eligibility boundary: 5 Laravel tests / 23 assertions passed;
  the disposable cross-language diagnostic accounted for 42 cases, 126
  variants, 93 independent source chunks and 126 resolver outcomes.
* Full final suites: Laravel 351 passed / 2 skipped / 1,868 assertions; Python
  provider-free 601 passed / 14 integration tests deselected; web 115 passed.
* Pint, Ruff lint/format, Mypy, ESLint, both TypeScript projects, shell syntax,
  Compose configuration, JSON parsing and `git diff --check`: passed.
* No OpenAI or Voyage calls were made.
* The exact reviewed 42-case / 126-variant candidate at commit
  `89d407612623acf08bef99d7871fad16a2f1984e` was promoted byte-for-byte as
  `deterministic-v1`; its semantic comparison digest is
  `714a1211558dadf1f7d11483356e3c47c58cdd5e382727b1443cafd3c67bb803`.
* The deterministic comparison gate passed with the checksum-valid Laravel
  foreign-workspace isolation probe. The approved ADR-0029 clarification
  substitutes that bound non-metric proof only for `cross-workspace`; all other
  load-bearing slices now enforce their configured metric tolerances.
* Governance tamper tests fail closed for baseline, promotion, candidate,
  execution profile and isolation-artefact drift. The final focused governance,
  schema and input suite passed (42), and the promoted comparison gate passed
  again with zero metric deltas and no absolute failures.
* The initial clean E2E rerun correctly failed closed because Laravel still
  provisioned historical deterministic dense v1 and sparse v1 profiles while
  Python executed dense v3 and sparse v4. A shared versioned E2E profile vector
  now binds the independently calculated PHP/Python fingerprints and Qdrant
  configuration without changing production profiles or mismatch validation.
* The subsequent single clean Playwright run passed: the representative
  document reached `INDEXED`, the corrupt document reached `FAILED`, expected
  evidence was retrieved, foreign-workspace access returned 404, and the
  disposable stack and volumes were removed. Provider credentials were absent
  and external provider calls remained zero.

### Acceptance criteria

* A test document can be uploaded.
* An ingestion event is published.
* The worker processes the document.
* Chunks and vectors are created.
* The document reaches the ready state.
* Failures are observable.
* Test runs isolate or clean their data.
* Cross-tenant search is rejected.

### Commit boundary

git add tests/end-to-end
git commit -m "Add end-to-end ingestion tests"

---

## Stage 22.4 — Add End-to-End Chat Tests

### Objective

Test authenticated, tenant-safe retrieval and grounded answer generation.

### Status

Completed on 2026-08-23.

### Delivered implementation

* Extended the isolated `dolved-e2e` profile with deterministic,
  catalogue-bound contextualisation and grounded-generation adapters. The
  complete six-adapter tuple is mandatory, unknown inputs fail closed and the
  preflight proves that OpenAI and Voyage credentials are absent.
* Extended the compact E2E catalogue with an answered question and a genuine
  follow-up insufficiency case. Contextualisation preserves conversation
  history while generation can cite only the evidence handles supplied by
  Laravel.
* Expanded the existing Playwright journey through authenticated chat,
  deterministic retrieval, grounded generation, citation expansion, the real
  document source route, persisted reload and a controlled
  `insufficient_evidence` outcome.
* Added direct API and browser deep-link checks proving that a foreign tenant
  receives concealment for retrieval, conversations and cited documents.
* Added an E2E-only, default-off SSE connection event limit so the browser test
  exercises genuine native reconnection and durable `Last-Event-ID` replay on
  an established conversation. Production streaming behaviour is unchanged.

### Verification evidence

* Clean `make test-e2e`: the single full-path Playwright journey passed in
  39.6 seconds and the isolated containers and volumes were removed.
* The journey visibly asserted `Understanding your question…` from the
  durable `run_progress` event before its E2E-only forced disconnect, then
  proved native reconnection, `Last-Event-ID` replay and terminal completion.
* The journey exercised real Next.js, Laravel, PostgreSQL, LocalStack S3/SQS,
  publisher, Python workers and Qdrant while all provider-facing adapters were
  deterministic and external provider credentials and calls remained absent.
* Focused deterministic chat/profile tests passed (17), and focused Laravel
  SSE replay tests passed (2 tests / 14 assertions).
* The final repository format, lint, type, unit, feature, contract and
  provider-free evaluation gates passed; shell, JSON and diff validation were
  clean.

### Acceptance criteria

* A user can authenticate.
* A tenant document can be indexed.
* A relevant question retrieves expected evidence.
* An answer is generated.
* Citations point to the expected source.
* Another tenant cannot retrieve the document.
* Provider calls can use deterministic test doubles.
* Streaming completion can be tested.

### Commit boundary

git add tests/end-to-end
git commit -m "Add end-to-end RAG chat tests"

---

## Stage 22.5 — Add Security-Focused Tests

### Objective

Automate checks for the platform’s most important security boundaries.

### Status

Completed on 23 August 2026.

### Delivered

* Extended Laravel security regressions for cross-tenant concealment,
  authentication throttling, hostile upload names, unsupported/oversized
  uploads, presigned-upload constraints and privacy-safe storage failures.
* Hardened SQS receive-count parsing so malformed, non-ASCII, non-positive or
  excessively long values remain unacknowledged without crashing polling.
* Added a three-case prompt-injection population and an optional, explicit
  live-measurement wrapper. Provider-free tests prove structural evidence and
  authority boundaries only; they do not claim model resistance or universal
  prompt-injection immunity.
* Bound that wrapper to an exact clean Git worktree, repository-owned policy
  and population, immutable experiment identity, generation fingerprint and
  retry-aware attempt/output ceilings before credentials can be inspected or
  a provider subprocess launched.
* Limited the optional live measurement to three cases, one generation and
  one evaluator attempt per case: six provider attempts and 18,432 maximum
  output tokens in total.
* Added a configurable evaluator output ceiling and generation attempt
  override to the existing evaluation runner so the declared live-policy
  bounds reach the actual adapters.

### Verification

* Focused SQS, live-wrapper, generation-adapter and generation-evaluation
  tests: 53 passed.
* Full Laravel suite: 357 passed, 2 skipped, 1,906 assertions.
* Full Python suite: 629 passed, 4 skipped.
* Full web suite: 115 passed.
* Pint, Ruff lint/format, ESLint, TypeScript and MyPy passed.
* Generation-evidence verification passed for 26 cases and 18 artefacts with
  zero provider calls.
* JSON parsing and `git diff --check` passed. No live provider command was
  invoked.

### Planned coverage

* cross-tenant access;
* insecure direct object references;
* upload validation;
* malicious filenames;
* oversized documents;
* unsupported media types;
* prompt injection in source documents;
* queue payload tampering;
* expired presigned uploads;
* rate limiting;
* authentication and authorisation failures.

### Acceptance criteria

* Cross-tenant tests exist for every tenant-owned resource.
* Upload abuse cases are tested.
* Untrusted document instructions cannot override system behaviour.
* Invalid queue messages fail safely.
* Sensitive errors do not leak internals.
* Security regressions fail CI.

### Commit boundary

git add tests apps
git commit -m "Add security regression tests"

---

## Phase 22 acceptance gate

### Status

Completed on 2026-08-23.

### Verified final state

* The full fast tier passed: web 115 tests; Laravel 357 passed, 2 skipped and
  1,906 assertions; Python 629 passed and 4 skipped; Pint, Ruff lint/format,
  ESLint, TypeScript, Mypy and Collector configuration validation were clean.
* The isolated provider-free Playwright journey passed and removed all of its
  containers and volumes. It now proves the visible progress presentation
  before native SSE reconnection as well as final grounded completion.
* The mandatory real-SPLADE integration check passed with one bounded model
  load and inference.
* All three required provider-free evaluation gates passed:
  `make evaluation-policy-gate`, `make evaluation-retrieval-current` and
  `make evaluation-generation-verify`. Generation verification accounted for
  26 cases and 18 checksum-bound artefacts with zero provider calls.
* ADR-0029 and the ADR index remain current. The deterministic retrieval
  baseline remains an orchestration-regression baseline only and makes no
  live-provider quality claim.
* The optional live retrieval and generation evaluations were not invoked;
  they remain non-gating evidence for controlled future execution.

### Gate outcome

Phase 22 is accepted. The repository is ready to begin R23-S01a without
changing retrieval, generation, threshold, calibration or benchmark policy.

---

# Phase 23 — Document Metadata, Governance and Structured Content

## Phase objective

Implement ADR-0030, ADR-0031 and ADR-0032 in their binding dependency order.
ADR-0030 establishes authoritative family/version metadata. ADR-0031 work that
does not consume extraction identity follows; ADR-0032 then establishes that
identity and its delivery boundary; the remaining clone/deletion work follows.

Every session is provider-free unless its accepted ADR explicitly says
otherwise. No session changes retrieval, generation, calibration or threshold
policy.

## Stage 23.1a — Metadata migration and domain model

Implement ADR-0030 categories, tags, family metadata, owner/source-checksum
state and constraints. Backfill and user-facing work do not begin here.

Acceptance: migration/model constraints and tenant isolation pass; ADR-0030 is
the implementation authority. Commit this bounded foundation before S01b.

## Stage 23.1b — Metadata policies, actions and API resources

Implement authorised metadata mutation and read resources without changing
organisation-owned applicability or version authority.

Acceptance: concealment, authorisation, immutable identities and concurrency
tests pass. Stop before backfill.

## Stage 23.1c — Metadata backfill

Run the streamed, resumable legacy reinterpretation and source-checksum
introduction exactly as ADR-0030 specifies, preserving honestly unknown state.

Acceptance: deterministic counts, resumability and no cross-workspace leakage.

## Stage 23.1d — Metadata acceptance tests

Complete ADR-0030's provider-free tenancy, taxonomy, checksum and mutation
matrix. Record actual evidence and close Stage 23.1 only if clean.

## Stage 23.2a — Version-governance API foundation

Implement only ADR-0031 routes, resources and policies that may precede the
ADR-0032 extraction foundation. Do not implement clone reuse yet.

Completed 2026-08-29: tenant-scoped version history and the approve,
withdraw/cancel, reschedule and owner-only timestamp-correction routes now use
durable per-workspace/per-purpose idempotency, stable typed conflicts and the
family-first deterministic locking order. The applicability-only successor
mutation remains deliberately deferred with clone reuse until ADR-0032 exists;
no incomplete browser route was exposed.

## Stage 23.3a — Structured extraction schema and digests

Implement ADR-0032's canonical artifact schema, identities and cross-language
digest fixtures.

Completed 2026-08-29: the ownership-free `DocumentExtractionArtifact` V1
schema now represents the complete normalised-document field set, while shared
PHP/Python RFC 8785 fixtures bind canonical UUID, Unicode, null/absence,
source-location, table, unknown-element and full-precision number behaviour.
The complete artefact, ordered projection manifest and warning manifest each
have independently verified SHA-256 identities. No upload, persistence,
projection publication or delivery behavior was introduced in this stage.

## Stage 23.3b — Upload authorisation and worker acknowledgement

Implement bounded upload acknowledgement and orphan recovery without moving
source-delivery authority into Python.

Completed 2026-08-29: each extraction-artifact upload is now bound to one
durable workspace/document/ingestion-claim identity, current lease generation,
purpose, contract version, expiry and exact conditional-create object key.
Python constructs and uploads the canonical ADR-0032 artifact to only that
authorised request; Laravel independently streams the stored object to verify
its SHA-256, byte size and contract version before recording one immutable
artifact acknowledgement. Stale generations, incomplete writes and conflicting
acknowledgements fail closed. A scheduled, database-led bounded sweep protects
live leases and published artifacts, deletes exact orphan keys only, persists
bounded retry exhaustion and exposes stuck cleanup through the existing
low-cardinality operational metric. Projection publication and browser source
delivery remain deferred to R23-S03c and R23-S03d respectively.

## Stage 23.3c — Atomic projection publication

Implement generation/build binding and atomic current-projection publication.

Completed 2026-08-29: verified canonical extraction artifacts are now consumed
through an exact-key streaming reader and written into tenant-bound inactive
projection generations in bounded batches. Laravel independently recomputes the
ordered element and warning manifests from persisted rows, verifies counts and
digests, and only then atomically publishes the new generation and switches the
document's active pointer. PostgreSQL supplies generated full-text vectors and
the database enforces scoped generation, artifact and active-pointer lineage.
Failed or incomplete generations remain invisible; bounded scheduled cleanup
removes only inactive stale generations. A retry of an already verified upload
skips object transfer and re-enters acknowledgement so a crash before projection
publication can safely build a fresh inactive generation. Browser source and
extracted-text delivery remain deferred to R23-S03d.

## Stage 23.3d — Source and extracted-text delivery

Implement tenant-authorised Range/HEAD-capable source delivery and the bounded
structured/extracted-text routes.

Completed 2026-08-29: active workspace members can now access an indexed
document's immutable source only through a reauthorised Laravel proxy. The
route implements full GET/HEAD parity, the accepted single byte-range grammar,
typed `416` responses, bounded streaming, deterministic safe content headers,
attachment-only DOCX behavior and tenant concealment before storage metadata is
read. No browser presigned URL is issued. The active published extraction
projection is separately exposed as a bounded cursor-paginated JSON view with
the required label and layout disclaimer, safe selected element/warning/change
fields, and document-scoped PostgreSQL full-text search. Delivery telemetry is
content-free and records only allowlisted public identities, result status,
sanitised range and byte count.

## Stage 23.3e — Structured extraction acceptance evidence

Run ADR-0032's provider-free cross-language, tenancy, digest, recovery and
delivery tests. Clone work remains blocked until this stage is accepted.

Completed 2026-08-29: canonical extraction and projection limits are now
explicitly bound and independently enforced across Python and Laravel,
processing and projection deadlines fail with typed outcomes, document
deletion reconciles source/artifact/projection storage and database lineage,
and the complete GET/HEAD/range matrix is provider-free regression covered.
Measured PDF, DOCX and 100,000-element text fixtures remained within the
accepted 50 MiB artifact, 100,000-element and 300-second processing/projection
envelopes. The evidence is recorded in
`docs/journal/2026-08-29-r23-s03e-structured-extraction-acceptance.md`; no
providers were called. Clone orchestration may now consume the verified
ADR-0032 identity.

## Stage 23.2b — Clone contract and orchestration

Implement ADR-0031 clone compatibility and reuse only now that ADR-0032's
verified extraction identity exists.

Completed 2026-08-29: every ingestion claim now carries an explicit
`ingestion`/`content_clone` origin and a write-once materialisation-pipeline
fingerprint with its inspectable components. Clone eligibility recomputes the
current fingerprint and fails closed on checksum, completed-publication,
active-generation or pipeline drift. The governed applicability-successor
route creates a target-owned claim and clone operation under deterministic
family/version locks and durable command idempotency.

Laravel independently copies and verifies the source object, extraction
artifact, relational projection, warnings, chunks and corpus assignments;
Python alone copies, independently verifies and publishes the exact bounded
Qdrant point mapping. Durable manifests bind the exact object key, checksum,
count, schema, operation, claim and lease generation. The explicit clone state
machine permits ordinary ingestion only after verified removal of all cloned
derived layers, while the final transaction publishes claim evidence and the
target `INDEXED` state atomically. Usage, telemetry and stuck-operation
projections distinguish clone work from ordinary ingestion, and the bounded
manifest sweeper deletes exact recorded keys without bucket scans.

Provider-free verification passed with 423 Laravel tests (3 skipped, 2,257
assertions) and 649 Python tests (4 skipped). Pint, Ruff formatting/lint and
Mypy were clean. Evidence is recorded in
`docs/journal/2026-08-29-r23-s02b-clone-contract-and-orchestration.md`; no
providers were called.

## Stage 23.2c — Family deletion and tombstones

Implement preview/confirm deletion, tombstones and cleanup. At the reserved
`documents.id` export coordination point, implement and test only a no-op
export-hold seam. ADR-0037 will define the real hold schema, expiry and export
contract; this stage must not invent them.

Completed 2026-08-29: family deletion now uses a read-only, tenant-authorised
preview and a short-lived actor/family/state-bound confirmation digest. Confirm
locks the family and versions in deterministic order, rejects stale state and
active clone/deletion conflicts, preserves truthful governance history, freezes
one existing-shape child deletion per version and derives the dedicated parent
status from those children. Completed children remove all seven persisted
content shapes plus exact clone-manifest objects; the family becomes an
immutable tombstone only after every child completes. The `documents.id`
coordination boundary is represented only by a tested, fail-closed interface
with the approved no-op implementation; no ADR-0037 schema or behavior was
introduced.

Provider-free verification passed with 429 Laravel tests (3 skipped, 2,307
assertions) and 649 Python tests (4 skipped). Pint, Ruff formatting/lint and
Mypy were clean. Evidence is recorded in
`docs/journal/2026-08-29-r23-s02c-family-deletion-and-tombstones.md`; no
providers were called.

## Stage 23.2d — Version-governance acceptance tests

Run the full ADR-0031 concealment, idempotency, concurrency, clone, cleanup,
tombstone and no-op export-hold seam matrix.

Completed 2026-08-29: the complete provider-free ADR-0031 matrix now covers
tenant concealment, durable command idempotency, lineage revalidation,
target-owned clone evidence, pipeline and active-generation drift, atomic
six-layer publication, cleanup-before-fallback, origin-separated callbacks and
usage, family preview/confirm staleness, truthful draft handling, tombstone
convergence and the no-op export-hold seam. The acceptance fixture exposed one
real defect: cloned chunks supplied a deterministic public identity, but the
model's mass-assignment boundary discarded it. `public_id` is now admitted at
creation while the existing whole-row immutability guard continues to reject
every later mutation.

Provider-free verification passed with 433 Laravel tests (3 skipped, 2,344
assertions) and 649 Python tests (4 skipped). Pint, Ruff formatting/lint,
ESLint, TypeScript and Mypy were clean. Evidence is recorded in
`docs/journal/2026-08-29-r23-s02d-version-governance-acceptance.md`; no
providers were called.

## Phase 23 gate

The sequence is binding: S01a–d, S02a, S03a–e, then S02b–d. Update the guide
with actual evidence, journal each bounded session and commit each verified
boundary before advancing.

Phase 23 completed 2026-08-29. ADR-0030 metadata and checksum ownership,
ADR-0032 structured extraction and delivery, and ADR-0031 version governance,
clone reuse and deletion/tombstone behavior are implemented and accepted by
their provider-free matrices. Retrieval, planner, generation, calibration,
threshold and benchmark behavior remained unchanged. R24-S01 is the next
session and retains its mandatory visual-review boundary.

---

# Phase 24 — Knowledge Library Product Interface

## Phase objective

Implement ADR-0033 after Phase 23 supplies authoritative metadata, governance,
structured extraction and source delivery.

## Mandatory visual-review boundary

Each user-facing session below must present direct browser URLs or screenshots
covering the ADR's applicable light/dark, desktop/mobile, keyboard/focus,
loading/empty/partial/error/progress states. David's explicit approval is a
session acceptance requirement; tests alone never close the session.

## Stage 24.1 — Contextual navigation shell and route scaffolding

Completed 2026-08-29: the workspace shell now exposes segment-safe,
route-backed Library, Scheduled, Needs attention, Deleted history and
Categories destinations without introducing the reserved import/export
surfaces. Workspace and family scaffolds independently reauthorise their
public identities and fail closed through dedicated loading, error and
not-found states. Desktop/mobile and light/dark browser review confirmed one
contextual active destination, mobile-menu dismissal after navigation, direct
deep-link reload and no horizontal overflow. David approved the visual boundary.
Provider-free verification passed with 29 web test files and 127 tests; ESLint,
TypeScript and `git diff --check` were clean. Evidence is recorded in
`docs/journal/2026-08-29-r24-s01-library-navigation-shell.md`.

## Stage 24.2 — Library table and activity-summary projection

Completed 2026-08-30: the workspace Library now presents one row per
`DocumentFamily` from a dedicated, tenant-authorised API rather than exposing
the legacy per-version document administration feed. The query resolves the
current attained authority state without predecessor resurrection, supports
page-based search, filtering and sorting, and includes historical-only families
only when explicitly requested. A durable family activity-summary projection is
maintained by document creation/versioning, governance, ingestion and extraction
producer paths; the exact rebuild command derives the same state from
authoritative records while preserving projection identity.

The responsive web table renders the real workspace population with loading,
empty, error, pagination and historical controls. The older technical ingestion
surface remains visibly separated under its existing disclosure until later
ADR-0033 destinations replace it. David approved the live browser presentation
and the distinction between family-level Library rows and per-document technical
controls. Provider-free verification passed with the focused Laravel metadata,
library, governance and temporal-authority suites (38 passed, 1 skipped, 171
assertions), 30 web test files / 129 tests, Pint, ESLint, TypeScript and
`git diff --check`. The broad Laravel suite reached 377 passed / 3 skipped; its
remaining failures are the already-known environment fixture, contract-mount and
document-storage configuration gaps, with no R24-S02 focused regression.
Evidence is recorded in
`docs/journal/2026-08-30-r24-s02-library-table.md`.

## Stage 24.3 — Family detail and version history

Completed 2026-08-30: the family detail route now presents authoritative
family metadata, the resolved current authority and the complete version
history from tenant-authorised APIs. Metadata editing and governance actions
are capability-driven from Laravel policy decisions; the interface does not
reimplement authority, applicability or governance rules in the browser.
Version history exposes evidence-processing state, authority intervals and
explicit current/scheduled/historical presentation without predecessor
resurrection.

David reviewed the live detail experience, including an approved document that
resolved as the family's current authority, and approved the visual boundary.
Provider-free verification passed with the focused Laravel metadata and
governance suite (12 tests, 87 assertions), 30 web test files / 129 tests,
Pint, ESLint, TypeScript and `git diff --check`. Evidence is recorded in
`docs/journal/2026-08-30-r24-s03-family-detail.md`.

## Stage 24.4 — Source viewing and extracted text

Completed 2026-08-30: version-specific source links now open an application
route that proxies ADR-0032's tenant-authorised, byte-range-aware source
delivery without constructing or exposing any browser storage URL. Safely
previewable source types render inline; unsupported formats retain a truthful
unavailable-preview state with authorised download and extracted-text options.

The extracted-text route consumes only ADR-0032's published, ordered projection
through its bounded cursor API. It renders structured elements and warnings,
states that the text was extracted for search and may not preserve visual
layout, and never reconstructs content from retrieval chunks or emits raw JSON.
David approved both live routes. Provider-free verification passed with 30 web
test files / 129 tests, ESLint, TypeScript and `git diff --check`. Evidence is
recorded in `docs/journal/2026-08-30-r24-s04-source-viewing.md`.

## Stage 24.5 — Version comparison

Completed 2026-08-30: the tenant-authorised family comparison route now binds
both version identifiers to the route family, rejects identical versions,
resolves the accepted predecessor/successor defaults, and preserves truthful
single-version, deleted-side and unavailable-projection states. The response
uses only ADR-0032's ordered, published extraction projection and remains
bounded to 500 elements per side; it does not reconstruct text from retrieval
chunks.

The production comparison component was exercised through a clearly labelled
development-only representative fixture. David accepted the functional page
provisionally and directed that isolated styling work not interrupt the
remaining implementation sequence:

> Functional checkpoint passed. Current visual treatment is a strong
> provisional foundation. Final comparison-page visual and end-to-end flow
> sign-off is deferred to the consolidated S09 review.

A bounded contract review found that final refinement is not visual-only. The
current equal-ordinal adapter cannot truthfully classify insertion-shifted or
moved elements and has no explicit alignment-reliability state. At the start of
R24-S09, before consolidated visual sign-off, extend this adapter—without a
schema migration—to return deterministic section-aware aligned pairs, moved
classification, change counts and reliable/partial/unavailable alignment
status. Word-level additions/removals may then be derived from already aligned
text pairs in the web layer. ADR-0032 does not retain inline formatting, so
formatting-only detection must remain explicitly unavailable unless a separate
reviewed extraction-contract extension is authorised. The complete retained
handover is recorded in `tasks.json` and
`docs/journal/2026-08-30-r24-s05-version-comparison.md`.

Provider-free verification passed with 9 focused Laravel tests / 68
assertions, 30 web test files / 129 tests, Pint, ESLint, TypeScript and
`git diff --check`.

## Stage 24.6 — Saved views and category settings

Completed on 2026-08-30. Laravel now owns the versioned, user-scoped
`SavedView` domain with canonical bounded definitions, normalized per-user
names, owner-only rename/delete, tenant-safe concealment, membership-end
cleanup and safe mutation audits. Saved definitions reopen against current
Library data and visibly discard unsupported legacy fields rather than
silently reinterpreting them.

The route-backed Categories surface supports administrator-owned create,
rename and archive operations. Archived categories remain visible for truthful
history while staying out of new-assignment controls. David approved the live
Saved views and Categories presentation on 2026-08-30.

Provider-free verification passed with 20 focused Laravel tests / 133
assertions, 33 web test files / 134 tests, Pint, ESLint, TypeScript, JSON and
`git diff --check`. Evidence is recorded in
`docs/journal/2026-08-30-r24-s06-saved-views-category-settings.md`.

## Stage 24.7 — Small-corpus onboarding and searchable-count projection

Completed on 2026-08-30. A dedicated tenant-authorised readiness projection
counts distinct document families that have an approved, indexed and currently
attained authority version. It reuses the Library's extracted CURRENT-authority
query, including no-predecessor-resurrection semantics, rather than maintaining
a second interpretation of searchability. The count endpoint returns only the
count; deterministic starter questions are served separately and derive only
from currently searchable family titles.

The workspace now keeps the exact searchable count visible and explains
truthfully that upload, processing and approval do not individually make a
document searchable. Zero and ready states provide real Library and composer
actions. The progressive disclosure preserves five user-facing stages and the
complete ten-state ADR-0033 onboarding model without presenting internal state
machinery as the primary experience. David approved the live small-corpus
workspace presentation on 2026-08-30.

Provider-free verification passed with 12 focused Laravel tests / 52
assertions, 3 focused web files / 15 tests, Pint, ESLint, TypeScript and
`git diff --check`. Evidence is recorded in
`docs/journal/2026-08-30-r24-s07-small-corpus-onboarding.md`.

## Stage 24.8 — Small-corpus Playwright readiness journey

Reallocated on 2026-08-30; not executed, skipped or passed. ADR-0033's complete
nine-step Playwright journey requires ADR-0034's `ImportBatch`, `ImportItem`,
matching, review and promotion implementation. None of those contracts exists
before Phase 25, and the legacy direct-upload path cannot substitute for them.
There is no independent R24-S08 remainder after Stage 24.7. The unchanged
journey is a mandatory Stage 25.7 and Phase 25 gate requirement.

## Stage 24.9 — Deleted/history presentation

Completed on 2026-08-30. A tenant-scoped owner/administrator endpoint now
presents only completed ADR-0031 family-deletion tombstones with the retained
operation identity, deletion time, requester, version count and governance
audit reference. A missing deletion reason is rendered truthfully rather than
invented. Ordinary members cannot discover the route or navigation item, and
cross-workspace access remains concealed.

The retained Stage 24.5 handover was completed in the same boundary. The
bounded comparison adapter now performs deterministic section-aware alignment,
classifies added, removed, modified and moved extracted elements, counts each
class and reports reliable, partial or unavailable alignment explicitly. The
web layer derives restrained word-level marks only for backend-aligned modified
pairs. It provides document-friendly side-by-side and inline modes, filters,
collapsed unchanged context and change navigation. Formatting-only comparison
remains explicitly unavailable because ADR-0032 does not retain those signals.

David approved the representative production-component fixtures for both
comparison and deleted history on 2026-08-30. This visual checkpoint accepts
the implemented Phase 24 knowledge-library surfaces only; it does not claim
acceptance of Phase 25's future import, staging, matching, review, recovery or
promotion experience. The unchanged import-through-grounded-answer journey
remains mandatory at Stage 25.7. Export navigation remains reserved and absent
until ADR-0037 is accepted.

Provider-free verification passed with 13 focused Laravel tests / 89
assertions, the complete 36-file / 142-test web suite, Pint, ESLint, TypeScript
and `git diff --check`. Evidence is recorded in
`docs/journal/2026-08-30-r24-s09-phase-closure.md`.

---

# Phase 25 — Import Staging and Promotion

## Phase objective

Implement ADR-0034 after the Phase 23 data foundation and Phase 24 library
surface are available.

## Stage 25.1 — Domain, schema and staging-privacy verification

**Completed 2026-08-31.** The ADR-0034 relational foundation is installed:
`ImportBatch`, `ImportItem`, immutable `ImportDecisionSnapshot`,
`PromotionAttempt`, durable per-generation failure rows and the shared
workspace/checksum reservation identity. PostgreSQL now enforces the composite
batch/workspace, current-snapshot/item, promotion-snapshot/item and
same-batch/workspace replacement bindings; self replacement, invalid actor
shapes, duplicate open attempts and duplicate idempotency identities fail
closed. Actor identity is generated by PostgreSQL, failure count is derived by
trigger from durable failure rows, and verified source identity, retained
snapshot lineage and set-once replacement lineage have database and model
guards.

The existing upload bucket was accepted for staging only after adding and
verifying all-four public-access blocks and default AES-256 encryption. Import
staging exposes purpose- and time-bounded operations for one exact
workspace/item key, carries no source filename, grants no browser listing or
prefix authority, rejects cross-workspace/key substitution before storage
access and cleans up only by exact key. The seven-day retention default is
versioned configuration. No preflight dispatch/callback, matching, promotion
execution, legacy cutover or import UI was implemented in this stage.

## Stage 25.2 — Preflight contract and lease reconciliation

**Completed 2026-08-31.** Import preflight now follows ADR-0034's existing
worker topology without introducing a synchronous Laravel-to-Python path.
Laravel atomically creates an immutable, generation-bound
`ImportPreflightAttempt` and `import.preflight.requested` outbox event whose
short-lived read authority is limited to the exact staged object. Python
consumes that event through the existing queue, reports only the bounded
technical result vocabulary and signs its purpose-specific callback with the
existing worker HMAC scheme. No extraction artefact, retrieval data or
provider call is produced.

Laravel remains the authority for zero-byte and configured-size rejection and
for every `ImportItem` transition. Callback incorporation locks the attempt,
requires the exact event, workspace, item, object key, token and generation,
and treats an identical terminal callback as idempotent while conflicting,
expired and stale callbacks fail closed. PostgreSQL protects attempt identity,
terminal immutability, result shape, generation uniqueness and the one-open-
attempt invariant. The scheduled reconciler terminalises an expired lease
before dispatching a successor generation; if successor dispatch is
temporarily unavailable, the terminal attempt remains discoverable for the
next reconciliation pass rather than stranding the item.

The complete Laravel and Python suites, contract/schema validation, PostgreSQL
catalog inspection, Ruff, Mypy, Pint and deterministic recovery tests passed.
R25-S03 now owns only deterministic duplicate/family matching and the shared
checksum-serialization behavior; no matching or promotion behavior was added
here.

## Stage 25.3 — Deterministic matching

**Completed 2026-08-31.** Verified import items now receive deterministic,
provider-free reconciliation based only on their verified checksum, original
source filename and existing family title. Exact checksum matches are scoped
to one workspace and distinguish live technical states from informational
`DELETING`/`DELETED` history without allowing governance state to hide a
duplicate. Applicability-only intent is redirected to ADR-0031's existing
successor action. Possible family matches use the versioned
`family-title-levenshtein-v1` profile, Unicode-normalised filename stems and
titles, a tracked threshold, stable score/public-ID ordering and a hard
five-candidate limit. Unsupported source identity fails closed, and matching
never selects a family or changes the import decision automatically.

The durable workspace/checksum reservation now has one transaction-bound lock
primitive shared with both ADR-0031 clone completion paths. The reservation is
the first lock in each clone final transaction; only after acquiring it does
the clone re-read the verified live source before committing. Real PostgreSQL
tests prove concurrent first creation, reuse, rollback recovery, same-pair
serialization and cross-workspace independence. The complete promotion-versus-
clone concurrency matrix remains an R25-S04/S07 acceptance responsibility
because the promotion consumer does not exist until Stage 25.4; no promotion,
decision-snapshot or user-interface behavior was introduced in this stage.

Verification passed with the complete Laravel suite (485 passed, 3 skipped,
2,606 assertions), the isolated PostgreSQL serialization profile (2 tests,
9 assertions), Pint and `git diff --check`. The complete Python suite was also
re-run unchanged to protect the shared application boundary. Evidence is
recorded in
`docs/journal/2026-08-31-r25-s03-deterministic-import-matching.md`.

## Stage 25.4 — Promotion state machine

**Completed 2026-08-31.** Promotion now consumes an immutable, canonical
decision snapshot and progresses through durable reservation, version-bound
object materialisation, source verification and one atomic Laravel commit.
Commit rechecks the current decision, live actor membership, predecessor and
family validity, metadata/applicability references and same-workspace verified
checksum reservation. Duplicate content, invalidated predecessors, changed
authorization and changed decisions terminalise as typed conflicts rather than
being repaired or silently retried. Document creation remains owned by the
existing ADR-0031 `CreateDocumentVersion` action through its verified-promotion
entry point rather than being reimplemented by the import finalizer.

The committed `Document` records the exact S3 object version that was verified.
Local object storage now enables versioning, document reads request that exact
version, and PostgreSQL prevents later mutation of the stored version identity.
A copied object is reused only after its version, checksum and size are
reverified; terminal uncommitted versions are removable while a committed
document makes the object application-owned. Cancellation, lease reclaim,
failure ceilings, reconciliation and owner/administrator adoption retain the
original attempt and decision lineage.

Provider-free verification passed with the complete Laravel suite (494 passed,
5 skipped, 2,655 assertions), the focused promotion/matching/clone/ingestion
regression set (39 passed, 214 assertions), a clean PostgreSQL promotion run
(9 passed, 49 assertions), the existing PostgreSQL checksum-serialization
profile (2 passed, 9 assertions), PostgreSQL catalog inspection, real LocalStack
version/reuse/pinned-read/cleanup checks, Pint, shell validation and
`git diff --check`. Evidence is recorded in
`docs/journal/2026-08-31-r25-s04-import-promotion.md`.

## Stage 25.5 — Legacy cutover and drain

Completed on 2026-08-31. The legacy browser-upload boundary now has a
PostgreSQL-enforced, forward-only cutover identity and singleton gate. Upload
initialisation serializes on that gate; transition-window and bounded-backfill
rows receive the same server-owned marker and append-only audit identity;
closure atomically handles the final bounded remainder and proves that no
pending legacy upload is unmarked.

The browser-facing completion and ingestion-request actions are now separate
legacy continuations that accept only the exact marked cutover population and
close after the bounded drain. The shared internal ingestion action remains
available to documents committed by the import workflow. The narrowly scoped
drain reconciler may move only marked, stalled `UPLOADING`/`UPLOADED` rows to
`FAILED` with `legacy_upload_drain_expired`; queued and ordinary ingestion
states remain owned by the existing pipeline.

The local cutover ran once under operation
`d09cc97a-68e7-4f4f-aee2-f9a5fc755ccb`: 15 pending rows were marked, zero
eligible rows remained unmarked, 15 per-row audit facts were recorded and one
gate-closure event was persisted. The drain remains open and no row was
expired during this stage. Provider-free verification passed with 502 Laravel
tests / 2,694 assertions, a clean fresh-PostgreSQL migration, six database
serialization/immutability assertions, PostgreSQL catalog inspection, Pint
and `git diff --check`. Evidence is recorded in
`docs/journal/2026-08-31-r25-s05-legacy-upload-cutover.md`.

## Stage 25.6 — Import workflow and progress UI

This stage must present every ADR-0034 visual checkpoint for David's explicit
review before acceptance.

## Stage 25.7 — Provider-free and Playwright acceptance journeys

In addition to ADR-0034's provider-free and Playwright acceptance, execute
ADR-0033's complete, unchanged small-corpus journey through the real
`ImportBatch` flow: create a new workspace, import approximately ten
representative documents, resolve matching/details, promote to `COMMITTED`,
reach real `INDEXED` processing, approve/current-authorise, assert the truthful
searchable-family count, ask a corpus-supported question and receive a grounded
answer with valid evidence/citation behaviour. Upload or indexing alone cannot
pass the journey, and the legacy direct-upload path is not an acceptable
substitute. This journey is mandatory evidence for the Phase 25 gate.

Stages follow ADR-0034's retry, adoption, actor-idempotency, checksum-
serialization and lineage rules. No export/import interchange behavior from
future ADR-0037 is included.

---

# Phase 26 — Frozen Bulk Document Operations

## Phase objective

Implement ADR-0035 after ADR-0034 promotion primitives exist.

## Stage 26.1 — PostgreSQL role and privilege foundation

Provision and verify owner, migrator and `rag_platform_app` roles; isolate
credentials; reconcile existing and default privileges for existing and future
schemas, tables, sequences and functions; prove the complete current
application under the runtime role. This stage must pass before any protected
ADR-0035 or ADR-0036 migration.

## Stage 26.2 — Bulk domain, schema, frozen membership and APIs

## Stage 26.3 — Queue execution, concurrency, cancellation, retry and audit

## Stage 26.4 — Selection, preflight, progress/result UI and Playwright

Stage 26.4 includes every ADR-0035 named visual checkpoint and requires David's
explicit approval before closure. All stages preserve the accepted lock order,
attempt-generation incorporation, database privilege and no-duplicate-mutation
rules.

---

# Phase 27 — Document Governance Notifications and Reminders

## Phase objective

Implement ADR-0036 after ADR-0030–0035 event-producing lifecycles and the
R26-S01 PostgreSQL privilege foundation exist.

## Stage 27.1 — Events, projections, owner controls and provider-free APIs

Implement Session A from ADR-0036, including its exact event vocabulary,
occurrence identities, producer ownership, outbox/projector, protected-column
privileges and owner-change function.

## Stage 27.2 — Inbox and actionable-work projections

## Stage 27.3 — Schedulers, reminders, preferences, envelopes and delivery

## Stage 27.4 — Email templates and tenant-branding seam

## Stage 27.5 — Visual, accessibility and Playwright acceptance

Run the product-surface and email checkpoints in ADR-0036's required order.
Each checkpoint requires a direct browser preview or screenshot and David's
explicit approval before proceeding. Provider deliverability remains governed
by its existing separate smoke-test boundary.

---

# Phase 28 — CI/CD and Production Readiness

## Phase objective

Make the platform reproducibly testable, buildable, deployable and operable outside a developer laptop.

---

## Stage 28.1 — Add Continuous Integration

### Objective

Run quality and test checks automatically for every relevant change.

### Status

Not yet executed.

### Planned CI stages

* repository validation;
* Next.js lint and build;
* Laravel tests;
* Python Ruff;
* Python MyPy;
* Python tests;
* contract tests;
* container builds;
* selected integration tests;
* dependency and security scanning.

### Acceptance criteria

* CI runs from a clean checkout.
* Lockfiles are enforced.
* Failures block merging.
* Secrets are not required for ordinary unit tests.
* External providers use fakes unless explicitly running integration tests.
* Cache use does not hide missing dependencies.
* CI commands match documented local commands.

### Commit boundary

git add .github Makefile
git commit -m "Add continuous integration pipeline"

---

## Stage 28.2 — Create Production Container Builds

### Objective

Create minimal, immutable runtime images distinct from development behaviour.

### Status

Not yet executed.

### Planned principles

* multi-stage builds;
* production-only dependencies;
* non-root runtime users;
* immutable application code;
* no development servers;
* explicit startup commands;
* health checks;
* graceful shutdown;
* image metadata;
* reproducible tags and digests.

### Acceptance criteria

* Production images build independently.
* Development dependencies are excluded where appropriate.
* Runtime processes are non-root.
* Images contain no committed secrets.
* Images start with production commands.
* Health checks work.
* Graceful termination is verified.
* Image sizes and contents are reviewed.

### Commit boundary

git add apps infrastructure
git commit -m "Add production container builds"

---

## Stage 28.3 — Add Infrastructure as Code

### Objective

Define production infrastructure under infrastructure/terraform.

### Status

Not yet executed.

### Planned infrastructure

Exact provider and deployment platform must be confirmed before implementation.

Potential resources include:

* networking;
* compute/container runtime;
* PostgreSQL;
* object storage;
* queues and dead-letter queues;
* secrets;
* logging and metrics;
* DNS and TLS;
* vector database connectivity;
* IAM roles and policies;
* container registry.

### Required ADR

docs/adr/ADR-XXX-production-deployment-platform.md

### Acceptance criteria

* Deployment platform is documented.
* Infrastructure is reproducible.
* Environments are separated.
* Remote state is protected.
* IAM follows least privilege.
* Secrets are not stored in Terraform source.
* Destructive changes are reviewable.
* Cost implications are documented.

### Commit boundary

git add infrastructure/terraform docs/adr
git commit -m "Define production infrastructure"

---

## Stage 28.4 — Configure Secrets and Environment Management

### Objective

Create a secure configuration contract across local, CI, staging and production environments.

### Status

Not yet executed.

### Planned principles

* no secrets committed;
* .env.example documents required variables;
* production secrets come from a managed secret store;
* secrets are scoped by service;
* rotation is possible;
* startup fails clearly when required configuration is missing;
* sensitive values are never logged.

### Acceptance criteria

* Every required variable is documented.
* Development defaults are clearly non-production.
* CI secrets are scoped minimally.
* Production secrets use managed storage.
* Secret rotation procedures exist.
* Configuration validation occurs at startup.
* No secret appears in repository history or image layers.

### Commit boundary

git add .env.example apps docs infrastructure
git commit -m "Harden environment and secret management"

---

## Stage 28.5 — Add Database Backup and Recovery

### Objective

Define and test relational database backup and restoration.

### Status

Not yet executed.

### Acceptance criteria

* Backup frequency is defined.
* Retention is defined.
* Backups are encrypted.
* Restoration is tested.
* Recovery time and recovery point objectives are documented.
* Backup failure is observable.
* Tenant deletion and retention requirements are considered.

### Commit boundary

git add docs infrastructure
git commit -m "Add database backup and recovery plan"

---

## Stage 28.6 — Define Vector Index Recovery

### Objective

Define whether vector data is backed up, recreated or both.

### Status

Not yet executed.

### Planned considerations

* source documents as system of record;
* extracted content persistence;
* chunk persistence;
* embedding model version;
* full re-index duration;
* Qdrant snapshots;
* disaster recovery;
* zero-downtime re-indexing.

### Acceptance criteria

* The vector system of record is explicit.
* Re-indexing is reproducible.
* Model versions are retained.
* Recovery duration is estimated.
* Snapshot or rebuild procedures are tested.
* Active retrieval is protected during re-indexing.

### Commit boundary

git add docs infrastructure apps/ai
git commit -m "Define vector index recovery"

---

## Stage 28.7 — Perform Security Hardening

### Objective

Review and harden the complete platform before public deployment.

### Status

Not yet executed.

### Planned review areas

* authentication;
* authorisation;
* tenant isolation;
* CORS and CSRF;
* cookie security;
* rate limits;
* upload controls;
* malware scanning strategy;
* dependency vulnerabilities;
* image vulnerabilities;
* IAM;
* network exposure;
* SSRF;
* prompt injection;
* sensitive logging;
* secret management;
* data deletion;
* backup access.

### Acceptance criteria

* A threat model exists.
* High-risk boundaries are tested.
* Dependency scans pass or exceptions are documented.
* Container scans pass or exceptions are documented.
* Public network exposure is minimal.
* Least-privilege IAM is verified.
* Tenant isolation has explicit regression tests.
* Prompt-injection controls are documented and tested.
* Security findings have owners and resolution states.

### Commit boundary

git add docs apps infrastructure tests
git commit -m "Harden platform security"

---

## Stage 28.8 — Create Staging Deployment

### Objective

Deploy the complete platform to a production-like staging environment.

### Status

Not yet executed.

### Acceptance criteria

* Staging is provisioned from infrastructure code.
* Production container images are used.
* Managed secrets are used.
* Migrations run safely.
* Upload and ingestion work.
* Retrieval and chat work.
* Observability is available.
* Failure and rollback procedures are exercised.
* End-to-end tests run against staging.

### Commit boundary

To be defined based on the deployment platform.

---

## Stage 28.9 — Production Readiness Review

### Objective

Perform a formal go-live review against technical and operational criteria.

### Status

Not yet executed.

### Required review areas

* functionality;
* security;
* tenancy;
* data protection;
* backups;
* recovery;
* scalability;
* cost;
* observability;
* alerting;
* support;
* runbooks;
* deployment;
* rollback;
* legal and privacy obligations.

### Acceptance criteria

* All critical user journeys pass.
* No unresolved critical security finding remains.
* Backup restoration has been tested.
* Rollback has been tested.
* Alerts and runbooks exist.
* Capacity assumptions are documented.
* Cost assumptions are documented.
* Data retention and deletion are documented.
* Known limitations are visible.
* A go/no-go decision is recorded.

### Commit boundary

git add docs
git commit -m "Complete production readiness review"

---

# Phase 29 — Documentation and Demonstration Readiness

## Phase objective

Document the platform clearly and provide a reproducible way to demonstrate its capabilities, without letting presentation work substitute for engineering substance.

---

## Stage 29.1 — Write Architecture Documentation

### Objective

Explain the system clearly to reviewers, collaborators, clients and future contributors.

### Status

Not yet executed.

### Planned documentation

* system context diagram;
* container/service diagram;
* ingestion sequence diagram;
* retrieval and generation sequence diagram;
* trust boundaries;
* tenancy model;
* deployment architecture;
* key ADR index;
* technology choices and trade-offs.

### Acceptance criteria

* Diagrams match the implemented system.
* Service responsibilities are clear.
* Data flows are clear.
* Security boundaries are visible.
* Trade-offs are discussed honestly.
* Documentation avoids pretending unfinished capabilities exist.

### Commit boundary

git add docs README.md
git commit -m "Document platform architecture"

---

## Stage 29.2 — Create Demonstration Dataset and Scenario

### Objective

Provide a safe, repeatable demonstration of ingestion, retrieval, citations and tenant isolation.

### Status

Not yet executed.

### Acceptance criteria

* Demonstration documents are legally reusable.
* The scenario includes multiple document formats.
* Questions have clear expected sources.
* Citations can be demonstrated.
* Tenant isolation can be demonstrated.
* Failure handling can be demonstrated.
* Setup is reproducible.

### Commit boundary

git add docs scripts tests
git commit -m "Add repeatable platform demonstration"

---

## Stage 29.3 — Finalise Repository README

### Objective

Make the root README an accurate entry point for technical reviewers.

### Status

Not yet executed.

### Planned sections

* project purpose;
* capabilities;
* architecture overview;
* technology stack;
* local setup;
* common commands;
* testing;
* security model;
* design decisions;
* current limitations;
* roadmap;
* screenshots or demonstration;
* licence.

### Acceptance criteria

* Setup works from a clean clone.
* Commands match the Makefile.
* Architecture claims are accurate.
* Current limitations are explicit.
* No secrets or private endpoints appear.
* Screenshots contain no sensitive data.
* The README links to deeper documentation rather than duplicating everything.

### Commit boundary

git add README.md docs
git commit -m "Finalise project documentation"

---

# Future Enhancement Backlog

These are deliberately outside the initial production milestone. They must not distract from completing and proving the baseline platform.

Potential future work:

* hybrid retrieval;
* advanced reranking;
* local embedding models;
* local generation models;
* OCR pipeline;
* spreadsheet extraction;
* image and multimodal retrieval;
* document-version comparison;
* connector ingestion;
* scheduled synchronisation;
* agentic workflows;
* tool calling;
* human feedback and evaluation;
* custom retrieval analytics;
* billing;
* quotas;
* regional data residency;
* enterprise SSO;
* audit exports;
* retention policies;
* model-routing strategies;
* caching;
* asynchronous answer generation;
* additional deployment targets.

Each enhancement must receive its own objective, rationale, tests, acceptance criteria and commit boundary before implementation.

---

# Project Definition of Done

The baseline RAG Platform is complete when:

* A user can register and authenticate.
* A user can create or join a tenant.
* Tenant permissions are enforced.
* A user can upload supported documents.
* Documents are stored safely.
* Upload completion publishes an ingestion event.
* The Python worker processes documents asynchronously.
* Text is extracted and normalised.
* Documents are chunked deterministically.
* Chunks are embedded.
* Vectors and metadata are stored in Qdrant.
* Retrieval is tenant-filtered.
* Answers are grounded in retrieved evidence.
* Citations map to real source locations.
* Chat responses stream to the browser.
* Conversations and citations are persisted.
* Failed ingestion can be inspected and retried.
* Cross-tenant access tests pass.
* Automated quality checks run locally and in CI.
* Production images are non-root and reproducible.
* Infrastructure is defined as code.
* Secrets are managed securely.
* Backups and recovery have been tested.
* Logs, metrics and traces make failures diagnosable.
* Staging deployment passes end-to-end tests.
* Architecture and operational documentation are complete.
* The root README accurately represents the implemented platform.

---

# Change Discipline

When a command fails:

1. Record the observed failure.
2. Explain the underlying cause.
3. Replace the canonical command rather than retaining multiple competing versions.
4. Verify the replacement.
5. Update the acceptance criteria when implementation reveals a missing requirement.
6. Keep this guide as the single procedural source of truth.

When a planned decision changes:

1. Identify whether the change requires an ADR.
2. Update the relevant placeholder before implementation.
3. Remove stale wording from this guide.
4. Update PROJECT_ROADMAP.md if milestone order or scope changes.
5. Ensure commands, documentation and actual behaviour remain aligned.

Do not create a new implementation guide merely because implementation has moved to another phase.
