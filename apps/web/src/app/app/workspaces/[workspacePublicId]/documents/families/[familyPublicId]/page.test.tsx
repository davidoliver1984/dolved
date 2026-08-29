import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { familyMetadataMock, notFoundMock, userWorkspaceMock } = vi.hoisted(() => ({
  familyMetadataMock: vi.fn(),
  notFoundMock: vi.fn(() => {
    throw new Error("NEXT_NOT_FOUND");
  }),
  userWorkspaceMock: vi.fn(),
}));

vi.mock("next/navigation", () => ({ notFound: notFoundMock }));
vi.mock("@/lib/server-api", () => ({
  initialDocumentFamilyMetadata: familyMetadataMock,
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
    familyMetadataMock.mockResolvedValue({
      public_id: "family-1",
      name: "Medication procedure",
      description: null,
      review_due_date: null,
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
    expect(screen.getByRole("heading", { level: 1, name: "Family details" })).not.toBeNull();
    expect(familyMetadataMock).toHaveBeenCalledWith("workspace-1", "family-1");
  });

  it.each(["missing", "deleted", "cross-workspace"])(
    "conceals a %s family identifier",
    async () => {
      familyMetadataMock.mockResolvedValue(null);

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
