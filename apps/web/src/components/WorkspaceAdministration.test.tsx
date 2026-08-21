import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { WorkspaceAdministration } from "@/components/WorkspaceAdministration";
import type { WorkspaceAdministrationSnapshot } from "@/lib/api";

afterEach(cleanup);

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
});
