import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

const repositoryRoot = process.env.REPOSITORY_ROOT ?? join(process.cwd(), "../..");
const rulesPath = join(repositoryRoot, "infrastructure/observability/rules/dolved-alerts.yaml");
const policyPath = join(repositoryRoot, "infrastructure/observability/slo-policy.json");
const runbookPath = join(repositoryRoot, "docs/operations/alert-runbooks.md");

describe("operational alert contract", () => {
  it("gives every alert bounded ownership, impact, response and runbook metadata", () => {
    const rules = readFileSync(rulesPath, "utf8");
    const runbooks = readFileSync(runbookPath, "utf8");
    const blocks = rules.split(/\n\s+- alert: /).slice(1);

    expect(blocks.length).toBeGreaterThanOrEqual(10);
    for (const block of blocks) {
      const name = block.split("\n", 1)[0].trim();
      expect(block).toMatch(/severity: (warning|urgent)/);
      expect(block).toContain("owner: platform-operator");
      expect(block).toContain("impact:");
      expect(block).toContain("response:");
      expect(block).toContain("runbook_url:");
      expect(runbooks).toContain(`\`${name}\``);
    }
  });

  it("preserves ADR-0024 terminal-outcome semantics and does not claim calibrated latency", () => {
    const rules = readFileSync(rulesPath, "utf8");
    const policy = JSON.parse(readFileSync(policyPath, "utf8")) as {
      status: string;
      objectives: Array<{ id: string; numerator: string; denominator: string; excluded?: string[] }>;
      latency_objectives: { status: string };
    };
    const conversation = policy.objectives.find((item) => item.id === "conversation_technical_success");

    expect(policy.status).toBe("PROVISIONAL_UNMEASURED");
    expect(policy.latency_objectives.status).toBe("CALIBRATION_REQUIRED");
    expect(conversation?.numerator).toContain("retrieval_no_answer");
    expect(conversation?.numerator).toContain("clarification_required");
    expect(conversation?.excluded).toEqual(["cancelled"]);
    expect(rules).toContain('rag_operation_outcome=~"completed|retrieval_no_answer|clarification_required|failed"');
    expect(rules).not.toContain('rag_operation_outcome="cancelled"');
  });
});
