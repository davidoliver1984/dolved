import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const { notFoundMock, platformOperationsMock, redirectMock } = vi.hoisted(() => ({ notFoundMock: vi.fn(), platformOperationsMock: vi.fn(), redirectMock: vi.fn() }));
vi.mock("@/lib/server-api", () => ({ platformOperations: platformOperationsMock }));
vi.mock("next/navigation", () => ({ notFound: notFoundMock, redirect: redirectMock, useRouter: () => ({ refresh: vi.fn() }) }));

import PlatformAlertsPage from "@/app/app/platform/operations/alerts/page";
import PlatformPolicyPage from "@/app/app/platform/operations/policy/page";
import PlatformTelemetryPage from "@/app/app/platform/operations/telemetry/page";

const snapshot = {
  status: "available" as const, health_status: "healthy" as const, as_of: "2026-08-20T12:00:00Z", freshness: "current" as const,
  grafana_url: "http://127.0.0.1:3001", alertmanager_url: "http://127.0.0.1:9093",
  metrics: { queue_depth: { status: "available" as const, values: [{ labels: {}, value: 3 }] } }, slos: [],
  alerts: { status: "available" as const, values: [{ name: "QueueDepth", severity: "warning" as const, subsystem: "queue", state: "active", started_at: "2026-08-20T11:00:00Z", impact: "Queued work is delayed.", runbook_url: "https://example.test/runbook" }] },
  operational_policy: null,
};

describe("platform operations specialist routes", () => {
  beforeEach(() => { platformOperationsMock.mockReset(); redirectMock.mockReset().mockImplementation(() => { throw new Error("redirect"); }); notFoundMock.mockReset().mockImplementation(() => { throw new Error("not-found"); }); });
  afterEach(cleanup);

  it("keeps complete alert detail on the alerts route", async () => {
    platformOperationsMock.mockResolvedValue({ status: "ok", data: snapshot });
    render(await PlatformAlertsPage());
    expect(screen.getByRole("heading", { name: "Active alerts" })).toBeTruthy();
    expect(screen.getByText("QueueDepth")).toBeTruthy();
    expect(screen.getByRole("link", { name: "Open alert console" }).getAttribute("href")).toBe("http://127.0.0.1:9093");
  });

  it("keeps the metric grid and specialist-console jump on telemetry", async () => {
    platformOperationsMock.mockResolvedValue({ status: "ok", data: snapshot });
    render(await PlatformTelemetryPage());
    expect(screen.getByRole("heading", { name: "Operational metrics" })).toBeTruthy();
    expect(screen.getByText("Queue and outbox depth")).toBeTruthy();
    expect(screen.getByRole("link", { name: "Open specialist console" }).getAttribute("href")).toBe("http://127.0.0.1:3001");
  });

  it("keeps desired policy ownership on the policy route", async () => {
    platformOperationsMock.mockResolvedValue({ status: "ok", data: snapshot });
    render(await PlatformPolicyPage());
    expect(screen.getByRole("heading", { name: "Operational policy" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "Save desired policy" })).toBeTruthy();
  });

  it.each([
    ["alerts", PlatformAlertsPage, "/login?next=/app/platform/operations/alerts"],
    ["telemetry", PlatformTelemetryPage, "/login?next=/app/platform/operations/telemetry"],
    ["policy", PlatformPolicyPage, "/login?next=/app/platform/operations/policy"],
  ] as const)("preserves the exact %s route through authentication", async (_name, page, target) => {
    platformOperationsMock.mockResolvedValue({ status: "unauthorized" });
    await expect(page()).rejects.toThrow("redirect");
    expect(redirectMock).toHaveBeenCalledWith(target);
  });

  it.each([PlatformAlertsPage, PlatformTelemetryPage, PlatformPolicyPage])("maps concealed specialist access to route-owned not-found", async (page) => {
    platformOperationsMock.mockResolvedValue({ status: "concealed" });
    await expect(page()).rejects.toThrow("not-found");
    expect(notFoundMock).toHaveBeenCalledOnce();
  });
});
