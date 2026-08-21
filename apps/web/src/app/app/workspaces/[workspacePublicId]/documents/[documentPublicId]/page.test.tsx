import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { initialWorkspaceDocumentMock, notFoundMock, userWorkspaceMock } = vi.hoisted(() => ({
  initialWorkspaceDocumentMock: vi.fn(),
  notFoundMock: vi.fn(() => {
    throw new Error("NEXT_NOT_FOUND");
  }),
  userWorkspaceMock: vi.fn(),
}));

vi.mock("next/navigation", () => ({ notFound: notFoundMock }));
vi.mock("@/lib/server-api", () => ({
  initialWorkspaceDocument: initialWorkspaceDocumentMock,
  userWorkspace: userWorkspaceMock,
}));

import DocumentSourcePage from "./page";

const workspace = {
  public_id: "workspace-1",
  name: "Alderbridge",
  slug: "alderbridge",
  role: "owner",
};
const document = {
  public_id: "document-1",
  source_filename: "Medication procedure.pdf",
  media_type: "application/pdf",
  size_bytes: 2048,
  status: "indexed",
  governance_status: "approved",
  failure_category: null,
  failure_message: null,
  extraction_warnings: [],
  capabilities: { retry: false, delete: true },
  created_at: "2026-08-21T10:00:00Z",
};

describe("document source route", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    userWorkspaceMock.mockResolvedValue(workspace);
    initialWorkspaceDocumentMock.mockResolvedValue(document);
  });

  it("renders safe source metadata for an authorised live document", async () => {
    render(await DocumentSourcePage({ params: Promise.resolve({ workspacePublicId: "workspace-1", documentPublicId: "document-1" }) }));
    expect(screen.getByRole("heading", { name: "Medication procedure.pdf", level: 1 })).not.toBeNull();
    expect(screen.getByText("application/pdf · 2.0 KB")).not.toBeNull();
    expect(screen.queryByText(/storage/i)).toBeNull();
  });

  it.each(["deleted", "inaccessible", "cross-workspace"])(
    "uses tenant-safe not found for a %s source",
    async () => {
      initialWorkspaceDocumentMock.mockResolvedValue(null);
      await expect(DocumentSourcePage({ params: Promise.resolve({ workspacePublicId: "workspace-1", documentPublicId: "concealed" }) })).rejects.toThrow("NEXT_NOT_FOUND");
      expect(notFoundMock).toHaveBeenCalledOnce();
    },
  );
});
