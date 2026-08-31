import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ImportWorkflow } from "@/components/ImportWorkflow";
import type { ImportBatch } from "@/lib/import-workflow";

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

const { listImportBatches } = vi.hoisted(() => ({
  listImportBatches: vi.fn<() => Promise<ImportBatch[]>>(async () => []),
}));
vi.mock("@/lib/import-workflow", async () => {
  const actual = await vi.importActual<typeof import("@/lib/import-workflow")>("@/lib/import-workflow");
  return {
    ...actual,
    importConfiguration: vi.fn(async () => configuration),
    listImportBatches,
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
});
