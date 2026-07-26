# RAG Platform Implementation Guide

> **Purpose:** This is the single, durable build guide for the RAG Platform.
>
> Update this file as implementation progresses. Do not create a new implementation
> document for every phase. Architectural decisions that require justification belong
> in `docs/adr/`; the overall milestone view belongs in `PROJECT_ROADMAP.md`.

## Document ownership

- `PROJECT_ROADMAP.md` explains **what** will be built and in what order.
- `IMPLEMENTATION_GUIDE.md` explains **how** to build and verify it.
- `docs/adr/` records important architectural decisions and their trade-offs.
- Application-specific setup belongs in the relevant application README only when it
  becomes necessary for operating that application.

## Working conventions

Each implementation stage should contain:

1. Objective
2. Engineering rationale
3. Commands
4. Expected changes
5. Verification
6. Commit boundary

Commands are run from the repository root unless explicitly stated otherwise.

After each completed phase:

1. run the phase acceptance checks;
2. update this implementation record;
3. commit the completed phase;
4. create an annotated `phase-N` Git tag at that commit.

Phases 2 through 5 were accumulated before this convention was requested. They are
recorded by one consolidated `phase-5` milestone rather than inventing inaccurate
historical commit boundaries. Phase 6 onward receives its own commit and tag.

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

Phase 6 — Authentication and Identity

Phase objective

Establish secure user identity before introducing tenant-owned documents and conversations.

⸻

Stage 6.1 — Define Authentication Architecture

Objective

Choose and document the authentication boundary between Next.js and Laravel.

Status

Not yet executed.

Planned decisions

* Session-cookie versus token-based authentication.
* Laravel Sanctum usage.
* CSRF protection.
* Browser-to-API trust boundary.
* Password-reset and email-verification requirements.
* Local-development origins and cookie settings.
* Whether Next.js acts only as a browser client or also as a backend-for-frontend.

Required ADR

docs/adr/ADR-XXX-authentication-architecture.md

Acceptance criteria

* Authentication flow is documented.
* Browser and API responsibilities are explicit.
* CSRF protection is addressed.
* Cookie and CORS rules are documented.
* Multi-tenancy implications are considered.
* No implementation begins before the decision is recorded.

Commit boundary

git add docs/adr
git commit -m "Document authentication architecture"

⸻

Stage 6.2 — Implement Laravel Authentication

Objective

Implement registration, login, logout and authenticated-user endpoints.

Status

Not yet executed.

Planned capabilities

* register;
* login;
* logout;
* current user;
* email verification if included;
* password reset if included;
* rate limiting;
* authentication feature tests.

Acceptance criteria

* Users can register.
* Users can log in.
* Users can log out.
* Authenticated requests resolve the current user.
* Invalid credentials fail safely.
* Authentication endpoints are rate-limited.
* Passwords are never logged or returned.
* Feature tests cover success and failure paths.

Commit boundary

git add apps/api
git commit -m "Implement API authentication"

⸻

Stage 6.3 — Implement Next.js Authentication UI

Objective

Provide frontend registration, login, logout and protected-route behaviour.

Status

Not yet executed.

Planned capabilities

* registration form;
* login form;
* logout control;
* authenticated application shell;
* route protection;
* loading and error states;
* accessible validation feedback.

Acceptance criteria

* Registration works through the UI.
* Login works through the UI.
* Logout clears authenticated state.
* Protected pages redirect unauthenticated users.
* API errors are presented safely.
* Authentication state survives normal navigation.
* Frontend tests cover critical flows.

Commit boundary

git add apps/web
git commit -m "Add web authentication experience"

⸻

Phase 7 — Multi-Tenancy

Phase objective

Ensure every tenant-owned resource is isolated by design rather than filtered as an afterthought.

⸻

Stage 7.1 — Define Tenant Model

Objective

Choose the platform tenancy model and record its security invariants.

Status

Not yet executed.

Planned decisions

* organisation/workspace terminology;
* user membership model;
* roles and permissions;
* invitation flow;
* tenant selection;
* tenant ownership of documents and conversations;
* row-level isolation strategy;
* background-job tenant context.

Required ADR

docs/adr/ADR-XXX-multi-tenancy-model.md

Acceptance criteria

* Tenant terminology is consistent.
* Ownership rules are documented.
* Membership and role rules are documented.
* Cross-tenant access is explicitly forbidden.
* Background processing carries tenant identity.
* Vector metadata includes tenant identity.
* Audit requirements are considered.

Commit boundary

git add docs/adr
git commit -m "Document multi-tenancy model"

⸻

Stage 7.2 — Implement Tenants and Memberships

Objective

Create tenant, membership and role persistence in Laravel.

Status

Not yet executed.

Planned database entities

tenants
tenant_memberships
tenant_invitations

Exact names may change through the ADR.

Acceptance criteria

* A user can create a tenant.
* Users can belong to multiple tenants if permitted.
* Roles are enforced server-side.
* Tenant selection is explicit.
* Database constraints protect integrity.
* Policies prevent cross-tenant access.
* Feature tests attempt and reject tenant-boundary violations.

Commit boundary

git add apps/api
git commit -m "Implement tenant membership model"

⸻

Stage 7.3 — Add Tenant-Aware Web Experience

Objective

Allow users to create, select and manage tenants through the frontend.

Status

Not yet executed.

Acceptance criteria

