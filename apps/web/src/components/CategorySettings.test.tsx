import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it } from "vitest";
import { CategorySettings } from "@/components/CategorySettings";

afterEach(cleanup);

const categories = [
  { public_id: "11111111-1111-4111-8111-111111111111", name: "Clinical", status: "active" as const },
  { public_id: "22222222-2222-4222-8222-222222222222", name: "Legacy", status: "archived" as const },
];

describe("CategorySettings", () => {
  it("separates assignable categories and uses an inline rename action", async () => {
    const user = userEvent.setup();
    render(<CategorySettings canManage initialCategories={categories} workspacePublicId="workspace-1" />);

    expect(screen.getByRole("heading", { name: "Active categories" })).toBeTruthy();
    expect(screen.getByRole("heading", { name: "Archived categories" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "Archive Clinical" })).toBeTruthy();
    await user.click(screen.getByRole("button", { name: "Rename Clinical" }));
    expect(screen.getByRole("textbox", { name: "New name for Clinical" }).getAttribute("value")).toBe("Clinical");
    expect(screen.getByRole("button", { name: "Save name for Clinical" })).toBeTruthy();
    expect(screen.getByText("Legacy")).toBeTruthy();
  });

  it("keeps the catalogue visible without mutation controls for members", () => {
    render(<CategorySettings canManage={false} initialCategories={categories} workspacePublicId="workspace-1" />);

    expect(screen.getByText(/Only workspace owners and administrators/)).toBeTruthy();
    expect(screen.queryByRole("button", { name: "Archive Clinical" })).toBeNull();
  });
});
