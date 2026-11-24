import { cleanup, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { VerificationResultAction } from "@/components/VerificationResultAction";

describe("VerificationResultAction", () => {
  beforeEach(() => window.localStorage.clear());
  afterEach(cleanup);

  it("continues a newly verified invitee back to the invitation", async () => {
    window.localStorage.setItem("dolved.postVerificationReturnTo", "/invitations/invite-token");

    render(<VerificationResultAction verified />);

    const link = await screen.findByRole("link", { name: "Continue to invitation" });
    expect(link.getAttribute("href")).toBe("/invitations/invite-token");
    await waitFor(() => expect(window.localStorage.getItem("dolved.postVerificationReturnTo")).toBeNull());
  });

  it("ignores an unsafe stored return address", () => {
    window.localStorage.setItem("dolved.postVerificationReturnTo", "https://evil.example");

    render(<VerificationResultAction verified />);

    expect(screen.getByRole("link", { name: "Open workspace" }).getAttribute("href")).toBe("/app");
  });
});
