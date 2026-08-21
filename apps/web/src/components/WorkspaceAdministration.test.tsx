import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { WorkspaceAdministration } from "@/components/WorkspaceAdministration";
import * as api from "@/lib/api";
import type { WorkspaceAdministrationSnapshot } from "@/lib/api";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const snapshot: WorkspaceAdministrationSnapshot = {
  memberships: [
    {
      public_id: "owner-membership",
      user: { name: "Workspace Owner", email: "owner@example.test" },
      role: "owner",
      joined_at: "2026-08-19T10:00:00Z",
      capabilities: { change_role: false, remove: false, transfer_ownership: false },
    },
    {
      public_id: "member-membership",
      user: { name: "Workspace Member", email: "member@example.test" },
      role: "member",
      joined_at: "2026-08-19T10:00:00Z",
      capabilities: { change_role: true, remove: true, transfer_ownership: true },
    },
  ],
  invitations: [
    {
      public_id: "invitation",
      invited_email: "new@example.test",
      intended_role: "member",
      status: "pending",
      expires_at: "2026-08-26T10:00:00Z",
      created_at: "2026-08-19T10:00:00Z",
      capabilities: { revoke: true },
    },
  ],
};

describe("WorkspaceAdministration", () => {
  it("renders only server-authorized owner controls", () => {
    render(<WorkspaceAdministration actorRole="owner" initialSnapshot={snapshot} view="people" workspaceId="workspace" />);

    expect(screen.getByRole("button", { name: "Make admin" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "Transfer ownership" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "Remove" })).toBeTruthy();
    expect(screen.queryByRole("button", { name: "Leave workspace" })).toBeNull();
  });

  it("separates invitation administration and preserves one-time-link semantics", () => {
    render(<WorkspaceAdministration actorRole="owner" initialSnapshot={snapshot} view="invitations" workspaceId="workspace" />);
    expect(screen.getByRole("button", { name: "Issue invitation" })).toBeTruthy();
    expect(screen.getByText(/Validity and email delivery are separate/)).toBeTruthy();
    expect(screen.getByRole("button", { name: "Revoke" })).toBeTruthy();
    expect(screen.queryByRole("button", { name: "Remove" })).toBeNull();
  });

  it("keeps the one-time invitation link bounded and exposes an icon-labelled copy action", async () => {
    const invitationUrl = `http://localhost:3000/invitations/${"a".repeat(96)}`;
    vi.spyOn(api, "issueWorkspaceInvitation").mockResolvedValue({
      data: {
        invitation: null,
        invitation_link: invitationUrl,
        link_returned_once: true,
        delivery_status: "sent",
        replayed: false,
        already_member: false,
      },
    });
    vi.spyOn(api, "workspaceMembers").mockResolvedValue({ data: snapshot.memberships, meta: { total: snapshot.memberships.length } });
    vi.spyOn(api, "workspaceInvitations").mockResolvedValue({ data: snapshot.invitations, meta: { total: snapshot.invitations.length } });
    render(<WorkspaceAdministration actorRole="owner" initialSnapshot={snapshot} view="invitations" workspaceId="workspace" />);

    fireEvent.change(screen.getByRole("textbox", { name: "Email address" }), { target: { value: "invitee@example.test" } });
    fireEvent.click(screen.getByRole("button", { name: "Issue invitation" }));

    const copyButton = await screen.findByRole("button", { name: "Copy invitation link" });
    expect(copyButton.querySelector("svg")).toBeTruthy();
    const link = screen.getByText(invitationUrl);
    expect(link.className).toContain("max-w-full");
    await waitFor(() => expect(api.workspaceInvitations).toHaveBeenCalledOnce());
  });
});
