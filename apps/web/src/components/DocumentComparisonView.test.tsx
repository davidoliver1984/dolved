import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { DocumentComparisonView } from "@/components/DocumentComparisonView";
import type { DocumentComparison } from "@/lib/server-api";

const comparison: DocumentComparison = {
  available: true,
  from: { document: { public_id: "v1", source_filename: "v1.pdf", publisher_label: null, source_url: null, governance_status: "withdrawn", effective_from: null, approved_at: null, withdrawn_at: null }, content_available: true, truncated: false, elements: [], warnings: [] },
  to: { document: { public_id: "v2", source_filename: "v2.pdf", publisher_label: null, source_url: null, governance_status: "approved", effective_from: null, approved_at: null, withdrawn_at: null }, content_available: true, truncated: false, elements: [], warnings: [] },
  alignment_status: "reliable",
  formatting_comparison: "unavailable",
  formatting_reason: "Formatting signals are unavailable.",
  change_counts: { added: 1, removed: 0, modified: 1, moved: 0, unchanged: 1 },
  differences: [
    { id: "same", position: 1, section: "Safety", status: "unchanged", before: { id: "a", ordinal: 1, kind: "paragraph", text: "Keep this." }, after: { id: "b", ordinal: 1, kind: "paragraph", text: "Keep this." } },
    { id: "changed", position: 2, section: "Safety", status: "modified", before: { id: "c", ordinal: 2, kind: "paragraph", text: "Report later." }, after: { id: "d", ordinal: 2, kind: "paragraph", text: "Report immediately." } },
    { id: "added", position: 3, section: "Safety", status: "added", before: null, after: { id: "e", ordinal: 3, kind: "paragraph", text: "Call the manager." } },
  ],
};

describe("DocumentComparisonView", () => {
  afterEach(cleanup);

  it("shows change counts, filters and collapsed unchanged context", () => {
    render(<DocumentComparisonView comparison={comparison} familyName="Safety procedure" />);
    expect(screen.getByRole("button", { name: "All changes 2" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "Modified 1" })).toBeTruthy();
    expect(screen.queryByText("Keep this.")).toBeNull();
    fireEvent.click(screen.getByRole("button", { name: "Show 1 unchanged section" }));
    expect(screen.getAllByText("Keep this.")).toHaveLength(2);
  });

  it("provides inline mode and restrained word-level highlighting for aligned modifications", () => {
    render(<DocumentComparisonView comparison={comparison} familyName="Safety procedure" />);
    fireEvent.click(screen.getByRole("button", { name: "Inline" }));
    expect(screen.getByText("later.").className).toContain("bg-status-destructive/30");
    expect(screen.getByText("immediately.").className).toContain("bg-status-success/30");
  });

  it("states when alignment is unavailable", () => {
    render(<DocumentComparisonView comparison={{ ...comparison, alignment_status: "unavailable", alignment_reason: "Extraction could not be aligned." }} familyName="Safety procedure" />);
    expect(screen.getByText("Extraction could not be aligned.")).toBeTruthy();
  });
});
