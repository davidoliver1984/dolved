import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { familyDetailMock, notFoundMock, refreshMock, userWorkspaceMock } = vi.hoisted(() => ({
  familyDetailMock: vi.fn(),
  notFoundMock: vi.fn(() => {
    throw new Error("NEXT_NOT_FOUND");
  }),
  refreshMock: vi.fn(),
  userWorkspaceMock: vi.fn(),
}));

vi.mock("next/navigation", () => ({ notFound: notFoundMock, useRouter: () => ({ refresh: refreshMock }) }));
vi.mock("@/lib/server-api", () => ({
  initialDocumentFamilyDetail: familyDetailMock,
  userWorkspace: userWorkspaceMock,
}));

import DocumentFamilyPage from "./page";

describe("document family route scaffold", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    userWorkspaceMock.mockResolvedValue({
      public_id: "workspace-1",
      name: "Alderbridge",
      slug: "alderbridge",
      role: "owner",
    });
    familyDetailMock.mockResolvedValue({
      family: {
        public_id: "family-1",
        name: "Medication procedure",
        description: "How omitted doses are managed.",
        review_due_date: "2027-01-01",
        category: { public_id: "category-1", name: "Medication", status: "active" },
        owner: { public_id: "user-1", name: "David Oliver" },
        tags: [{ public_id: "tag-1", name: "Safety" }],
        capabilities: { edit: false },
        edit_options: null,
      },
      history: {
        data: [{
          public_id: "document-1", family_public_id: "family-1", source_filename: "medication.pdf",
          publisher_label: "Alderbridge", source_url: null, media_type: "application/pdf", size_bytes: 1024,
          status: "indexed", governance_status: "approved", predecessor_public_id: null,
          effective_from: "2026-01-01T00:00:00Z", approved_at: "2026-01-02T00:00:00Z", withdrawn_at: null,
          is_current_authority: true, extraction_warning_count: 0,
          applicability: { scope: "universal", locations: [] },
          capabilities: { approve: false, withdraw: false, reschedule: false, create_applicability_successor: false, correct_timestamps: false },
        }],
        meta: { current_version_public_id: "document-1", locations: [] },
      },
    });
  });

  it("renders the scaffold only for an authorised family in the workspace", async () => {
    render(
      await DocumentFamilyPage({
        params: Promise.resolve({
          familyPublicId: "family-1",
          workspacePublicId: "workspace-1",
        }),
      }),
    );

    expect(screen.getByText("Medication procedure")).not.toBeNull();
    expect(screen.getByRole("heading", { level: 1, name: "Medication procedure" })).not.toBeNull();
    expect(screen.getByText("Current authority")).not.toBeNull();
    expect(screen.getByText("Version 1")).not.toBeNull();
    expect(familyDetailMock).toHaveBeenCalledWith("workspace-1", "family-1");
  });

  it.each(["missing", "deleted", "cross-workspace"])(
    "conceals a %s family identifier",
    async () => {
      familyDetailMock.mockResolvedValue(null);

      await expect(
        DocumentFamilyPage({
          params: Promise.resolve({
            familyPublicId: "concealed",
            workspacePublicId: "workspace-1",
          }),
        }),
      ).rejects.toThrow("NEXT_NOT_FOUND");
      expect(notFoundMock).toHaveBeenCalledOnce();
    },
  );
});
