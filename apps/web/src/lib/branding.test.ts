import { readFileSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const properNameSurfaces = [
  "src/app/layout.tsx",
];
const sharedBrandingConsumers = [
  "src/app/page.tsx",
  "src/components/AuthForm.tsx",
  "src/components/InvitationAcceptance.tsx",
];
const wordmarkSurface = "src/components/Wordmark.tsx";

const repositoryRoot = process.env.REPOSITORY_ROOT ?? resolve(process.cwd(), "../..");
const legacyPattern = /Make\s*Time|maketime(?:\.ai|\.)/i;

function isImmutableLegacyIdentity(path: string, line: string): boolean {
  if (path.startsWith("contracts/") && line.includes('"$id": "https://maketime.ai/contracts/')) return true;
  if (path === "apps/ai/app/vector_store/identity.py" && line.includes("https://maketime.ai/vector-point/v1")) return true;
  if (["tests/evaluation/corpus/v1/corpus.json", "tests/evaluation/corpus/v2/corpus.json"].includes(path) && line.includes('"title": "MakeTime retrieval foundation corpus"')) return true;
  if ([
    "docs/adr/0005-use-sanctum-and-fortify-for-first-party-spa-authentication.md",
    "docs/adr/0006-use-workspace-as-the-tenancy-and-isolation-boundary.md",
    "docs/adr/0027-define-the-product-interface-and-design-system.md",
    "docs/journal/2026-08-18-r18-phase-closure.md",
    "docs/evaluation/r28-s01/r28-s02-population-binding.md",
    "docs/evaluation/runs/R28-S02-LIVE-RETRIEVAL-BASELINE-0001/execution-policy.json",
    "tests/evaluation/policies/v1/r28-s02-live-retrieval-policy.json",
  ].includes(path)) return true;
  return false;
}

describe("product identity", () => {
  it("preserves the Dolved proper name and lowercase wordmark independently", () => {
    for (const surface of properNameSurfaces) {
      const source = readFileSync(resolve(process.cwd(), surface), "utf8");
      expect(source).toContain("Dolved");
      expect(source).not.toMatch(/Make\s+Time/i);
    }
    for (const surface of sharedBrandingConsumers) {
      const source = readFileSync(resolve(process.cwd(), surface), "utf8");
      expect(source).toContain("<Wordmark");
      expect(source).not.toMatch(/Make\s+Time/i);
    }
    const wordmark = readFileSync(resolve(process.cwd(), wordmarkSurface), "utf8");
    expect(wordmark).toContain("dolved");
    expect(wordmark).not.toContain("dolved.");

    const layout = readFileSync(resolve(process.cwd(), "src/app/layout.tsx"), "utf8");
    const icon = readFileSync(resolve(process.cwd(), "public/dolved-mark.svg"), "utf8");
    expect(layout).toContain('/dolved-mark.svg');
    expect(icon).toContain('#006c4d');
    expect(icon).toContain('aria-label="Dolved"');
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
