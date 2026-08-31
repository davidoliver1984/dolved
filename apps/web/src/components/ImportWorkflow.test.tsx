import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ImportWorkflow } from "@/components/ImportWorkflow";
import type { ImportBatch, ImportMatches } from "@/lib/import-workflow";

const configuration = {
  formats: { pdf: ["application/pdf"] },
  max_upload_bytes: 25 * 1024 * 1024,
  upload_concurrency: 3,
  retention_days: 7,
  review_options: {
    categories: [], tags: [], locations: [],
    owners: [{ public_id: "owner-1", name: "David Oliver" }],
    current_user_public_id: "owner-1",
  },
};

const { adoptImport, getImportBatch, importMatches, listImportBatches, replaceImportFile } = vi.hoisted(() => ({
  adoptImport: vi.fn(async () => undefined),
  getImportBatch: vi.fn<() => Promise<ImportBatch>>(),
  importMatches: vi.fn<() => Promise<ImportMatches>>(async () => ({
    profile_version: "import-match-v1",
    exact_live_duplicates: [],
    deleted_duplicates: [],
    applicability_only_redirect_document_id: null,
    family_candidates: [],
  })),
  listImportBatches: vi.fn<() => Promise<ImportBatch[]>>(async () => []),
  replaceImportFile: vi.fn(async () => undefined),
}));
vi.mock("@/lib/import-workflow", async () => {
  const actual = await vi.importActual<typeof import("@/lib/import-workflow")>("@/lib/import-workflow");
  return {
    ...actual,
    adoptImport,
    getImportBatch,
    importMatches,
    importConfiguration: vi.fn(async () => configuration),
    listImportBatches,
    replaceImportFile,
  };
});

afterEach(() => { cleanup(); vi.clearAllMocks(); });

