import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { DocumentApplicabilityEditor } from "@/components/DocumentApplicabilityEditor";
import { createApplicabilitySuccessor, type DocumentVersion } from "@/lib/api";

const refresh = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ refresh }) }));
vi.mock("@/lib/api", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/lib/api")>(),
  createApplicabilitySuccessor: vi.fn(),
}));

const version: DocumentVersion = {
  public_id: "document-1",
  family_public_id: "family-1",
  source_filename: "policy.pdf",
  publisher_label: null,
  source_url: null,
  media_type: "application/pdf",
  size_bytes: 123,
  status: "indexed",
  governance_status: "approved",
  predecessor_public_id: null,
  effective_from: "2026-11-01T00:00:00Z",
  approved_at: "2026-11-01T00:00:00Z",
  withdrawn_at: null,
  is_current_authority: true,
  extraction_warning_count: 0,
  applicability: { scope: "universal", locations: [] },
  capabilities: { approve: false, withdraw: true, reschedule: false, create_applicability_successor: true, correct_timestamps: false },
};

describe("DocumentApplicabilityEditor", () => {
  beforeEach(() => vi.clearAllMocks());

  it("explains successor behaviour and submits selected registered locations", async () => {
    vi.mocked(createApplicabilitySuccessor).mockResolvedValue({} as never);
    render(<DocumentApplicabilityEditor locations={[{ public_id: "location-1", name: "Oakfield Lodge" }]} version={version} workspacePublicId="workspace-1" />);

    fireEvent.click(screen.getByText("Edit applicability"));
    expect(screen.getByText(/there is no separate successor document to select/i)).toBeDefined();
    expect(screen.getByRole("radio", { name: "Organisation-wide" }).closest("label")?.className).toContain("min-h-11");
    fireEvent.click(screen.getByRole("radio", { name: "Selected registered locations" }));
    fireEvent.click(screen.getByRole("checkbox", { name: "Oakfield Lodge" }));
    fireEvent.change(screen.getByLabelText("Effective date"), { target: { value: "2026-12-01" } });
    fireEvent.click(screen.getByRole("button", { name: "Save applicability" }));

    await waitFor(() => expect(createApplicabilitySuccessor).toHaveBeenCalledWith("workspace-1", "document-1", "2026-12-01", ["location-1"]));
    expect(refresh).toHaveBeenCalled();
  });
});