* The active tenant is visible.
* Tenant switching is supported where applicable.
* Tenant-specific data refreshes after switching.
* Users cannot select inaccessible tenants.
* Permission-restricted actions are hidden and enforced by the API.
* Tenant state is not trusted solely from the browser.

Commit boundary

git add apps/web
git commit -m "Add tenant-aware web experience"

⸻

Phase 8 — Document Domain and Storage

Phase objective

Model tenant-owned documents and store source files safely before asynchronous ingestion begins.

⸻

Stage 8.1 — Define Document Lifecycle

Objective

Define document states, ownership, metadata and failure behaviour.

Status

Not yet executed.

Planned lifecycle states

Potential states include:

pending_upload
uploaded
queued
processing
ready
failed
deleted

The final state machine must be explicitly defined before implementation.

Required ADR or domain document

docs/adr/ADR-XXX-document-lifecycle.md

Acceptance criteria

* Document states are explicit.
* Valid transitions are documented.
* Tenant ownership is mandatory.
* Original filename and media type are preserved safely.
* Storage keys do not trust user-provided paths.
* Failure and retry states are defined.
* Deletion semantics are defined.

Commit boundary

git add docs/adr
git commit -m "Document document lifecycle"

⸻

Stage 8.2 — Implement Document Persistence

Objective

Create the Laravel document domain model, migrations, policies and API resources.

Status

Not yet executed.

Acceptance criteria

* Document records are tenant-owned.
* State transitions are validated.
* Storage keys are generated safely.
* File metadata is validated.
* Cross-tenant access is rejected.
* API responses do not expose sensitive storage details.
* Feature tests cover policies and lifecycle changes.

Commit boundary

git add apps/api
git commit -m "Implement document domain model"

⸻

Stage 8.3 — Implement Direct Upload Flow

Objective

Allow the browser to upload documents safely to S3-compatible storage.

Status

Not yet executed.

Planned flow

1. Browser requests an upload authorisation from Laravel.
2. Laravel validates tenant, filename, type and size.
3. Laravel creates a pending document record.
4. Laravel returns a presigned upload request.
5. Browser uploads directly to storage.
6. Completion is confirmed before processing begins.

Acceptance criteria

* Uploads are authorised by Laravel.
* Presigned requests are time-limited.
* File type and size rules are enforced.
* Storage keys are tenant-scoped.
* The browser does not receive permanent AWS credentials.
* Interrupted uploads do not become ready documents.
* Upload completion is verified.
* LocalStack and real S3-compatible configuration share the same application flow.

Commit boundary

git add apps/api apps/web contracts
git commit -m "Implement direct document uploads"

⸻

Phase 9 — Event-Driven Ingestion

Phase objective

Decouple document uploads from processing by publishing durable ingestion jobs.

⸻

Stage 9.1 — Define Ingestion Event Contract

Objective

Create a versioned event contract for document-ingestion requests.

Status

Not yet executed.

Planned location

contracts/events/document-ingestion-requested/

Planned fields

* event identifier;
* event version;
* occurred-at timestamp;
* tenant identifier;
* document identifier;
* storage bucket;
* storage object key;
* media type;
* correlation identifier.

Acceptance criteria

* The schema is versioned.
* Required fields are documented.
* Tenant identity is included.
* Consumers can reject unsupported versions.
* Example payloads exist.
* Laravel and Python validate against the same contract.
* No secrets or presigned URLs are included.

Commit boundary

git add contracts/events
git commit -m "Define document ingestion event contract"

⸻

Stage 9.2 — Publish Ingestion Jobs

Objective

Publish an ingestion event after a document upload is confirmed.

Status

Not yet executed.

Acceptance criteria

* Laravel publishes a valid event.
* Publishing is tenant-aware.
* Events include correlation identifiers.
* Duplicate completion requests do not create uncontrolled duplicate work.
* Queue failures are handled.
* Publishing behaviour is covered by tests.
* LocalStack SQS is supported.

Commit boundary

git add apps/api
git commit -m "Publish document ingestion jobs"

⸻

Stage 9.3 — Consume Ingestion Jobs

Objective

Create a Python worker that receives, validates and acknowledges ingestion events.

Status

Not yet executed.

Planned engineering decisions

* Worker process separate from the HTTP process.
* Explicit visibility timeout.
* Controlled retry count.
* Dead-letter handling.
* Idempotency based on event/document identifiers.
* Structured logging with correlation context.
* Messages acknowledged only after durable success.

Acceptance criteria

* The worker receives SQS messages.
* Event schemas are validated.
* Unsupported versions fail safely.
* Successful messages are acknowledged.
* Failed messages are retried.
* Repeated failures reach the dead-letter queue.
* Duplicate messages do not duplicate final data.
* Worker tests cover acknowledgement and failure behaviour.

Commit boundary

git add apps/ai contracts
git commit -m "Add document ingestion worker"

⸻

Phase 10 — Text Extraction and Normalisation

Phase objective

Convert supported source documents into a consistent internal representation with traceable source metadata.

⸻

Stage 10.1 — Define Extracted Document Contract

Objective

Define the internal representation produced by document extractors.

Status

Not yet executed.

Planned fields

* document identifier;
* tenant identifier;
* source media type;
* extracted text;
* page or section boundaries;
* table metadata where available;
* source offsets;
* warnings;
* extractor name and version.

Acceptance criteria

* The representation is typed.
* Source locations can later support citations.
* Extraction warnings are retained.
* Empty and malformed documents are represented explicitly.
* Tenant context is preserved.
* Extractor version information is captured.

