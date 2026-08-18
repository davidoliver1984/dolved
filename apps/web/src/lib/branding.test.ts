import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const brandedSurfaces = [
  "src/app/layout.tsx",
  "src/app/page.tsx",
  "src/app/app/layout.tsx",
  "src/components/AuthForm.tsx",
];

describe("product identity", () => {
  it("uses Dolved across every shared web shell", () => {
    for (const surface of brandedSurfaces) {
      const source = readFileSync(resolve(process.cwd(), surface), "utf8");
      expect(source).toContain("Dolved");
      expect(source).not.toMatch(/Make\s+Time/i);
    }
  });
});
