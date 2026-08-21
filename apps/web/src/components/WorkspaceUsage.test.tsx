import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { WorkspaceUsage } from "@/components/WorkspaceUsage";
import type { WorkspaceUsageSnapshot } from "@/lib/api";

afterEach(cleanup);

const snapshot: WorkspaceUsageSnapshot = {
  range: { key: "30d", start: "2026-07-20T10:00:00Z", end: "2026-08-19T10:00:00Z", semantics: "[start,end) UTC" },
  as_of: "2026-08-19T10:00:00Z",
  gauges: { active_documents: 12, logical_source_bytes: 4096, indexed_chunks: 34 },
  historical: {
    ingestion_failures: 2,
    activity: [{ event_kind: "run_outcome", outcome: "completed", aggregate_count: 7 }],
    usage: [{
      operation_kind: "generation",
      provider: "openai",
      model: "gpt-5-mini",
      cost_basis: "estimated",
      pricing_snapshot: "openai-pricing-v1",
      request_count: 7,
      retry_count: 1,
      input_tokens: 1000,
      cached_input_tokens: 100,
      output_tokens: 200,
      latency_ms: 500,
      cost_usd: "0.00125",
      observation_count: 7,
    }],
  },
  labels: {
    logical_source_bytes: "Logical uploaded source bytes; not physical storage or billing usage.",
    cost: "Estimated cost is not billing-grade.",
  },
};

describe("WorkspaceUsage", () => {
  it("separates gauges from bounded historical usage and labels estimates", () => {
    render(<WorkspaceUsage initialSnapshot={snapshot} workspaceId="workspace" />);
    expect(screen.getByText("Reporting period")).toBeTruthy();
    expect(screen.getByText("Workspace activity")).toBeTruthy();
    expect(screen.getByText("12")).toBeTruthy();
    expect(screen.getAllByText(/estimated/).length).toBeGreaterThan(0);
    expect(screen.getByText(/not billing-grade/)).toBeTruthy();
  });
});
