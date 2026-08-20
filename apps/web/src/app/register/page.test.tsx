import { beforeEach, describe, expect, it, vi } from "vitest";

const { currentUserMock, redirectMock } = vi.hoisted(() => ({
  currentUserMock: vi.fn(),
  redirectMock: vi.fn(),
}));

vi.mock("@/lib/server-api", () => ({
  currentUser: currentUserMock,
}));

vi.mock("next/navigation", () => ({
  redirect: redirectMock,
}));

import RegisterPage from "@/app/register/page";

describe("RegisterPage", () => {
  beforeEach(() => {
    currentUserMock.mockReset();
    redirectMock.mockReset();
  });

  it("renders registration for a signed-out visitor", async () => {
    currentUserMock.mockResolvedValue(null);

    const page = await RegisterPage();

    expect(page.type.name).toBe("AuthForm");
    expect(page.props).toEqual({ mode: "register" });
    expect(redirectMock).not.toHaveBeenCalled();
  });

  it("returns an unverified signed-in account to verification", async () => {
    currentUserMock.mockResolvedValue({
      id: 18,
      name: "David Oliver",
      email: "david@example.test",
      email_verified_at: null,
    });

    await RegisterPage();

    expect(redirectMock).toHaveBeenCalledWith("/verify-email");
  });

  it("returns a verified signed-in account to the application", async () => {
    currentUserMock.mockResolvedValue({
      id: 18,
      name: "David Oliver",
      email: "david@example.test",
      email_verified_at: "2026-08-19T12:00:00.000000Z",
    });

    await RegisterPage();

    expect(redirectMock).toHaveBeenCalledWith("/app");
  });
});
