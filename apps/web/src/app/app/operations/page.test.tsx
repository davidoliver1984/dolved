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
});
