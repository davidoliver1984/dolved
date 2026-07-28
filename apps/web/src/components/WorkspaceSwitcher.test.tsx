import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { WorkspaceSwitcher } from "@/components/WorkspaceSwitcher";
import type { Workspace } from "@/lib/api";

const workspaces: Workspace[] = [
  {
    public_id: "11111111-1111-4111-8111-111111111111",
    name: "Atlas Research",
    slug: "atlas-research",
    role: "owner",
  },
  {
    public_id: "22222222-2222-4222-8222-222222222222",
    name: "Beacon Operations",
    slug: "beacon-operations",
    role: "admin",
  },
];

describe("WorkspaceSwitcher", () => {
  it("marks the active workspace and links to each assigned workspace", () => {
    render(
      <WorkspaceSwitcher
        activeWorkspace={workspaces[0]}
        workspaces={workspaces}
      />,
    );

    expect(
      screen
        .getByRole("link", { name: /Atlas Research, role owner/i })
        .getAttribute("aria-current"),
    ).toBe("page");
    expect(
      screen
        .getByRole("link", { name: /Beacon Operations, role admin/i })
        .getAttribute("href"),
    ).toBe(
      "/app/workspaces/22222222-2222-4222-8222-222222222222",
    );
  });
});
