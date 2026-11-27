import { beforeEach, describe, expect, it, vi } from "vitest";

const { currentUserMock, redirectMock, validPendingInvitationMock } = vi.hoisted(() => ({
  currentUserMock: vi.fn(),
  redirectMock: vi.fn(),
  validPendingInvitationMock: vi.fn(),
}));

vi.mock("@/lib/server-api", () => ({
  currentUser: currentUserMock,
  validPendingInvitation: validPendingInvitationMock,
}));

vi.mock("next/navigation", () => ({
  redirect: redirectMock,
}));

import RegisterPage from "@/app/register/page";

describe("RegisterPage", () => {
  beforeEach(() => {
    currentUserMock.mockReset();
    redirectMock.mockReset();
    validPendingInvitationMock.mockReset();
    validPendingInvitationMock.mockResolvedValue(true);
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "true");
  });

  it("renders registration for a signed-out visitor", async () => {
    currentUserMock.mockResolvedValue(null);

    const page = await RegisterPage();

    expect(page.type.name).toBe("AuthForm");
    expect(page.props).toEqual({ mode: "register" });
    expect(redirectMock).not.toHaveBeenCalled();
  });

  it("preserves a safe invitation return path", async () => {
    currentUserMock.mockResolvedValue(null);
    const next = "/invitations/invite-token";
    const page = await RegisterPage({ searchParams: Promise.resolve({ next }) });
    expect(page.props).toEqual({ mode: "register", returnTo: next });
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

  it("keeps an invitation return path while a new account awaits verification", async () => {
    currentUserMock.mockResolvedValue({
      id: 18,
      name: "David Oliver",
      email: "david@example.test",
      email_verified_at: null,
    });

    await RegisterPage({ searchParams: Promise.resolve({ next: "/invitations/invite-token" }) });

    expect(redirectMock).toHaveBeenCalledWith("/verify-email?next=%2Finvitations%2Finvite-token");
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

  it("redirects a signed-out visitor when production registration is disabled", async () => {
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "false");
    currentUserMock.mockResolvedValue(null);

    await RegisterPage();

    expect(redirectMock).toHaveBeenCalledWith("/login");
  });

  it("allows invitation-authorised account setup when public registration is disabled", async () => {
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "false");
    currentUserMock.mockResolvedValue(null);
    const next = `/invitations/${"a".repeat(64)}`;

    const page = await RegisterPage({ searchParams: Promise.resolve({ next }) });

    expect(page.props).toEqual({ mode: "register", returnTo: next });
    expect(redirectMock).not.toHaveBeenCalled();
    expect(validPendingInvitationMock).toHaveBeenCalledWith("a".repeat(64));
  });

  it("does not let an invalid invitation bypass closed public registration", async () => {
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "false");
    currentUserMock.mockResolvedValue(null);
    validPendingInvitationMock.mockResolvedValue(false);
    const next = `/invitations/${"a".repeat(64)}`;

    await RegisterPage({ searchParams: Promise.resolve({ next }) });

    expect(redirectMock).toHaveBeenCalledWith(`/login?next=${encodeURIComponent(next)}`);
  });
});
