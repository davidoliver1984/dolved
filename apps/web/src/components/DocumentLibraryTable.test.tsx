import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { DocumentLibraryTable } from "@/components/DocumentLibraryTable";
import type { DocumentFamilyPage } from "@/lib/api";

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
});
