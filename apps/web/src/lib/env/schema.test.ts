import { describe, expect, it } from "vitest";
import {
  parseClientEnvironment,
  parseServerEnvironment,
} from "@/lib/env/schema";

describe("environment validation", () => {
  it("normalises valid URLs", () => {
    expect(
      parseClientEnvironment({
        NEXT_PUBLIC_API_URL: "https://api.maketime.ai/",
      }),
    ).toEqual({ NEXT_PUBLIC_API_URL: "https://api.maketime.ai" });
    expect(
      parseServerEnvironment({
        API_INTERNAL_URL: "http://api:8000/",
        FRONTEND_URL: "https://app.maketime.ai/",
      }),
    ).toEqual({
      API_INTERNAL_URL: "http://api:8000",
      FRONTEND_URL: "https://app.maketime.ai",
    });
  });

  it("uses the established local defaults when values are missing", () => {
    expect(parseClientEnvironment({})).toEqual({
      NEXT_PUBLIC_API_URL: "http://localhost:8000",
    });
    expect(parseServerEnvironment({})).toEqual({
      API_INTERNAL_URL: "http://localhost:8000",
      FRONTEND_URL: "http://localhost:3000",
    });
  });

  it("rejects malformed URLs", () => {
    expect(() =>
      parseClientEnvironment({ NEXT_PUBLIC_API_URL: "not-a-url" }),
    ).toThrow();
    expect(() =>
      parseServerEnvironment({
        API_INTERNAL_URL: "api.internal",
        FRONTEND_URL: "also-not-a-url",
      }),
    ).toThrow();
    expect(() =>
      parseServerEnvironment({ API_INTERNAL_URL: "ftp://api.internal" }),
    ).toThrow();
  });
});
