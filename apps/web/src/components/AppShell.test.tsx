import { cleanup, render, screen, waitFor } from "@testing-library/react";
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

    expect(screen.getByRole("link", { name: "New conversation" }).getAttribute("href")).toBe("/app/workspaces/workspace-1");
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

    expect(screen.getByRole("link", { name: "Administration" }).getAttribute("aria-current")).toBe("page");
    expect(screen.getByRole("link", { name: "People & roles" }).getAttribute("href")).toBe("/app/workspaces/workspace-1/administration/people");
    expect(screen.getByRole("link", { name: "Platform operations" }).getAttribute("href")).toBe("/app/platform/operations");
  });
});
