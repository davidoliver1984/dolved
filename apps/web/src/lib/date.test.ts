import { describe, expect, it } from "vitest";
import { formatDate, formatDateTime } from "@/lib/date";

describe("deterministic date formatting", () => {
  it("uses UTC regardless of the host timezone", () => {
    expect(formatDate("2026-08-21T23:30:00-04:00")).toBe("22/08/2026");
    expect(formatDateTime("2026-08-21T18:59:35Z")).toBe("21/08/2026, 18:59:35 UTC");
  });
});
