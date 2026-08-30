import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { DeletedDocumentFamilyHistory } from "@/components/DeletedDocumentFamilyHistory";
import type { DeletedDocumentFamilyPage } from "@/lib/server-api";

describe("DeletedDocumentFamilyHistory", () => {
  afterEach(cleanup);

  it("renders retained deletion and audit lineage without inventing a reason", () => {
    const page: DeletedDocumentFamilyPage = { data: [{ family: { public_id: "family", name: "Retired guidance" }, operation_public_id: "operation", deleted_at: "2026-08-28T14:22:00Z", reason: null, audit_reference: "audit-123", requested_by: { public_id: "user", name: "David Oliver" }, versions_removed: 2 }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } };
    render(<DeletedDocumentFamilyHistory page={page} workspacePublicId="workspace" />);
    expect(screen.getByText("Retired guidance")).toBeTruthy();
    expect(screen.getByText("David Oliver")).toBeTruthy();
    expect(screen.getByText("audit-123")).toBeTruthy();
    expect(screen.getByText("No reason was recorded.")).toBeTruthy();
  });

  it("renders a truthful empty state", () => {
    render(<DeletedDocumentFamilyHistory page={{ data: [], meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 } }} workspacePublicId="workspace" />);
    expect(screen.getByRole("heading", { name: "No deleted document families" })).toBeTruthy();
  });
});
