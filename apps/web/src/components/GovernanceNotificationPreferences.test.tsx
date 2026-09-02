import { cleanup, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";

const { personalMock, workspaceMock } = vi.hoisted(() => ({ personalMock: vi.fn(), workspaceMock: vi.fn() }));
vi.mock("@/lib/api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api")>()),
  updatePersonalGovernanceNotificationPreference: personalMock,
  updateWorkspaceGovernanceNotificationPreferences: workspaceMock,
}));

import { GovernanceNotificationPreferences } from "@/components/GovernanceNotificationPreferences";

describe("GovernanceNotificationPreferences", () => {
  afterEach(() => { cleanup(); vi.clearAllMocks(); });

  it("renders inherited personal categories and updates every closed category in a grouped choice", async () => {
    render(<GovernanceNotificationPreferences preview={{ workspace: { email_delivery_enabled: true, default_email_enabled: true, can_manage: true }, personal: [] }} workspacePublicId="workspace-1" />);
    expect(screen.getByRole("heading", { name: "Choose what reaches your inbox" })).not.toBeNull();
    await userEvent.click(screen.getByRole("checkbox", { name: "Import outcomes" }));
    await waitFor(() => expect(screen.getByRole("checkbox", { name: "Import outcomes" }).getAttribute("data-state")).toBe("unchecked"));
    expect(personalMock).not.toHaveBeenCalled();
  });

  it("conceals workspace-wide controls from ordinary members", () => {
    render(<GovernanceNotificationPreferences preview={{ workspace: { email_delivery_enabled: true, default_email_enabled: true, can_manage: false }, personal: [] }} workspacePublicId="workspace-1" />);
    expect(screen.queryByText("Workspace email policy")).toBeNull();
    expect(screen.getByText("Your email categories")).not.toBeNull();
  });
});
