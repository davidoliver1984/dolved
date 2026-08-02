import { beforeEach, describe, expect, it, vi } from "vitest";

const { cookiesMock } = vi.hoisted(() => ({
  cookiesMock: vi.fn(),
}));

vi.mock("server-only", () => ({}));
vi.mock("next/headers", () => ({ cookies: cookiesMock }));

import {
  allowlistedAuthCookieHeader,
  forwardedAuthCookieHeader,
} from "@/lib/auth-cookies";

describe("Laravel authentication cookie forwarding", () => {
  beforeEach(() => {
    cookiesMock.mockReset();
  });

  it("forwards only the Laravel session and XSRF cookies", () => {
    const values = new Map([
      ["rag-platform-session", { value: "session-value" }],
      ["XSRF-TOKEN", { value: "xsrf-value" }],
      ["theme", { value: "dark" }],
      ["feature-preview", { value: "enabled" }],
    ]);

    expect(
      allowlistedAuthCookieHeader({
        get: (name) => values.get(name),
      }),
    ).toBe("rag-platform-session=session-value; XSRF-TOKEN=xsrf-value");
  });

  it("omits missing cookies without forwarding unrelated values", async () => {
    cookiesMock.mockResolvedValue({
      get: (name: string) =>
        name === "theme" ? { value: "dark" } : undefined,
    });

    await expect(forwardedAuthCookieHeader()).resolves.toBe("");
  });
});
