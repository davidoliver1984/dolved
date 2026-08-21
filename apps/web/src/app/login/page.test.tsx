import { beforeEach, describe, expect, it, vi } from "vitest";

const { currentUserMock, redirectMock } = vi.hoisted(() => ({
  currentUserMock: vi.fn(),
  redirectMock: vi.fn(),
}));

vi.mock("@/lib/server-api", () => ({ currentUser: currentUserMock }));
vi.mock("next/navigation", () => ({ redirect: redirectMock }));

import LoginPage from "@/app/login/page";

describe("LoginPage", () => {
  beforeEach(() => {
    currentUserMock.mockReset();
    redirectMock.mockReset();
  });

  it("renders login for a signed-out visitor", async () => {
    currentUserMock.mockResolvedValue(null);

    const page = await LoginPage();

    expect(page.type.name).toBe("AuthForm");
    expect(page.props).toEqual({ mode: "login" });
    expect(redirectMock).not.toHaveBeenCalled();
  });

  it.each([
    "/app/platform/operations",
    "/app/platform/operations/alerts",
    "/app/platform/operations/telemetry",
    "/app/platform/operations/policy",
  ] as const)("renders contextual platform copy for allowlisted return path %s", async (next) => {
    currentUserMock.mockResolvedValue(null);

    const page = await LoginPage({ searchParams: Promise.resolve({ next }) });

    expect(page.props).toEqual({ context: "platform", mode: "login", returnTo: next });
  });

  it.each([
    "/app/platform/operations/anything-else",
    "https://evil.example/app/platform/operations",
    "//evil.example/app/platform/operations",
    "/app/platform/operations?section=alerts",
    "/app/platform/operations#alerts",
  ])("rejects unsafe or unlisted return value %s", async (next) => {
    currentUserMock.mockResolvedValue(null);
    const page = await LoginPage({ searchParams: Promise.resolve({ next }) });
    expect(page.props).toEqual({ mode: "login" });
  });

  it("returns an unverified signed-in account to verification", async () => {
    currentUserMock.mockResolvedValue({
      id: 18,
      name: "David Oliver",
      email: "david@example.test",
      email_verified_at: null,
    });

    await LoginPage();

    expect(redirectMock).toHaveBeenCalledWith("/verify-email");
  });

  it("returns a verified signed-in account to the application", async () => {
    currentUserMock.mockResolvedValue({
      id: 18,
      name: "David Oliver",
      email: "david@example.test",
      email_verified_at: "2026-08-19T12:00:00.000000Z",
    });

    await LoginPage();

    expect(redirectMock).toHaveBeenCalledWith("/app");
  });
});
