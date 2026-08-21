import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const { createPolicy, refresh } = vi.hoisted(() => ({ createPolicy: vi.fn(), refresh: vi.fn() }));
vi.mock("@/lib/api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api")>()),
  createOperationalPolicy: createPolicy,
}));
vi.mock("next/navigation", () => ({ useRouter: () => ({ refresh }) }));

import { OperationalPolicyPanel } from "@/components/OperationalPolicyPanel";

describe("OperationalPolicyPanel", () => {
  beforeEach(() => { createPolicy.mockReset(); refresh.mockReset(); });
  afterEach(cleanup);

  it("shows per-setting and per-target state without collapsing partial application", () => {
    render(<OperationalPolicyPanel policy={{
      public_id: "policy-1", environment: "local", version: 3,
      manifest_version: "observability-required-targets-v1", manifest_digest: "a".repeat(64),
      active_settings: 1, total_settings: 2, fully_active: false,
      settings: [{ setting_key: "trace_sampling_percentage", desired_value: 10, status: "ACTIVE", targets: [{
        target: "collector", plan_id: "plan-1", expected_digest: "b".repeat(64), current_attempt_id: "attempt-1", status: "ACTIVE", reconciled_at: "2026-08-20T12:00:00Z",
      }] }, { setting_key: "trace_retention_days", desired_value: 14, status: "PENDING", targets: [{
        target: "tempo", plan_id: "plan-2", expected_digest: "c".repeat(64), current_attempt_id: null, status: "PENDING", reconciled_at: null,
      }] }],
    }} />);

    expect(screen.getByText(/1 of 2 settings active/)).toBeTruthy();
    expect(screen.getByText(/collector: ACTIVE/)).toBeTruthy();
    expect(screen.getByText(/tempo: PENDING/)).toBeTruthy();
  });

  it("labels a saved value as desired until authenticated reconciliation", async () => {
    createPolicy.mockResolvedValue({ status: "ok", data: { policy: {} } });
    render(<OperationalPolicyPanel policy={null} />);
    fireEvent.submit(screen.getByRole("button", { name: "Save desired policy" }).closest("form")!);

    await waitFor(() => expect(createPolicy).toHaveBeenCalledOnce());
    expect(screen.getByText(/remains pending/)).toBeTruthy();
  });

  it("refreshes without a retry message when authority is concealed during save", async () => {
    createPolicy.mockResolvedValue({ status: "concealed" });
    render(<OperationalPolicyPanel policy={null} />);
    fireEvent.submit(screen.getByRole("button", { name: "Save desired policy" }).closest("form")!);

    await waitFor(() => expect(refresh).toHaveBeenCalledOnce());
    expect(screen.queryByText(/failed|forbidden|permission/i)).toBeNull();
    expect(createPolicy).toHaveBeenCalledOnce();
  });
});
