import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const { listConversationsMock, pathnameState, routerPushMock } = vi.hoisted(() => ({
  listConversationsMock: vi.fn(),
  pathnameState: { value: "/app/workspaces/workspace-1" },
  routerPushMock: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  usePathname: () => pathnameState.value,
  useRouter: () => ({ push: routerPushMock, refresh: vi.fn() }),
}));

vi.mock("@/lib/conversations", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/conversations")>()),
  listConversations: listConversationsMock,
}));

import { AppShell } from "@/components/AppShell";

const user = {
  id: 1,
  name: "David Oliver",
  email: "david@example.test",
  email_verified_at: "2026-08-21T00:00:00Z",
};

describe("AppShell", () => {
  beforeEach(() => {
    pathnameState.value = "/app/workspaces/workspace-1";
    listConversationsMock.mockReset();
    listConversationsMock.mockResolvedValue([]);
    routerPushMock.mockReset();
  });

  afterEach(cleanup);

  it("provides route-backed workspace navigation and recent conversations", async () => {
    listConversationsMock.mockResolvedValue([
      { id: "conversation-1", title: "Medication safety", status: "ACTIVE", created_at: "", updated_at: "" },
    ]);
    render(
      <AppShell
        canOperatePlatform={false}
        user={user}
        workspaces={[{ public_id: "workspace-1", name: "Alderbridge", slug: "alderbridge", role: "owner" }]}
      >
        <p>Workspace content</p>
      </AppShell>,
    );

    const newConversation = screen.getByRole("link", { name: "New conversation" });
    expect(newConversation.getAttribute("href")).toBe("/app/workspaces/workspace-1");
    expect(newConversation.className).toContain("text-sidebar-primary-foreground");
    expect(newConversation.className).toContain("[&_span]:text-sidebar-primary-foreground");
    expect(screen.getByRole("link", { name: "Documents" }).getAttribute("href")).toBe("/app/workspaces/workspace-1/documents");
    expect(screen.getByRole("link", { name: "Administration" }).getAttribute("href")).toBe("/app/workspaces/workspace-1/administration");
    await waitFor(() => expect(screen.getByRole("link", { name: "Medication safety" }).getAttribute("href")).toBe("/app/workspaces/workspace-1/conversations/conversation-1"));
  });

  it("does not render authority-gated destinations for an ordinary member", () => {
    render(
      <AppShell
        canOperatePlatform={false}
        user={user}
        workspaces={[{ public_id: "workspace-1", name: "Alderbridge", slug: "alderbridge", role: "member" }]}
      >
        <p>Workspace content</p>
      </AppShell>,
    );

    expect(screen.queryByRole("link", { name: "Administration" })).toBeNull();
    expect(screen.queryByRole("link", { name: "Platform operations" })).toBeNull();
    pathnameState.value = "/app/workspaces/workspace-1/documents";
    cleanup();
    render(
      <AppShell
        canOperatePlatform={false}
        user={user}
        workspaces={[{ public_id: "workspace-1", name: "Alderbridge", slug: "alderbridge", role: "member" }]}
      >
        <p>Workspace content</p>
      </AppShell>,
    );
    expect(screen.queryByRole("link", { name: "Deleted history" })).toBeNull();
  });

  it("marks route-backed administration navigation active", () => {
    pathnameState.value = "/app/workspaces/workspace-1/administration/people";
    render(
      <AppShell
        canOperatePlatform
        user={user}
        workspaces={[{ public_id: "workspace-1", name: "Alderbridge", slug: "alderbridge", role: "admin" }]}
      >
        <p>Workspace content</p>
      </AppShell>,
    );

    expect(screen.getByRole("link", { name: "Administration" }).getAttribute("aria-current")).toBeNull();
    expect(screen.getByRole("link", { name: "People & roles" }).getAttribute("href")).toBe("/app/workspaces/workspace-1/administration/people");
    expect(screen.getByRole("link", { name: "People & roles" }).getAttribute("aria-current")).toBe("page");
    expect(screen.getByRole("link", { name: "New conversation" }).getAttribute("aria-current")).toBeNull();
    expect(screen.getByRole("link", { name: "Platform operations" }).getAttribute("href")).toBe("/app/platform/operations");
  });

  it("renders route-backed platform context with exactly one contextual destination active", () => {
    pathnameState.value = "/app/platform/operations/telemetry";
    render(
      <AppShell canOperatePlatform user={user} workspaces={[]}>
        <p>Telemetry content</p>
      </AppShell>,
    );

    expect(screen.getByRole("link", { name: "Platform operations" }).getAttribute("aria-current")).toBe("page");
    expect(screen.getByRole("link", { name: "Overview" }).getAttribute("aria-current")).toBeNull();
    expect(screen.getByRole("link", { name: "Active alerts" }).getAttribute("href")).toBe("/app/platform/operations/alerts");
    expect(screen.getByRole("link", { name: "Global telemetry" }).getAttribute("aria-current")).toBe("page");
    expect(screen.getByRole("link", { name: "Operational policy" }).getAttribute("href")).toBe("/app/platform/operations/policy");
    expect(screen.getByRole("link", { name: "Back to chat" }).getAttribute("href")).toBe("/app");
  });

  it.each([
    ["/app/workspaces/workspace-1/documents", "Library"],
    ["/app/workspaces/workspace-1/documents/families/family-1", "Library"],
    ["/app/workspaces/workspace-1/documents/document-1", "Library"],
    ["/app/workspaces/workspace-1/documents/scheduled", "Scheduled"],
    ["/app/workspaces/workspace-1/documents/attention", "Needs attention"],
    ["/app/workspaces/workspace-1/documents/deleted", "Deleted history"],
    ["/app/workspaces/workspace-1/documents/settings/categories", "Categories"],
  ])("maps %s to one active knowledge-library destination", (path, activeLabel) => {
    pathnameState.value = path;
    render(
      <AppShell
        canOperatePlatform={false}
        user={user}
        workspaces={[{ public_id: "workspace-1", name: "Alderbridge", slug: "alderbridge", role: "owner" }]}
      >
        <p>Library content</p>
      </AppShell>,
    );

    const contextual = screen.getByRole("navigation", { name: "Knowledge library" });
    expect(screen.getByRole("link", { name: "Documents" }).getAttribute("aria-current")).toBe("page");
    expect(within(contextual).getByRole("link", { name: activeLabel }).getAttribute("aria-current")).toBe("page");
    expect(within(contextual).getAllByRole("link").filter((link) => link.getAttribute("aria-current") === "page")).toHaveLength(1);
  });

  it("does not activate documents for a path that merely contains the word", () => {
    pathnameState.value = "/app/workspaces/workspace-1/conversations/documents-review";
    render(
      <AppShell
        canOperatePlatform={false}
        user={user}
        workspaces={[{ public_id: "workspace-1", name: "Alderbridge", slug: "alderbridge", role: "owner" }]}
      >
        <p>Conversation content</p>
      </AppShell>,
    );

    expect(screen.getByRole("link", { name: "Documents" }).getAttribute("aria-current")).toBeNull();
    expect(screen.queryByRole("navigation", { name: "Knowledge library" })).toBeNull();
  });

  it("offers sign out from the named account menu", async () => {
    const interaction = userEvent.setup();
    render(
      <AppShell
        canOperatePlatform={false}
        user={user}
        workspaces={[{ public_id: "workspace-1", name: "Alderbridge", slug: "alderbridge", role: "owner" }]}
      >
        <p>Workspace content</p>
      </AppShell>,
    );

    await interaction.click(screen.getByRole("button", { name: "Account menu for David Oliver" }));
    expect(screen.getByRole("button", { name: "Sign out" })).toBeTruthy();
  });

  it("closes mobile navigation after following a destination and omits the duplicated wordmark", async () => {
    render(
      <AppShell
        canOperatePlatform={false}
        user={user}
        workspaces={[{ public_id: "workspace-1", name: "Alderbridge", slug: "alderbridge", role: "owner" }]}
      >
        <p>Workspace content</p>
      </AppShell>,
    );

    fireEvent.click(screen.getByRole("button", { name: "Open navigation" }));
    const dialog = screen.getByRole("dialog", { name: "Application navigation" });
    expect(within(dialog).getByRole("link", { name: "dolved" }).parentElement?.className).toContain("hidden");
    fireEvent.click(within(dialog).getByRole("link", { name: "Documents" }));
    await waitFor(() => expect(screen.queryByRole("dialog", { name: "Application navigation" })).toBeNull());
  });
});
