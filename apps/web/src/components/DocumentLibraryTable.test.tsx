import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { DocumentLibraryTable } from "@/components/DocumentLibraryTable";
import { createBulkOperation, type DocumentFamilyPage } from "@/lib/api";

vi.mock("@/lib/api", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/lib/api")>(),
  createBulkOperation: vi.fn(),
  confirmBulkOperation: vi.fn(),
}));

const page: DocumentFamilyPage = {
  data: [{
    public_id: "family-1",
    name: "Medication procedure",
    description: null,
    category: { public_id: "category-1", name: "Clinical", status: "active" },
    tags: [{ public_id: "tag-1", name: "Medication", status: "active" }],
    owner: { public_id: "user-1", name: "David Oliver", needs_reassignment: false },
    review_due_date: "2027-03-31",
    last_meaningful_update: "2026-08-29T12:00:00Z",
    state: "current",
    scheduled_effective_from: null,
    version_count: 2,
    historical: false,
    current_version: {
      public_id: "document-2",
      technical_status: "indexed",
      source_filename: "medication-v2.pdf",
      media_type: "application/pdf",
      size_bytes: 428_000,
      checksum_verification_status: "verified",
      governance_status: "approved",
      effective_from: "2026-08-01T00:00:00Z",
      approved_at: "2026-08-01T00:00:00Z",
      withdrawn_at: null,
      extraction_warning_count: 1,
    },
  }],
  meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
};

describe("DocumentLibraryTable", () => {
  beforeEach(() => vi.clearAllMocks());
  it("renders authoritative family rows for desktop and mobile with accessible filters", () => {
    render(<DocumentLibraryTable page={page} query={{}} workspacePublicId="workspace-1" />);
    expect(screen.getByRole("heading", { name: "Knowledge library" })).toBeDefined();
    expect(screen.getByRole("textbox", { name: "Search" })).toBeDefined();
    expect(screen.getAllByRole("link", { name: /Medication procedure/ })).toHaveLength(2);
    expect(screen.getAllByText("Current")).toHaveLength(2);
    expect(screen.getAllByText("David Oliver")).toHaveLength(2);
    expect(screen.getByText("Applicability is not confidentiality.", { exact: false })).toBeDefined();
    expect(within(screen.getByRole("table")).getByText("medication-v2.pdf", { exact: false })).toBeDefined();
  });

  it("renders the truthful empty state", () => {
    render(<DocumentLibraryTable page={{ data: [], meta: { ...page.meta, total: 0 } }} query={{ search: "missing" }} workspacePublicId="workspace-1" />);
    expect(screen.getByText("No matching document families")).toBeDefined();
  });

  it("distinguishes current-page selection from a server-frozen all-filtered preflight", async () => {
    vi.mocked(createBulkOperation).mockResolvedValue({ data: {
      public_id: "operation-1", operation_type: "bulk_review_date_assignment", status: "awaiting_confirmation",
      selection_mode: "all_filtered", payload: { review_due_date: "2027-06-01" }, filters: { search: "medication" },
      membership_digest: "digest", confirmed_at: null, cancellation_requested_at: null,
      counts: { total: 3, eligible: 2, excluded: 1, open_attempts: 0, waiting_on_subordinate: 0, succeeded: 0, skipped: 0, failed_retryable: 0, failed_permanent: 0, cancelled: 0 },
      exclusions: { already_assigned: 1 },
      items: [
        { ordinal: 1, target_kind: "family", target_public_id: "family-1", target_display_label: "Medication procedure", eligibility_status: "eligible", exclusion_reason: null, execution_status: "eligible", terminal_reason: null, result_identity: null },
        { ordinal: 2, target_kind: "family", target_public_id: "family-2", target_display_label: "Medicines policy", eligibility_status: "eligible", exclusion_reason: null, execution_status: "eligible", terminal_reason: null, result_identity: null },
        { ordinal: 3, target_kind: "family", target_public_id: "family-3", target_display_label: "Medication archive", eligibility_status: "excluded", exclusion_reason: "already_assigned", execution_status: "excluded", terminal_reason: null, result_identity: null },
      ],
    } });
    render(<DocumentLibraryTable canManage metadata={{ categories: [], tags: [], owners: [], locations: [] }} page={{ ...page, meta: { ...page.meta, total: 3 } }} query={{ search: "medication" }} workspacePublicId="workspace-1" />);
    fireEvent.click(screen.getByRole("button", { name: "Select current page" }));
    fireEvent.click(screen.getByRole("button", { name: "Select all 3 filtered results" }));
    fireEvent.change(screen.getByLabelText("Review date"), { target: { value: "2027-06-01" } });
    fireEvent.click(screen.getByRole("button", { name: "Review eligibility" }));

    await waitFor(() => expect(createBulkOperation).toHaveBeenCalledWith("workspace-1", expect.objectContaining({
      selection_mode: "all_filtered", target_public_ids: [], filters: { search: "medication" },
      payload: { review_due_date: "2027-06-01" },
    })));
    expect(screen.getByRole("heading", { name: "Confirm Set review date" })).toBeDefined();
    expect(screen.getByText("Medication archive")).toBeDefined();
    expect(screen.getByText("Already assigned")).toBeDefined();
  });
});
