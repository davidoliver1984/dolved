import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("design-system reference route boundary", () => {
  it("fails through not-found in production instead of becoming a public surface", () => {
    const source = readFileSync(resolve(process.cwd(), "src/app/design-system/page.tsx"), "utf8");
    expect(source).toContain('process.env.NODE_ENV === "production"');
    expect(source).toContain("notFound()");
  });
});