Commit boundary

git add apps/ai contracts
git commit -m "Define extracted document representation"

⸻

Stage 10.2 — Implement Plain Text Extraction

Objective

Support ingestion of UTF-8 plain-text documents.

Status

Not yet executed.

Acceptance criteria

* Valid text files are extracted.
* Encoding failures are handled clearly.
* Empty files are handled.
* Excessively large files are bounded.
* Newline normalisation is deterministic.
* Tests use representative fixtures.

Commit boundary

git add apps/ai tests
git commit -m "Add plain text extraction"

⸻

Stage 10.3 — Implement PDF Extraction

Objective

Extract text and source-location metadata from PDFs.

Status

Not yet executed.

Planned considerations

* text-based PDFs;
* scanned PDFs;
* page boundaries;
* headers and footers;
* multi-column layouts;
* tables;
* malformed or encrypted PDFs;
* OCR as a separate and explicit capability.

Acceptance criteria

* Text-based PDFs are supported.
* Page numbers are preserved.
* Unsupported encrypted files fail clearly.
* Empty extraction is detected.
* Table behaviour is documented.
* Representative fixtures are tested.
* OCR is not silently performed unless intentionally implemented.

Commit boundary

git add apps/ai tests
git commit -m "Add PDF text extraction"

⸻

Stage 10.4 — Implement DOCX Extraction

Objective

Extract paragraphs, headings and table content from DOCX documents.

Status

Not yet executed.

Acceptance criteria

* Paragraph text is extracted in order.
* Heading information is retained.
* Table content is represented deliberately.
* Corrupt files fail clearly.
* Source structure can support later citations.
* Representative fixtures are tested.

Commit boundary

git add apps/ai tests
git commit -m "Add DOCX text extraction"

⸻

Stage 10.5 — Normalise Extracted Content

Objective

Convert extractor-specific output into one deterministic normalised representation.

Status

Not yet executed.

Planned behaviour

* Unicode normalisation;
* whitespace normalisation;
* repeated-header/footer handling;
* section-boundary preservation;
* page-boundary preservation;
* deterministic output;
* warning retention.

Acceptance criteria

* Equivalent input produces deterministic output.
* Source-location mappings remain valid.
* Structural boundaries are not discarded unnecessarily.
* Normalisation is tested independently.
* Raw extraction and normalised content can be distinguished.

Commit boundary

git add apps/ai tests
git commit -m "Normalise extracted document content"

⸻

Phase 11 — Chunking

Phase objective

Split normalised documents into retrieval units while preserving enough context and source metadata for accurate answers and citations.

⸻

Stage 11.1 — Define Chunk Contract

Objective

Define the fields and invariants of a document chunk.

Status

Not yet executed.

Planned fields

* chunk identifier;
* document identifier;
* tenant identifier;
* chunk ordinal;
* text;
* token count;
* source page or section;
* source offsets;
* heading context;
* chunking strategy and version;
* metadata used for retrieval filters.

Acceptance criteria

* Chunk identifiers are stable or reproducibly generated.
* Tenant and document identity are mandatory.
* Source metadata supports citations.
* Chunking strategy version is recorded.
* Token counts are available.
* The contract supports re-chunking.

Commit boundary

git add apps/ai contracts
git commit -m "Define document chunk contract"

⸻

Stage 11.2 — Implement Baseline Chunker

Objective

Create a deterministic baseline chunking strategy.

Status

Not yet executed.

Planned considerations

* token-based limits;
* overlap;
* paragraph boundaries;
* headings;
* minimum useful chunk size;
* excessive single paragraphs;
* tables and lists;
* deterministic ordering.

Acceptance criteria

* Chunk size is bounded.
* Overlap is deterministic.
* Text is not silently lost.
* Chunk ordering is stable.
* Source metadata is preserved.
* Edge cases are tested.
* Configuration is explicit rather than hard-coded throughout the codebase.

Commit boundary

git add apps/ai tests
git commit -m "Implement baseline document chunking"

⸻

Stage 11.3 — Evaluate Chunking Quality

Objective

Create an evaluation corpus and measure whether chunks preserve useful retrieval context.

Status

Not yet executed.

Planned evaluation material

* prose-heavy PDF;
* multi-section DOCX;
* plain text;
* tables;
* repeated headings;
* short document;
* long document;
* awkward formatting.

Acceptance criteria

* Evaluation fixtures are committed where licensing permits.
* Expected boundaries are documented.
* Chunk-size distributions can be inspected.
* Text-loss checks exist.
* Source-location integrity is tested.
* Known limitations are recorded.

Commit boundary

git add tests docs
git commit -m "Add chunking evaluation corpus"

⸻

Phase 12 — Embeddings

Phase objective

Generate reproducible vector representations while keeping model providers replaceable.

⸻

Stage 12.1 — Define Embedding Provider Boundary

Objective

Introduce a provider-neutral interface for embedding text.

Status

Not yet executed.

Planned decisions

* hosted versus local model support;
* model identifier configuration;
* embedding dimensions;
* batching;
* retry and timeout policy;
* rate limiting;
* cost instrumentation;
* test doubles;
* model-version tracking.

Required ADR

docs/adr/ADR-XXX-embedding-provider.md

Acceptance criteria

* Application code does not depend directly on one vendor SDK everywhere.
* Model and dimensions are explicit.
* Timeouts and retries are bounded.
* Provider failures are typed.
* Tests can run without paid external calls.
* Model changes trigger controlled re-embedding.

