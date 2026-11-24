import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { InvitationAcceptance } from "@/components/InvitationAcceptance";
import { acceptWorkspaceInvitation, ApiError, invitationBranding } from "@/lib/api";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));
vi.mock("@/lib/api", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/lib/api")>(),
  acceptWorkspaceInvitation: vi.fn(),
  invitationBranding: vi.fn(),
}));

describe("InvitationAcceptance", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(invitationBranding).mockRejectedValue(new Error("branding unavailable"));
  });

  it("sends signed-out recipients to login and preserves the invitation return path", async () => {
    vi.mocked(acceptWorkspaceInvitation).mockRejectedValue(new ApiError("Unauthenticated.", 401));
    render(<InvitationAcceptance token="invite-token" />);

    fireEvent.click(screen.getByRole("button", { name: "Accept invitation" }));

    await waitFor(() => expect(push).toHaveBeenCalledWith("/login?next=%2Finvitations%2Finvite-token"));
    expect(screen.queryByText(/expired, revoked/i)).toBeNull();
  });
});
