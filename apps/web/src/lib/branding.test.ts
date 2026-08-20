import { readFileSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const brandedSurfaces = [
  "src/app/layout.tsx",
  "src/app/page.tsx",
  "src/app/app/layout.tsx",
  "src/components/AuthForm.tsx",
];

const repositoryRoot = process.env.REPOSITORY_ROOT ?? resolve(process.cwd(), "../..");
const legacyPattern = /Make\s*Time|maketime(?:\.ai|\.)/i;

function isImmutableLegacyIdentity(path: string, line: string): boolean {
  if (path.startsWith("contracts/") && line.includes('"$id": "https://maketime.ai/contracts/')) return true;
  if (path === "apps/ai/app/vector_store/identity.py" && line.includes("https://maketime.ai/vector-point/v1")) return true;
  if (["tests/evaluation/corpus/v1/corpus.json", "tests/evaluation/corpus/v2/corpus.json"].includes(path) && line.includes('"title": "MakeTime retrieval foundation corpus"')) return true;
  if ([
    "docs/adr/0005-use-sanctum-and-fortify-for-first-party-spa-authentication.md",
    "docs/adr/0006-use-workspace-as-the-tenancy-and-isolation-boundary.md",
    "docs/journal/2026-08-18-r18-phase-closure.md",
  ].includes(path)) return true;
  return false;
}

describe("product identity", () => {
  it("uses Dolved across every shared web shell", () => {
    for (const surface of brandedSurfaces) {
      const source = readFileSync(resolve(process.cwd(), surface), "utf8");
      expect(source).toContain("Dolved");
      expect(source).not.toMatch(/Make\s+Time/i);
    }
  });

  it("rejects legacy product branding across tracked active repository content", () => {
    const tracked = execFileSync("git", ["ls-files", "-z"], {
      cwd: repositoryRoot,
      encoding: "utf8",
    }).split("\0").filter(Boolean);
    const unexpected: string[] = [];
    for (const path of tracked) {
      if (path === "apps/web/src/lib/branding.test.ts") continue;
      let source: string;
      try {
        source = readFileSync(resolve(repositoryRoot, path), "utf8");
      } catch {
        continue;
      }
      source.split("\n").forEach((line, index) => {
        if (legacyPattern.test(line) && !isImmutableLegacyIdentity(path, line)) {
          unexpected.push(`${path}:${index + 1}`);
        }
      });
    }
    expect(unexpected).toEqual([]);
  }, 30000);
});