Commit boundary

git add docs/adr apps/ai
git commit -m "Define embedding provider boundary"

⸻

Stage 12.2 — Implement Embedding Generation

Objective

Generate embeddings for chunks in controlled batches.

Status

Not yet executed.

Acceptance criteria

* Chunk text is embedded in batches.
* Empty content is rejected.
* Dimensions are validated.
* Provider errors are retried only when appropriate.
* Correlation and document identifiers are logged.
* Secrets are loaded through environment configuration.
* Tests use a deterministic fake provider.
* Real-provider integration is tested separately.

Commit boundary

git add apps/ai tests
git commit -m "Implement chunk embedding generation"

⸻

Phase 13 — Vector Storage

Phase objective

Persist tenant-isolated chunk vectors and metadata in a dedicated vector database.

⸻

Stage 13.1 — Define Vector Database Architecture

Objective

Confirm Qdrant as the vector store and document collection, tenancy and filtering strategy.

Status

Not yet executed.

Planned decisions

* collection per environment versus per tenant;
* tenant filtering;
* vector dimensions and distance metric;
* payload schema;
* point identifiers;
* re-index strategy;
* deletion behaviour;
* backup considerations;
* local and managed deployment compatibility.

Required ADR

docs/adr/ADR-XXX-vector-storage.md

Acceptance criteria

* Tenant isolation strategy is explicit.
* Payload schema is documented.
* Distance metric is justified.
* Model/dimension changes are handled.
* Document deletion behaviour is defined.
* Re-indexing is possible without corrupting active data.

Commit boundary

git add docs/adr
git commit -m "Document vector storage architecture"

⸻

Stage 13.2 — Add Qdrant Development Service

Objective

Add Qdrant to Docker Compose with persistent local storage and health checks.

Status

Not yet executed.

Planned service name

qdrant

Acceptance criteria

* Qdrant starts through Compose.
* The image version is pinned.
* Health checks pass.
* Data persists through container recreation.
* The AI service connects using Compose DNS.
* No public exposure is enabled unnecessarily.
* Reset behaviour is documented.

Commit boundary

git add compose.yaml .env.example
git commit -m "Add Qdrant development service"

⸻

Stage 13.3 — Persist Chunk Vectors

Objective

Store embedded chunks and retrieval metadata in Qdrant.

Status

Not yet executed.

Acceptance criteria

* Collections are created idempotently.
* Vector dimensions are validated.
* Point IDs are deterministic or safely generated.
* Tenant identifiers are mandatory payload fields.
* Document identifiers support deletion and re-indexing.
* Batch upserts are supported.
* Partial failures are handled.
* Tests verify tenant filtering.

Commit boundary

git add apps/ai tests
git commit -m "Persist document vectors in Qdrant"

⸻

Stage 13.4 — Complete Ingestion Pipeline

Objective

Connect upload, queue consumption, extraction, normalisation, chunking, embedding and vector persistence.

Status

Not yet executed.

Acceptance criteria

* An uploaded document reaches the ready state.
* Each stage is observable.
* Failures update document status.
* Retries are idempotent.
* Duplicate messages do not duplicate vectors.
* Document and tenant context survives every stage.
* Dead-letter behaviour is verified.
* End-to-end ingestion tests exist.

Commit boundary

git add apps/api apps/ai tests contracts
git commit -m "Complete document ingestion pipeline"

⸻

Phase 14 — Retrieval

Phase objective

Retrieve relevant, tenant-safe source chunks for a user query.

⸻

Stage 14.1 — Define Retrieval Contract

Objective

Define the input, output and diagnostics of the retrieval subsystem.

Status

Not yet executed.

Planned input

* tenant identifier;
* query text;
* optional document filters;
* optional metadata filters;
* result limit;
* retrieval configuration.

Planned output

* ranked chunks;
* similarity score;
* document metadata;
* source location;
* retrieval diagnostics;
* strategy version.

Acceptance criteria

* Tenant context is mandatory.
* Retrieval results include citation metadata.
* Scores and ranking are inspectable.
* Filters are typed.
* Empty-query behaviour is defined.
* No generation concerns leak into the retrieval contract.

Commit boundary

git add apps/ai contracts
git commit -m "Define retrieval contract"

⸻

Stage 14.2 — Implement Semantic Retrieval

Objective

Embed a user query and retrieve tenant-filtered chunks from Qdrant.

Status

Not yet executed.

Acceptance criteria

* Query embeddings use the compatible model.
* Every search includes tenant filtering.
* Optional document filters work.
* Result limits are bounded.
* No cross-tenant chunks are returned.
* Empty results are represented normally.
* Tests cover ranking and isolation.

Commit boundary

git add apps/ai tests
git commit -m "Implement semantic document retrieval"

⸻

Stage 14.3 — Add Retrieval Evaluation

Objective

Measure retrieval quality against a curated question-and-source dataset.

Status

Not yet executed.

Planned metrics

* hit rate;
* recall at K;
* mean reciprocal rank where useful;
* tenant-isolation correctness;
* latency;
* no-result behaviour.

Acceptance criteria

* Evaluation questions have expected source chunks or documents.
* Metrics are reproducible.
* Baseline results are recorded.
* Retrieval changes can be compared.
* Failures are inspectable rather than represented only by one aggregate score.

Commit boundary

git add tests docs scripts
git commit -m "Add retrieval evaluation suite"

