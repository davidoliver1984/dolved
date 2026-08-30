import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { SavedViewsPanel } from "@/components/SavedViewsPanel";

afterEach(cleanup);

describe("SavedViewsPanel", () => {
  it("explains live evaluation and exposes owner controls", () => {
    render(<SavedViewsPanel currentDefinition={{ search: "policy" }} initialViews={[{
      public_id: "11111111-1111-4111-8111-111111111111",
      name: "Current policies",
      definition_schema_version: 1,
      definition: { search: "policy" },
      notices: [],
      created_at: null,
      updated_at: null,
    }]} workspacePublicId="workspace-1" />);

    expect(screen.getByText(/always refreshed from the live library/i)).toBeTruthy();
    expect(screen.getByRole("link", { name: "Current policies" }).getAttribute("href")).toContain("/documents/saved/");
    expect(screen.getByRole("button", { name: "Delete Current policies" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "Save current view" })).toBeTruthy();
  });
});
