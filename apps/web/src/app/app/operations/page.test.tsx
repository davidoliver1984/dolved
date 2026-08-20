import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { platformOperationsMock, redirectMock } = vi.hoisted(() => ({
  platformOperationsMock: vi.fn(),
  redirectMock: vi.fn(),
}));

vi.mock("@/lib/server-api", () => ({ platformOperations: platformOperationsMock }));
vi.mock("next/navigation", () => ({ redirect: redirectMock }));

import PlatformOperationsPage from "@/app/app/operations/page";

describe("PlatformOperationsPage", () => {
  beforeEach(() => {
    platformOperationsMock.mockReset();
    redirectMock.mockReset();
  });

  it("renders explicit unavailable values without fabricating zero", async () => {
    platformOperationsMock.mockResolvedValue({
      status: "ok",
      data: {
        status: "partial",
        health_status: "unknown",
        as_of: "2026-08-20T12:00:00Z",
        freshness: "current",
        grafana_url: "http://127.0.0.1:3001",
        metrics: {
          api_error_rate: { status: "unavailable", values: [] },
          queue_depth: { status: "available", values: [] },
        },
      },
    });

    render(await PlatformOperationsPage());

    expect(screen.getByRole("heading", { name: "Platform health is unknown." })).toBeTruthy();
    expect(screen.getByText("Unavailable")).toBeTruthy();
    expect(screen.getByText("No observations")).toBeTruthy();
    expect(screen.queryByText("0")).toBeNull();
  });

  it("degrades an API failure without exposing specialist data", async () => {
    platformOperationsMock.mockResolvedValue({ status: "unavailable" });

    render(await PlatformOperationsPage());

    expect(screen.getByRole("heading", { name: "Health data is unavailable." })).toBeTruthy();
    expect(screen.getByText(/Ordinary Dolved use is unaffected/)).toBeTruthy();
  });

  it("renders curated SLO and alert state with specialist links", async () => {
    platformOperationsMock.mockResolvedValue({
      status: "ok",
      data: {
        status: "available",
        health_status: "degraded",
        as_of: "2026-08-20T12:00:00Z",
        freshness: "current",
        grafana_url: "http://127.0.0.1:3001",
        alertmanager_url: "http://127.0.0.1:9093",
        metrics: {},
        slos: [{
          id: "conversation_technical_success",
          objective: 0.99,
          window_days: 28,
          status: "available",
          value: 0.98,
          compliant: false,
        }],
        alerts: {
          status: "available",
          values: [{
            name: "DolvedConversationTechnicalSuccessSloBreach",
            severity: "urgent",
            subsystem: "conversation",
            state: "active",
            started_at: "2026-08-20T11:00:00Z",
            impact: "Users may not receive a valid terminal outcome.",
            runbook_url: "https://example.test/runbook",
          }],
        },
      },
    });

    render(await PlatformOperationsPage());

    expect(screen.getByText("98.00%")).toBeTruthy();
    expect(screen.getByText(/outside objective/)).toBeTruthy();
    expect(screen.getByText("DolvedConversationTechnicalSuccessSloBreach")).toBeTruthy();
    expect(screen.getByRole("link", { name: "Open alert console" }).getAttribute("href")).toBe("http://127.0.0.1:9093");
  });
});
