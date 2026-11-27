import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { DocumentFamilyDetail } from "@/lib/server-api";

const {
  configurationMock,
  createBatchMock,
  decideMock,
  getBatchMock,
  matchesMock,
  promoteMock,
  refreshMock,
  stageMock,
} = vi.hoisted(() => ({
  configurationMock: vi.fn(),
  createBatchMock: vi.fn(),
  decideMock: vi.fn(),
  getBatchMock: vi.fn(),
  matchesMock: vi.fn(),
  promoteMock: vi.fn(),
  refreshMock: vi.fn(),
  stageMock: vi.fn(),
}));

vi.mock("next/navigation", () => ({ useRouter: () => ({ refresh: refreshMock }) }));
vi.mock("@/lib/import-workflow", () => ({
  createImportBatch: createBatchMock,
  decideImport: decideMock,
  getImportBatch: getBatchMock,
  importConfiguration: configurationMock,
  importMatches: matchesMock,
  promoteImport: promoteMock,
  stageImportFile: stageMock,
  validateDocumentFile: vi.fn(() => null),
}));

import { DocumentVersionUpload } from "./DocumentVersionUpload";

const detail: DocumentFamilyDetail = {
  family: {
    public_id: "family-1",
    name: "Safeguarding adults procedure",
    description: "The current safeguarding procedure.",
    review_due_date: "2027-01-01",
    category: { public_id: "category-1", name: "Safety", status: "active" },
    owner: { public_id: "owner-1", name: "David Oliver" },
    tags: [{ public_id: "tag-1", name: "Safeguarding" }],
    capabilities: { edit: true },
    edit_options: null,
  },
  history: {
    data: [{
      public_id: "document-1",
      family_public_id: "family-1",
      source_filename: "safeguarding.pdf",
      publisher_label: "Cherish",
      source_url: null,
      media_type: "application/pdf",
      size_bytes: 1024,
      status: "indexed",
      governance_status: "approved",
      predecessor_public_id: null,
      effective_from: "2026-11-24T00:00:00Z",
      approved_at: "2026-11-24T10:00:00Z",
      withdrawn_at: null,
      is_current_authority: true,
      extraction_warning_count: 0,
      applicability: { scope: "selected", locations: [{ public_id: "location-1", name: "Oakfield Lodge" }] },
      capabilities: { approve: false, withdraw: true, reschedule: false, create_applicability_successor: true, correct_timestamps: true },
    }],
    meta: { current_version_public_id: "document-1", locations: [{ public_id: "location-1", name: "Oakfield Lodge" }] },
  },
};

function batch(document = false) {
  return {
    public_id: "batch-1",
    status: "open" as const,
    retention_expires_at: "2026-12-02T00:00:00Z",
    created_at: "2026-11-25T00:00:00Z",
    items: [{
      public_id: "item-1",
      filename: "safeguarding-v2.pdf",
      declared_media_type: "application/pdf",
      size_bytes: 2048,
      preflight_status: "verified" as const,
      preflight_rejection_reason: null,
      match_status: "pending" as const,
      decision_ready: false,
      promotion: document ? { public_id: "promotion-1", status: "committed", reason: null } : null,
      document: document ? { public_id: "document-2", status: "uploaded" } : null,
    }],
  };
}

describe("DocumentVersionUpload", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    configurationMock.mockResolvedValue({ formats: { pdf: ["application/pdf"] }, max_upload_bytes: 25 * 1024 * 1024, upload_concurrency: 3, retention_days: 7, review_options: { categories: [], tags: [], owners: [], locations: [], current_user_public_id: "owner-1" } });
    createBatchMock.mockResolvedValue({ batch: batch(), uploads: [{ item_public_id: "item-1", upload: { url: "https://upload.invalid", method: "PUT", headers: {}, expires_at: "2026-11-25T01:00:00Z" } }] });
    stageMock.mockImplementation(async (_workspace, _batch, _item, _file, _upload, onProgress) => onProgress(100));
    getBatchMock.mockResolvedValueOnce(batch()).mockResolvedValueOnce(batch(true));
    matchesMock.mockResolvedValue({ profile_version: "v1", exact_live_duplicates: [], deleted_duplicates: [], applicability_only_redirect_document_id: null, family_candidates: [] });
  });

  it("creates a successor in the known family without asking the user to match it again", async () => {
    const user = userEvent.setup();
    render(<DocumentVersionUpload detail={detail} workspacePublicId="workspace-1" />);

    await user.click(screen.getByRole("button", { name: "Upload new version" }));
    expect(screen.getByText(/added to Safeguarding adults procedure/)).not.toBeNull();
    expect(screen.getByText(/Oakfield Lodge/)).not.toBeNull();
    await user.upload(screen.getByLabelText("Revised document"), new File(["revised"], "safeguarding-v2.pdf", { type: "application/pdf" }));
    await user.click(screen.getByRole("button", { name: "Upload and index" }));

    await waitFor(() => expect(decideMock).toHaveBeenCalledWith(
      "workspace-1",
      "batch-1",
      "item-1",
      expect.objectContaining({
        family: { mode: "successor", family_public_id: "family-1" },
        applicability: { location_public_ids: ["location-1"] },
      }),
    ));
    expect(promoteMock).toHaveBeenCalledWith("workspace-1", "batch-1", "item-1");
    expect(await screen.findByText("The new version has been created and indexing has started.")).not.toBeNull();
    expect(refreshMock).toHaveBeenCalledOnce();
  });
});
