import { cleanup, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const { dismissMock, listMock, pushMock, readMock } = vi.hoisted(() => ({
  dismissMock: vi.fn(),
  listMock: vi.fn(),
  pushMock: vi.fn(),
  readMock: vi.fn(),
}));

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: pushMock }) }));

vi.mock("@/lib/api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api")>()),
  dismissGovernanceNotification: dismissMock,
  governanceNotifications: listMock,
  markGovernanceNotificationRead: readMock,
}));

import { GovernanceActionableWork } from "@/components/GovernanceActionableWork";
import { GovernanceInbox } from "@/components/GovernanceInbox";

describe("GovernanceInbox", () => {
  beforeEach(() => {
    listMock.mockResolvedValue({
      data: [{
        public_id: "notification-1",
        title: "Review due soon",
        message: "The medication policy is approaching its review date.",
        severity: "action_required",
        target_label: "Medication policy",
        target_route: "/app/workspaces/workspace-1/documents/families/family-1",
        read_at: null,
        dismissed_at: null,
        created_at: "2026-09-02T12:00:00Z",
      }],
      meta: { unread_count: 147, next_cursor: null },
    });
    readMock.mockResolvedValue(undefined);
    dismissMock.mockResolvedValue(undefined);
  });

  afterEach(cleanup);

  it("shows an exact accessible unread count and a visually capped badge", async () => {
    render(<GovernanceInbox workspacePublicId="workspace-1" />);
    const trigger = await screen.findByRole("button", { name: "147 unread notifications" });
    expect(trigger.textContent).toContain("99+");
    await userEvent.click(trigger);
    expect(await screen.findByText("Review due soon")).not.toBeNull();
    const target = screen.getByRole("link", { name: /Review due soon/ });
    expect(target.getAttribute("href")).toBe("/app/workspaces/workspace-1/documents/families/family-1");
    await userEvent.click(target);
    await waitFor(() => expect(readMock).toHaveBeenCalledWith("workspace-1", "notification-1"));
    expect(pushMock).toHaveBeenCalledWith("/app/workspaces/workspace-1/documents/families/family-1");
  });

  it("renders actionable work from live counts with real drill-down routes", () => {
    render(<GovernanceActionableWork data={{ awaiting_approval: 2, imports_processing: 3, imports_warning: 1, scheduled_changes: 4, review_due_soon: 5, review_overdue: 6 }} workspacePublicId="workspace-1" />);
    expect(screen.getByText("What needs attention now")).not.toBeNull();
    expect(screen.getByText(/Dismissing a notification never removes work from these cards/)).not.toBeNull();
    const links = screen.getAllByRole("link", { name: "View details" });
    expect(links[0].getAttribute("href")).toBe("/app/workspaces/workspace-1/documents/attention");
    expect(links).toHaveLength(6);
  });

  it("keeps already-loaded notifications visible during a partial refresh failure", () => {
    render(<GovernanceInbox preview={{ items: [{
      public_id: "retained",
      title: "Review overdue",
      message: "A document family has passed its review date.",
      severity: "warning",
      target_label: null,
      target_route: null,
      read_at: null,
      dismissed_at: null,
      created_at: "2026-09-02T12:00:00Z",
    }], unread: 1, initiallyOpen: true, state: "error" }} workspacePublicId="workspace-1" />);
    expect(screen.getByText("Some notifications could not be loaded.")).not.toBeNull();
    expect(screen.getByText("Review overdue")).not.toBeNull();
    expect(screen.getByRole("button", { name: /Review overdue/ })).not.toBeNull();
  });
});