⸻

Stage 14.4 — Introduce Retrieval Enhancements

Objective

Evaluate enhancements only after the semantic baseline is measured.

Status

Not yet executed.

Candidate enhancements

* hybrid lexical/vector retrieval;
* reranking;
* query rewriting;
* metadata-aware retrieval;
* contextual compression;
* diversity selection;
* parent-child retrieval.

Do not implement all candidates automatically. Each enhancement must justify its complexity through evaluation.

Acceptance criteria

* A baseline exists before enhancement.
* The enhancement solves an observed weakness.
* Quality and latency effects are measured.
* Tenant filtering remains mandatory.
* The simpler approach remains preferred when results are equivalent.

Commit boundary

To be defined for the selected enhancement.

⸻

Phase 15 — Grounded Generation

Phase objective

Generate answers that are constrained by retrieved evidence and accompanied by verifiable citations.

⸻

Stage 15.1 — Define Generation Provider Boundary

Objective

Create a provider-neutral interface for chat or completion models.

Status

Not yet executed.

Planned decisions

* supported provider;
* model configuration;
* structured versus text output;
* streaming;
* timeout and retry policy;
* usage accounting;
* safety controls;
* test doubles;
* prompt versioning.

Required ADR

docs/adr/ADR-XXX-generation-provider.md

Acceptance criteria

* Provider SDK use is isolated.
* Model identifiers are configurable.
* Requests have bounded timeouts.
* Usage metadata can be captured.
* Tests do not require paid API calls.
* Prompt versions are traceable.

Commit boundary

git add docs/adr apps/ai
git commit -m "Define generation provider boundary"

⸻

Stage 15.2 — Build Grounded Prompt Assembly

Objective

Construct prompts from the user query, tenant-safe retrieved chunks and explicit grounding instructions.

Status

Not yet executed.

Planned principles

* source material is clearly delimited;
* instructions state that sources may contain untrusted text;
* retrieved text cannot override system instructions;
* the model must distinguish evidence from inference;
* insufficient evidence should produce an honest limitation;
* source identifiers are stable enough for citation mapping;
* token budgets are bounded.

Acceptance criteria

* Prompt assembly is deterministic.
* Retrieved sources are clearly delimited.
* Prompt injection from documents is treated as untrusted content.
* Context-size limits are enforced.
* Source identifiers survive generation.
* Prompt templates are versioned and tested.

Commit boundary

git add apps/ai tests
git commit -m "Build grounded prompt assembly"

⸻

Stage 15.3 — Generate Answers with Citations

Objective

Produce answers that cite retrieved source locations.

Status

Not yet executed.

Acceptance criteria

* Answers cite source identifiers.
* Citations map to real retrieved chunks.
* Unsupported citations are rejected or flagged.
* Insufficient evidence is handled honestly.
* The answer cannot cite another tenant’s material.
* Provider failures are represented clearly.
* Tests cover grounded and ungrounded cases.

Commit boundary

git add apps/ai tests
git commit -m "Generate grounded answers with citations"

⸻

Stage 15.4 — Add Answer Evaluation

Objective

Evaluate groundedness, citation correctness and answer usefulness.

Status

Not yet executed.

Planned measures

* citation precision;
* citation recall where measurable;
* groundedness;
* answer relevance;
* abstention quality;
* latency;
* token usage;
* cost.

Acceptance criteria

* Evaluation examples are versioned.
* Citation mappings can be checked automatically.
* Model-graded metrics are not treated as unquestionable truth.
* Human-review fields are supported.
* Prompt/model changes can be compared.
* Regression thresholds are documented.

Commit boundary

git add tests docs scripts
git commit -m "Add grounded answer evaluation"

⸻

Phase 16 — Conversation and Streaming

Phase objective

Expose the RAG workflow as a persistent, streaming conversational experience.

⸻

Stage 16.1 — Define Conversation Domain

Objective

Model conversations, messages, citations and generation metadata.

Status

Not yet executed.

Planned entities

conversations
messages
message_citations
generation_runs

Exact names may change.

Acceptance criteria

* Conversations are tenant-owned.
* Messages record user and assistant roles.
* Citations are persisted.
* Model and prompt versions are traceable.
* Usage metadata can be recorded.
* Conversation deletion semantics are defined.
* Cross-tenant access is prohibited.

Commit boundary

git add apps/api docs/adr
git commit -m "Define conversation domain"

⸻

Stage 16.2 — Implement Chat Orchestration API

Objective

Coordinate retrieval and generation through a stable Laravel-facing API.

Status

Not yet executed.

Planned flow

1. Laravel authorises the user and tenant.
2. Laravel persists the user message.
3. Laravel invokes the AI service.
4. AI retrieves tenant-filtered context.
5. AI generates a grounded answer.
6. Laravel persists the answer and citations.
7. The result is returned or streamed to the browser.

Acceptance criteria

* Laravel remains the identity and authorisation boundary.
* AI requests carry trusted tenant context.
* User messages are persisted once.
* Failures do not create inconsistent conversation history.
* Correlation identifiers span services.
* Timeouts and cancellation are handled.
* Integration tests cover the complete request.

Commit boundary

git add apps/api apps/ai contracts
git commit -m "Implement chat orchestration API"

⸻

Stage 16.3 — Implement Streaming Responses

Objective

Stream generated answer tokens or events to the browser.

Status

Not yet executed.

Planned decisions

