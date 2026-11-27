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
vi.mock("next/navigation", () => ({ redirect: redirectMock }));

import LoginPage from "@/app/login/page";

describe("LoginPage", () => {
  beforeEach(() => {
    currentUserMock.mockReset();
    redirectMock.mockReset();
    validPendingInvitationMock.mockReset();
    validPendingInvitationMock.mockResolvedValue(true);
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "true");
  });

  it("renders login for a signed-out visitor", async () => {
    currentUserMock.mockResolvedValue(null);

    const page = await LoginPage({});

    expect(page.type.name).toBe("AuthForm");
    expect(page.props).toEqual({ mode: "login", registrationEnabled: true });
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

    expect(page.props).toEqual({ context: "platform", mode: "login", registrationEnabled: true, returnTo: next });
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
    expect(page.props).toEqual({ mode: "login", registrationEnabled: true });
  });

  it("preserves a safe invitation return path", async () => {
    currentUserMock.mockResolvedValue(null);
    const next = "/invitations/invite-token";
    const page = await LoginPage({ searchParams: Promise.resolve({ next }) });
    expect(page.props).toEqual({ mode: "login", registrationEnabled: true, returnTo: next });
  });

  it("does not offer account creation for an invalid invitation when public registration is disabled", async () => {
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "false");
    currentUserMock.mockResolvedValue(null);
    validPendingInvitationMock.mockResolvedValue(false);
    const next = `/invitations/${"a".repeat(64)}`;

    const page = await LoginPage({ searchParams: Promise.resolve({ next }) });

    expect(page.props).toEqual({ mode: "login", registrationEnabled: false, returnTo: next });
  });

  it("returns an unverified signed-in account to verification", async () => {
    currentUserMock.mockResolvedValue({
      id: 18,
      name: "David Oliver",
      email: "david@example.test",
      email_verified_at: null,
    });

    await LoginPage({});

    expect(redirectMock).toHaveBeenCalledWith("/verify-email");
  });

  it("keeps an invitation return path while an account awaits verification", async () => {
    currentUserMock.mockResolvedValue({
      id: 18,
      name: "David Oliver",
      email: "david@example.test",
      email_verified_at: null,
    });

    await LoginPage({ searchParams: Promise.resolve({ next: "/invitations/invite-token" }) });

    expect(redirectMock).toHaveBeenCalledWith("/verify-email?next=%2Finvitations%2Finvite-token");
  });

  it("returns a verified signed-in account to the application", async () => {
    currentUserMock.mockResolvedValue({
      id: 18,
      name: "David Oliver",
      email: "david@example.test",
      email_verified_at: "2026-08-19T12:00:00.000000Z",
    });

    await LoginPage({});

    expect(redirectMock).toHaveBeenCalledWith("/app");
  });

  it("accepts Next's promised empty search parameters without changing login", async () => {
    currentUserMock.mockResolvedValue(null);
    const page = await LoginPage({ searchParams: Promise.resolve({}) });
    expect(page.props).toEqual({ mode: "login", registrationEnabled: true });
    expect(redirectMock).not.toHaveBeenCalled();
  });

  it("hides self-registration links when production registration is disabled", async () => {
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "false");
    currentUserMock.mockResolvedValue(null);

    const page = await LoginPage({});

    expect(page.props).toEqual({ mode: "login", registrationEnabled: false });
  });

  it("offers account setup to an invited recipient when public registration is disabled", async () => {
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "false");
    currentUserMock.mockResolvedValue(null);
    const next = `/invitations/${"a".repeat(64)}`;

    const page = await LoginPage({ searchParams: Promise.resolve({ next }) });

    expect(page.props).toEqual({ mode: "login", registrationEnabled: true, returnTo: next });
    expect(validPendingInvitationMock).toHaveBeenCalledWith("a".repeat(64));
  });
});