describe("ImportWorkflow", () => {
  it("presents the complete honest seven-stage flow before upload", async () => {
    render(<ImportWorkflow workspacePublicId="workspace-1" />);

    await waitFor(() => expect(screen.getByText("Add documents")).toBeTruthy());
    for (const stage of ["Select", "Stage", "Verify", "Match", "Review", "Promote", "Index"]) {
      expect(screen.getByText(stage)).toBeTruthy();
    }
    expect(screen.getByText(/Nothing reaches the Library until/)).toBeTruthy();
  });

  it("validates a source locally and makes the staging action explicit", async () => {
    render(<ImportWorkflow workspacePublicId="workspace-1" />);
    await waitFor(() => expect(screen.getByLabelText(/choose files/i)).toBeTruthy());
    fireEvent.change(screen.getByLabelText(/choose files/i), {
      target: { files: [new File(["source"], "Medication policy.pdf", { type: "application/pdf" })] },
    });

    expect(screen.getByText("Medication policy.pdf")).toBeTruthy();
    expect(screen.getByRole("button", { name: /Stage and verify document/ })).toBeTruthy();
  });

  it("reports only the honest coarse progress available after promotion", async () => {
    listImportBatches.mockResolvedValueOnce([{
      public_id: "batch-1",
      status: "open",
      retention_expires_at: "2026-09-07T12:00:00Z",
      created_at: "2026-08-31T12:00:00Z",
      items: [
        {
          public_id: "item-queued", filename: "Queued.pdf", declared_media_type: "application/pdf", size_bytes: 100,
          preflight_status: "verified", preflight_rejection_reason: null, match_status: "resolved", decision_ready: true,
          promotion: { public_id: "promotion-queued", status: "committed", reason: null },
          document: { public_id: "document-queued", status: "pending" },
        },
        {
          public_id: "item-processing", filename: "Processing.pdf", declared_media_type: "application/pdf", size_bytes: 100,
          preflight_status: "verified", preflight_rejection_reason: null, match_status: "resolved", decision_ready: true,
          promotion: { public_id: "promotion-processing", status: "committed", reason: null },
          document: { public_id: "document-processing", status: "processing" },
        },
        {
          public_id: "item-indexed", filename: "Indexed.pdf", declared_media_type: "application/pdf", size_bytes: 100,
          preflight_status: "verified", preflight_rejection_reason: null, match_status: "resolved", decision_ready: true,
          promotion: { public_id: "promotion-indexed", status: "committed", reason: null },
          document: { public_id: "document-indexed", status: "indexed" },
        },
      ],
    }]);
    render(<ImportWorkflow workspacePublicId="workspace-1" />);

    await waitFor(() => expect(screen.getByRole("button", { name: /3 documents/ })).toBeTruthy());
    fireEvent.click(screen.getByRole("button", { name: /3 documents/ }));

    expect(screen.getAllByText("Promoted · queued").length).toBeGreaterThan(0);
    expect(screen.getAllByText("Processing").length).toBeGreaterThan(0);
    expect(screen.getAllByText("Indexed").length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Finer ingestion sub-stages are not claimed here/)).toHaveLength(3);
  });

  it("offers an explicit revised-decision adoption action for a terminal conflict", async () => {
    const conflicted: ImportBatch = {
      public_id: "batch-conflict",
      status: "open",
      retention_expires_at: "2026-09-07T12:00:00Z",
      created_at: "2026-08-31T12:00:00Z",
      items: [{
        public_id: "item-conflict", filename: "Controlled drugs.pdf", declared_media_type: "application/pdf", size_bytes: 100,
        preflight_status: "verified", preflight_rejection_reason: null, match_status: "resolved", decision_ready: true,
        promotion: { public_id: "promotion-conflict", status: "conflict", reason: "authorization_changed" },
        document: null,
      }],
    };
    listImportBatches.mockResolvedValueOnce([conflicted]);
    getImportBatch.mockResolvedValue(conflicted);
    render(<ImportWorkflow workspacePublicId="workspace-1" />);

    await waitFor(() => expect(screen.getByRole("button", { name: /1 document/ })).toBeTruthy());
    fireEvent.click(screen.getByRole("button", { name: /1 document/ }));
    fireEvent.click(screen.getByRole("button", { name: "Review and adopt" }));
    await waitFor(() => expect(screen.getByRole("heading", { name: "Review and adopt Controlled drugs.pdf" })).toBeTruthy());
    fireEvent.click(screen.getByRole("button", { name: "Save and adopt" }));

    await waitFor(() => expect(adoptImport).toHaveBeenCalledWith(
      "workspace-1",
      "batch-conflict",
      "item-conflict",
      expect.objectContaining({ family: { mode: "new", title: "Controlled drugs" } }),
    ));
  });

  it("stages a corrected replacement instead of promoting a duplicate", async () => {
    const duplicate: ImportBatch = {
      public_id: "batch-duplicate",
      status: "open",
      retention_expires_at: "2026-09-07T12:00:00Z",
      created_at: "2026-08-31T12:00:00Z",
      items: [{
        public_id: "item-duplicate", filename: "Policy.pdf", declared_media_type: "application/pdf", size_bytes: 100,
        preflight_status: "verified", preflight_rejection_reason: null, match_status: "pending", decision_ready: false,
        promotion: null, document: null,
      }],
    };
    listImportBatches.mockResolvedValueOnce([duplicate]);
    getImportBatch.mockResolvedValue(duplicate);
    importMatches.mockResolvedValueOnce({
      profile_version: "import-match-v1",
      exact_live_duplicates: [{ document_id: "document-existing", family_id: "family-existing", status: "indexed" }],
      deleted_duplicates: [],
      applicability_only_redirect_document_id: null,
      family_candidates: [],
    });
    render(<ImportWorkflow workspacePublicId="workspace-1" />);

    await waitFor(() => expect(screen.getByRole("button", { name: /1 document/ })).toBeTruthy());
    fireEvent.click(screen.getByRole("button", { name: /1 document/ }));
    fireEvent.click(screen.getByRole("button", { name: "Review match and metadata" }));
    await waitFor(() => expect(screen.getByRole("heading", { name: "Duplicate found" })).toBeTruthy());
    const corrected = new File(["corrected"], "Policy corrected.pdf", { type: "application/pdf" });
    fireEvent.change(screen.getByLabelText("Choose corrected file"), { target: { files: [corrected] } });

    await waitFor(() => expect(replaceImportFile).toHaveBeenCalledWith(
      "workspace-1", "batch-duplicate", "item-duplicate", corrected, expect.any(Function),
    ));
  });
});
