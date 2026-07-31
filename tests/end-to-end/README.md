# End-to-End Tests

This directory contains end-to-end tests that verify behaviour across the
entire platform.

These tests exercise complete user journeys rather than individual
applications.

Stage 12.5 introduced the first live cross-service acceptance workflow. It is
kept in `scripts/telemetry/verify-cross-service.sh` because it verifies the
running Compose telemetry topology as well as application behaviour. Run it
through `make telemetry-verify`.

Browser-driven product journeys will be added here when a later stage defines
their test runner and fixtures.