* Server-Sent Events versus another streaming transport.
* Event schema.
* Completion and error events.
* Cancellation behaviour.
* Proxy buffering.
* Persisting partial versus final responses.
* Reconnection behaviour.

Acceptance criteria

* Streaming transport is documented.
* The browser receives incremental output.
* Completion is represented explicitly.
* Errors are represented explicitly.
* Cancellation releases upstream work where possible.
* Partial messages do not become silently complete.
* Citations are delivered consistently.
* Streaming tests exist.

Commit boundary

git add apps/web apps/api apps/ai contracts
git commit -m "Add streaming chat responses"

⸻

Stage 16.4 — Build Chat Interface

Objective

Create the tenant-aware conversational UI.

Status

Not yet executed.

Planned capabilities

* conversation list;
* new conversation;
* message composer;
* streaming answer display;
* source citations;
* loading, empty and error states;
* document filters;
* accessible keyboard behaviour;
* retry or regenerate where appropriate.

Acceptance criteria

* Users can create conversations.
* Users can send messages.
* Responses stream visibly.
* Citations are inspectable.
* Conversations are tenant-scoped.
* Errors do not destroy prior messages.
* The interface is keyboard accessible.
* Critical interactions are tested.

Commit boundary

git add apps/web
git commit -m "Build streaming RAG chat interface"

⸻

Phase 17 — Administration

Phase objective

Provide operational visibility and safe tenant-level controls.

⸻

Stage 17.1 — Build Document Administration

Objective

Allow authorised users to inspect, retry and delete documents.

Status

Not yet executed.

Planned capabilities

* document list;
* processing status;
* extraction warnings;
* failure reason;
* retry ingestion;
* delete document;
* filter and search;
* inspect source metadata.

Acceptance criteria

* Permissions are enforced server-side.
* Status reflects the real ingestion state.
* Retry is idempotent.
* Delete removes or schedules removal of derived vectors.
* Failures are understandable without exposing secrets.
* Cross-tenant administration is impossible.

Commit boundary

git add apps/api apps/web
git commit -m "Add document administration"

⸻

Stage 17.2 — Build Tenant and Membership Administration

Objective

Allow authorised tenant administrators to manage members and roles.

Status

Not yet executed.

Acceptance criteria

* Members can be listed.
* Invitations can be issued and revoked where supported.
* Roles can be changed by authorised users.
* A tenant cannot accidentally lose every administrator unless deliberately supported.
* Permission changes take effect consistently.
* Audit events are recorded for sensitive changes.

Commit boundary

git add apps/api apps/web
git commit -m "Add tenant membership administration"

⸻

Stage 17.3 — Add Usage Visibility

Objective

Expose document, storage, ingestion and model-usage information.

Status

Not yet executed.

Planned metrics

* document count;
* storage usage;
* chunks indexed;
* ingestion failures;
* queries;
* embedding usage;
* generation token usage;
* estimated provider cost where available.

Acceptance criteria

* Usage is tenant-scoped.
* Metrics have defined units and time ranges.
* Provider-reported usage is distinguished from estimates.
* Users cannot infer another tenant’s activity.
* Expensive aggregation is controlled.
* Data freshness is visible.

Commit boundary

git add apps/api apps/web
git commit -m "Add tenant usage visibility"

⸻

Phase 18 — Observability and Operations

Phase objective

Make failures, latency and cross-service behaviour diagnosable.

⸻

Stage 18.1 — Standardise Structured Logging

Objective

Emit machine-readable, correlated logs from every service.

Status

Not yet executed.

Planned common fields

* timestamp;
* level;
* service;
* environment;
* request identifier;
* correlation identifier;
* tenant identifier where safe;
* user identifier where safe;
* document identifier;
* event name;
* duration;
* error type.

Acceptance criteria

* Logs are structured consistently.
* Secrets and source-document contents are not logged.
* Request and correlation identifiers cross service boundaries.
* Background jobs carry correlation context.
* Errors include useful stack or exception context.
* Logging configuration differs appropriately by environment.

Commit boundary

git add apps/web apps/api apps/ai docs
git commit -m "Standardise structured platform logging"

⸻

Stage 18.2 — Add Metrics

Objective

Measure platform health, latency, throughput and failures.

Status

Not yet executed.

Planned metrics

* HTTP request count and latency;
* queue depth and age;
* ingestion duration;
* extraction failures;
* chunks per document;
* embedding latency;
* retrieval latency;
* generation latency;
* model token usage;
* streaming completion and cancellation;
* database and vector-store failures.

Acceptance criteria

* Metrics have stable names.
* Labels avoid unbounded cardinality.
* Tenant identifiers are not used carelessly as metric labels.
* Key pipeline stages are timed.
* Error counts can be separated by class.
* Local metric inspection is possible.

Commit boundary

git add apps infrastructure docs
git commit -m "Add platform metrics"

⸻

Stage 18.3 — Add Distributed Tracing

Objective

Trace requests across Next.js, Laravel, Python, queues and external providers.

Status

Not yet executed.

Planned technology

OpenTelemetry or another explicitly documented standard.

Acceptance criteria

* Trace context propagates over HTTP.
* Trace context propagates through queue messages.
* External provider calls create spans.
* Sensitive prompt and document content is not captured by default.
* Sampling strategy is configurable.
* Local traces can be inspected.

Commit boundary

git add apps infrastructure docs
git commit -m "Add distributed tracing"

⸻

