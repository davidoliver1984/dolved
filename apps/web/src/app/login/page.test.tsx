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

  it("renders contextual platform copy only for the allowlisted platform return path", async () => {
    currentUserMock.mockResolvedValue(null);

    const page = await LoginPage({ searchParams: Promise.resolve({ next: "/app/platform/operations" }) });

    expect(page.props).toEqual({ context: "platform", mode: "login", returnTo: "/app/platform/operations" });
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
