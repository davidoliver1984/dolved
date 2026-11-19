import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/server", () => ({ connection: vi.fn().mockResolvedValue(undefined) }));

import Home from "@/app/page";

describe("Home", () => {
  beforeEach(() => {
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "true");
  });

  afterEach(() => {
    cleanup();
    vi.unstubAllEnvs();
  });

  it("offers account creation when registration is enabled", async () => {
    render(await Home());

    expect(screen.getByRole("link", { name: "Get started" })).toBeTruthy();
    expect(screen.getByRole("link", { name: "Build your workspace" })).toBeTruthy();
  });

  it("offers only sign-in when registration is disabled", async () => {
    vi.stubEnv("AUTH_REGISTRATION_ENABLED", "false");
    render(await Home());

    expect(screen.queryByRole("link", { name: "Get started" })).toBeNull();
    expect(screen.queryByRole("link", { name: "Build your workspace" })).toBeNull();
    expect(screen.getByRole("link", { name: "Sign in to Dolved" })).toBeTruthy();
  });
});