Stage 18.4 — Define Operational Alerts

Objective

Document actionable alert conditions and runbooks.

Status

Not yet executed.

Planned alerts

* API error-rate increase;
* queue backlog;
* dead-letter messages;
* ingestion failure spike;
* database unavailable;
* vector store unavailable;
* provider failure or rate limiting;
* latency threshold breach;
* storage capacity concern.

Acceptance criteria

* Every alert has an owner or response expectation.
* Alerts describe impact rather than only internal symptoms.
* Thresholds are justified.
* Runbooks exist.
* Alerts avoid obvious noise.
* Local or test alert verification is possible where practical.

Commit boundary

git add docs infrastructure
git commit -m "Document operational alerts and runbooks"

⸻

Phase 19 — Testing and Quality Strategy

Phase objective

Create a layered test strategy that catches regressions without requiring every check to be an expensive end-to-end test.

⸻

Stage 19.1 — Establish Test Taxonomy

Objective

Document which behaviours belong in unit, integration, contract, feature and end-to-end tests.

Status

Not yet executed.

Planned categories

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

Acceptance criteria

* Test categories have clear purposes.
* External providers can be faked.
* Critical tenant boundaries have automated tests.
* Expensive tests are separated from fast feedback.
* Test data strategy is documented.
* CI stages can map to the taxonomy.

Commit boundary

git add docs
git commit -m "Document platform testing strategy"

⸻

Stage 19.2 — Add Contract Tests

Objective

Verify that Laravel and Python agree on shared HTTP and event contracts.

Status

Not yet executed.

Acceptance criteria

* Contracts are versioned.
* Producers validate emitted payloads.
* Consumers validate received payloads.
* Breaking changes fail tests.
* Example payloads remain valid.
* Unsupported versions are tested.
* Contract generation or duplication is controlled.

Commit boundary

git add contracts apps/api apps/ai tests
git commit -m "Add shared contract tests"

⸻

Stage 19.3 — Add End-to-End Ingestion Tests

Objective

Test the complete document path from upload to searchable vectors.

Status

Not yet executed.

Planned location

tests/end-to-end/

Acceptance criteria

* A test document can be uploaded.
* An ingestion event is published.
* The worker processes the document.
* Chunks and vectors are created.
* The document reaches the ready state.
* Failures are observable.
* Test runs isolate or clean their data.
* Cross-tenant search is rejected.

Commit boundary

git add tests/end-to-end
git commit -m "Add end-to-end ingestion tests"

⸻

Stage 19.4 — Add End-to-End Chat Tests

Objective

Test authenticated, tenant-safe retrieval and grounded answer generation.

Status

Not yet executed.

Acceptance criteria

* A user can authenticate.
* A tenant document can be indexed.
* A relevant question retrieves expected evidence.
* An answer is generated.
* Citations point to the expected source.
* Another tenant cannot retrieve the document.
* Provider calls can use deterministic test doubles.
* Streaming completion can be tested.

Commit boundary

git add tests/end-to-end
git commit -m "Add end-to-end RAG chat tests"

⸻

Stage 19.5 — Add Security-Focused Tests

Objective

Automate checks for the platform’s most important security boundaries.

Status

Not yet executed.

Planned coverage

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

Acceptance criteria

* Cross-tenant tests exist for every tenant-owned resource.
* Upload abuse cases are tested.
* Untrusted document instructions cannot override system behaviour.
* Invalid queue messages fail safely.
* Sensitive errors do not leak internals.
* Security regressions fail CI.

Commit boundary

git add tests apps
git commit -m "Add security regression tests"

⸻

Phase 20 — CI/CD and Production Readiness

Phase objective

Make the platform reproducibly testable, buildable, deployable and operable outside a developer laptop.

⸻

Stage 20.1 — Add Continuous Integration

Objective

Run quality and test checks automatically for every relevant change.

Status

Not yet executed.

Planned CI stages

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

Acceptance criteria

* CI runs from a clean checkout.
* Lockfiles are enforced.
* Failures block merging.
* Secrets are not required for ordinary unit tests.
* External providers use fakes unless explicitly running integration tests.
* Cache use does not hide missing dependencies.
* CI commands match documented local commands.

Commit boundary

git add .github Makefile
git commit -m "Add continuous integration pipeline"

⸻

Stage 20.2 — Create Production Container Builds

Objective

Create minimal, immutable runtime images distinct from development behaviour.

Status

Not yet executed.

Planned principles

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

Acceptance criteria

* Production images build independently.
* Development dependencies are excluded where appropriate.
* Runtime processes are non-root.
* Images contain no committed secrets.
* Images start with production commands.
* Health checks work.
* Graceful termination is verified.
* Image sizes and contents are reviewed.

Commit boundary

git add apps infrastructure
git commit -m "Add production container builds"

⸻

Stage 20.3 — Add Infrastructure as Code

Objective

Define production infrastructure under infrastructure/terraform.

Status

Not yet executed.

Planned infrastructure

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

Required ADR

docs/adr/ADR-XXX-production-deployment-platform.md

Acceptance criteria

* Deployment platform is documented.
* Infrastructure is reproducible.
* Environments are separated.
* Remote state is protected.
* IAM follows least privilege.
* Secrets are not stored in Terraform source.
* Destructive changes are reviewable.
* Cost implications are documented.

Commit boundary

git add infrastructure/terraform docs/adr
git commit -m "Define production infrastructure"

⸻

Stage 20.4 — Configure Secrets and Environment Management

