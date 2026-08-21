import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const { notFoundMock, platformOperationsMock, redirectMock } = vi.hoisted(() => ({
  notFoundMock: vi.fn(),
  platformOperationsMock: vi.fn(),
  redirectMock: vi.fn(),
}));

vi.mock("@/lib/server-api", () => ({ platformOperations: platformOperationsMock }));
vi.mock("next/navigation", () => ({ notFound: notFoundMock, redirect: redirectMock }));

import PlatformOperationsPage from "@/app/app/platform/operations/page";

const snapshot = {
  status: "available" as const,
  health_status: "degraded" as const,
  as_of: "2026-08-20T12:00:00Z",
  freshness: "current" as const,
  grafana_url: "http://127.0.0.1:3001",
  alertmanager_url: "http://127.0.0.1:9093",
  metrics: { api_error_rate: { status: "unavailable" as const, values: [] } },
  slos: [{ id: "conversation_technical_success", objective: 0.99, window_days: 28, status: "available" as const, value: 0.98, compliant: false }],
  alerts: { status: "available" as const, values: [{ name: "TechnicalSuccessBreach", severity: "urgent" as const, subsystem: "conversation", state: "active", started_at: "2026-08-20T11:00:00Z", impact: "Terminal outcomes are degraded.", runbook_url: "https://example.test/runbook" }] },
  operational_policy: { public_id: "policy", environment: "local", version: 2, manifest_version: "v1", manifest_digest: "a".repeat(64), active_settings: 1, total_settings: 5, fully_active: false, settings: [] },
};

describe("PlatformOperationsPage", () => {
  beforeEach(() => {
    platformOperationsMock.mockReset();
    redirectMock.mockReset().mockImplementation(() => { throw new Error("redirect"); });
    notFoundMock.mockReset().mockImplementation(() => { throw new Error("not-found"); });
  });
  afterEach(cleanup);

  it("renders a concise overview that links to each detailed route", async () => {
    platformOperationsMock.mockResolvedValue({ status: "ok", data: snapshot });
    render(await PlatformOperationsPage());

    expect(screen.getByRole("heading", { name: "Platform health is degraded." })).toBeTruthy();
    expect(screen.getByText("98.00%")).toBeTruthy();
    expect(screen.getByText("1 active")).toBeTruthy();
    expect(screen.getByText("1 of 5 active")).toBeTruthy();
    expect(screen.getByRole("link", { name: "View active alerts" }).getAttribute("href")).toBe("/app/platform/operations/alerts");
    expect(screen.getByRole("link", { name: "View global telemetry" }).getAttribute("href")).toBe("/app/platform/operations/telemetry");
    expect(screen.getByRole("link", { name: "View operational policy" }).getAttribute("href")).toBe("/app/platform/operations/policy");
    expect(screen.queryByText("TechnicalSuccessBreach")).toBeNull();
  });

  it("degrades an API failure without exposing specialist data", async () => {
    platformOperationsMock.mockResolvedValue({ status: "unavailable" });
    render(await PlatformOperationsPage());
    expect(screen.getByRole("heading", { name: "Platform health is unavailable." })).toBeTruthy();
    expect(screen.getByText(/Ordinary Dolved use is unaffected/)).toBeTruthy();
  });

  it("maps a concealed API response to the route-owned not-found boundary", async () => {
    platformOperationsMock.mockResolvedValue({ status: "concealed" });
    await expect(PlatformOperationsPage()).rejects.toThrow("not-found");
    expect(notFoundMock).toHaveBeenCalledOnce();
  });

  it("preserves the exact overview route through authentication", async () => {
    platformOperationsMock.mockResolvedValue({ status: "unauthorized" });
    await expect(PlatformOperationsPage()).rejects.toThrow("redirect");
    expect(redirectMock).toHaveBeenCalledWith("/login?next=/app/platform/operations");
  });
});
