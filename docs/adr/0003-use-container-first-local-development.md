# ADR 0003: Use Container-First Local Development

## Status

Accepted (retrospective)

## Date

2026-07-26

## Original decision context

Phase 2 — Docker Development Environment

## Context

The repository contains Node.js, PHP and Python applications plus supporting
infrastructure. Requiring every developer to install compatible host versions of all
runtimes and package managers would make onboarding and troubleshooting dependent on
host-specific state.

During Phases 1 and 2, scaffolding, dependency installation, quality checks and
application execution were deliberately performed through disposable or project
containers. Each application now has its own independently buildable development
image.

This ADR is retrospective: it records the development contract already established by
the Phase 2 implementation.

## Decision

Use a container-first local development workflow.

Docker and Docker Compose provide the canonical application runtimes and supporting
services. Routine repository commands execute inside containers; developers are not
required to install Node.js, npm, PHP, Composer, Python or uv on the host.

The development environment will:

* maintain one independently buildable image for each application;
* pin foundational runtime and package-manager versions where reproducibility
  requires it;
* mount source code for rapid local iteration;
* keep container-managed dependencies in named volumes;
* run application processes as non-root users where the base image permits;
* expose stable repository commands through the root Makefile;
* keep production-specific process and infrastructure decisions separate from the
  local-development images.

Dockerfiles remain independently usable. Docker Compose orchestrates them but is not
embedded into their application-level configuration.

## Alternatives considered

### Host-native runtimes

Developers could install and manage Node.js, PHP, Composer, Python, uv and PostgreSQL
directly. This can provide faster filesystem performance and easier native debugging,
but increases setup variance and makes runtime compatibility the responsibility of
each workstation.

### A single development container

All language runtimes could be installed in one large development image. This would
provide one shell but would couple unrelated toolchains, enlarge rebuilds and stop the
applications from proving that they can build independently.

### IDE-specific development containers

A Dev Container could define the primary environment. This can provide a strong
editor-integrated experience, but would make the canonical workflow dependent on a
particular editor protocol. It may be added later as an optional interface over the
same Dockerfiles.

### Hybrid host-and-container workflow

Applications could run on the host while only infrastructure runs in containers.
This is a common workflow, but retains host runtime drift and creates two networking
models that documentation and support must cover.

## Consequences

### Positive

* A compatible Docker installation is the primary host prerequisite.
* Runtime and dependency behaviour is reproducible across developer machines and CI.
* Each service proves its own build boundary.
* Local infrastructure uses the same network model for every developer.
* Onboarding and recovery can be automated behind stable Make targets.

### Negative

* Docker consumes additional disk, memory and startup time.
* Bind-mounted filesystem performance may be slower on macOS and Windows.
* Debugging and editor integration sometimes require container-aware configuration.
* File ownership must be handled deliberately at bind-mount and named-volume
  boundaries.
* Containerised development improves parity but does not make development images
  identical to production deployments.