Objective

Create a secure configuration contract across local, CI, staging and production environments.

Status

Not yet executed.

Planned principles

* no secrets committed;
* .env.example documents required variables;
* production secrets come from a managed secret store;
* secrets are scoped by service;
* rotation is possible;
* startup fails clearly when required configuration is missing;
* sensitive values are never logged.

Acceptance criteria

* Every required variable is documented.
* Development defaults are clearly non-production.
* CI secrets are scoped minimally.
* Production secrets use managed storage.
* Secret rotation procedures exist.
* Configuration validation occurs at startup.
* No secret appears in repository history or image layers.

Commit boundary

git add .env.example apps docs infrastructure
git commit -m "Harden environment and secret management"

⸻

Stage 20.5 — Add Database Backup and Recovery

Objective

Define and test relational database backup and restoration.

Status

Not yet executed.

Acceptance criteria

* Backup frequency is defined.
* Retention is defined.
* Backups are encrypted.
* Restoration is tested.
* Recovery time and recovery point objectives are documented.
* Backup failure is observable.
* Tenant deletion and retention requirements are considered.

Commit boundary

git add docs infrastructure
git commit -m "Add database backup and recovery plan"

⸻

Stage 20.6 — Define Vector Index Recovery

Objective

Define whether vector data is backed up, recreated or both.

Status

Not yet executed.

Planned considerations

* source documents as system of record;
* extracted content persistence;
* chunk persistence;
* embedding model version;
* full re-index duration;
* Qdrant snapshots;
* disaster recovery;
* zero-downtime re-indexing.

Acceptance criteria

* The vector system of record is explicit.
* Re-indexing is reproducible.
* Model versions are retained.
* Recovery duration is estimated.
* Snapshot or rebuild procedures are tested.
* Active retrieval is protected during re-indexing.

Commit boundary

git add docs infrastructure apps/ai
git commit -m "Define vector index recovery"

⸻

Stage 20.7 — Perform Security Hardening

Objective

Review and harden the complete platform before public deployment.

Status

Not yet executed.

Planned review areas

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

Acceptance criteria

* A threat model exists.
* High-risk boundaries are tested.
* Dependency scans pass or exceptions are documented.
* Container scans pass or exceptions are documented.
* Public network exposure is minimal.
* Least-privilege IAM is verified.
* Tenant isolation has explicit regression tests.
* Prompt-injection controls are documented and tested.
* Security findings have owners and resolution states.

Commit boundary

git add docs apps infrastructure tests
git commit -m "Harden platform security"

⸻

Stage 20.8 — Create Staging Deployment

Objective

Deploy the complete platform to a production-like staging environment.

Status

Not yet executed.

Acceptance criteria

* Staging is provisioned from infrastructure code.
* Production container images are used.
* Managed secrets are used.
* Migrations run safely.
* Upload and ingestion work.
* Retrieval and chat work.
* Observability is available.
* Failure and rollback procedures are exercised.
* End-to-end tests run against staging.

Commit boundary

To be defined based on the deployment platform.

⸻

Stage 20.9 — Production Readiness Review

Objective

Perform a formal go-live review against technical and operational criteria.

Status

Not yet executed.

Required review areas

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

Acceptance criteria

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

Commit boundary

git add docs
git commit -m "Complete production readiness review"

⸻

Phase 21 — Portfolio and Demonstration Readiness

Phase objective

Present the platform as a credible 2027 engineering portfolio project without allowing presentation work to replace engineering substance.

⸻

Stage 21.1 — Write Architecture Documentation

Objective

Explain the system clearly to reviewers, recruiters, clients and future contributors.

Status

Not yet executed.

Planned documentation

* system context diagram;
* container/service diagram;
* ingestion sequence diagram;
* retrieval and generation sequence diagram;
* trust boundaries;
* tenancy model;
* deployment architecture;
* key ADR index;
* technology choices and trade-offs.

Acceptance criteria

* Diagrams match the implemented system.
* Service responsibilities are clear.
* Data flows are clear.
* Security boundaries are visible.
* Trade-offs are discussed honestly.
* Documentation avoids pretending unfinished capabilities exist.

Commit boundary

git add docs README.md
git commit -m "Document platform architecture"

⸻

Stage 21.2 — Create Demonstration Dataset and Scenario

Objective

Provide a safe, repeatable demonstration of ingestion, retrieval, citations and tenant isolation.

Status

Not yet executed.

Acceptance criteria

* Demonstration documents are legally reusable.
* The scenario includes multiple document formats.
* Questions have clear expected sources.
* Citations can be demonstrated.
* Tenant isolation can be demonstrated.
* Failure handling can be demonstrated.
* Setup is reproducible.

Commit boundary

git add docs scripts tests
git commit -m "Add repeatable platform demonstration"

⸻

Stage 21.3 — Finalise Repository README

Objective

Make the root README an accurate entry point for technical reviewers.

Status

Not yet executed.

Planned sections

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

Acceptance criteria

* Setup works from a clean clone.
* Commands match the Makefile.
* Architecture claims are accurate.
* Current limitations are explicit.
* No secrets or private endpoints appear.
* Screenshots contain no sensitive data.
* The README links to deeper documentation rather than duplicating everything.

Commit boundary

git add README.md docs
git commit -m "Finalise project documentation"

⸻

Future Enhancement Backlog

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

⸻

Project Definition of Done

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

⸻

Change Discipline

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
