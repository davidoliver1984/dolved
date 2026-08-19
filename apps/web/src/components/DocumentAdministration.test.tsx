import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { DocumentAdministration } from "@/components/DocumentAdministration";
import type { AdminDocument } from "@/lib/api";

afterEach(cleanup);

const document: AdminDocument = {
  public_id: "11111111-1111-4111-8111-111111111111",
  source_filename: "Medication procedure.pdf",
  media_type: "application/pdf",
  size_bytes: 2048,
  status: "failed",
  governance_status: "draft",
  failure_category: "extraction_failed",
  failure_message: "The file could not be extracted.",
  extraction_warnings: [
    { code: "images_not_extracted", message: "Images were not extracted." },
  ],
  created_by: { name: "Workspace Owner" },
  deletion: null,
  capabilities: { retry: true, delete: true },
  created_at: "2026-08-19T10:00:00Z",
  updated_at: "2026-08-19T10:01:00Z",
};

const page = {
  data: [document],
  meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
};

describe("DocumentAdministration", () => {
  it("renders authoritative lifecycle, safe diagnostics and permitted actions", () => {
    render(
      <DocumentAdministration
        initialPage={page}
        workspacePublicId="workspace-id"
      />,
    );

    expect(screen.getByText("Medication procedure.pdf")).toBeTruthy();
    expect(screen.getAllByText("failed")).toHaveLength(2);
    expect(screen.getByText(/The file could not be extracted/)).toBeTruthy();
    expect(screen.getByText(/1 extraction warning/)).toBeTruthy();
    expect(screen.getByRole("button", { name: "Retry ingestion" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "Delete document" })).toBeTruthy();
  });

  it("does not render mutation controls when server capabilities deny them", () => {
    render(
      <DocumentAdministration
        initialPage={{
          ...page,
          data: [{ ...document, capabilities: { retry: false, delete: false } }],
        }}
        workspacePublicId="workspace-id"
      />,
    );

    expect(screen.queryByRole("button", { name: "Retry ingestion" })).toBeNull();
    expect(screen.queryByRole("button", { name: "Delete document" })).toBeNull();
  });
});
