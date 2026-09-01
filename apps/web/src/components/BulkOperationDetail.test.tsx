import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { BulkOperationDetail } from "@/components/BulkOperationDetail";
import type { BulkOperationSnapshot } from "@/lib/api";

vi.mock("@/lib/api", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/lib/api")>(),
  bulkOperation: vi.fn(), cancelBulkOperation: vi.fn(), retryBulkOperation: vi.fn(),
}));

const operation: BulkOperationSnapshot = {
  public_id: "operation-1", operation_type: "bulk_review_date_assignment", status: "completed_with_exceptions",
  selection_mode: "current_page", payload: { review_due_date: "2027-06-01" }, filters: {}, membership_digest: "digest",
  confirmed_at: "2026-09-01T08:00:00Z", cancellation_requested_at: null,
  counts: { total: 3, eligible: 0, excluded: 0, open_attempts: 0, waiting_on_subordinate: 0, succeeded: 1, skipped: 1, failed_retryable: 0, failed_permanent: 1, cancelled: 0 },
  exclusions: {},
  items: [
    { ordinal: 1, target_kind: "family", target_public_id: "family-1", target_display_label: "Medication procedure", eligibility_status: "eligible", exclusion_reason: null, execution_status: "succeeded", terminal_reason: null, result_identity: null },
    { ordinal: 2, target_kind: "family", target_public_id: "family-2", target_display_label: "Safeguarding policy", eligibility_status: "eligible", exclusion_reason: null, execution_status: "skipped", terminal_reason: "expected_state_mismatch", result_identity: null },
    { ordinal: 3, target_kind: "family", target_public_id: "family-3", target_display_label: "Infection control", eligibility_status: "eligible", exclusion_reason: null, execution_status: "failed_permanent", terminal_reason: "execution_failed", result_identity: null },
  ],
};

describe("BulkOperationDetail", () => {
  it("renders durable progress, partial completion and expandable truthful outcomes", () => {
    render(<BulkOperationDetail initial={operation} workspacePublicId="workspace-1" />);
    expect(screen.getAllByText("Completed with exceptions").length).toBeGreaterThan(0);
    expect(screen.getByText("Counts come from durable item and attempt states.", { exact: false })).toBeDefined();
    expect(screen.getByText("Medication procedure")).toBeDefined();
    expect(screen.getByText("Safeguarding policy")).toBeDefined();
    expect(screen.getByRole("link", { name: "Back to library" }).getAttribute("href")).toBe("/app/workspaces/workspace-1/documents");
    expect(screen.queryByRole("button", { name: "Cancel remaining work" })).toBeNull();
  });
});
