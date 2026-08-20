import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("server-only", () => ({}));

import { structuredLog } from "@/lib/structured-log";

describe("structuredLog", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it("writes only allowlisted structured fields", () => {
    const write = vi.spyOn(process.stderr, "write").mockReturnValue(true);

    structuredLog("document.ingestion.claimed.v1", "info", {
      correlation_id: "correlation-1",
      document_id: "document-1",
      prompt: "private prompt",
      answer: "private answer",
    });

    const encoded = String(write.mock.calls[0]?.[0]);
    const payload = JSON.parse(encoded);
    expect(payload.event_name).toBe("document.ingestion.claimed.v1");
    expect(payload.correlation_id).toBe("correlation-1");
    expect(payload.document_id).toBe("document-1");
    expect(payload.prompt).toBeUndefined();
    expect(payload.answer).toBeUndefined();
    expect(encoded).not.toContain("private");
  });

  it("omits exception messages while retaining safe diagnostics", () => {
    const write = vi.spyOn(process.stderr, "write").mockReturnValue(true);
    const error = new Error("private evidence value");

    structuredLog("generation.failed.v1", "error", {}, error);

    const encoded = String(write.mock.calls[0]?.[0]);
    const payload = JSON.parse(encoded);
    expect(payload.exception_type).toBe("Error");
    expect(encoded).not.toContain("private evidence value");
  });

  it("isolates output failures", () => {
    vi.spyOn(process.stderr, "write").mockImplementation(() => {
      throw new Error("private stream failure");
    });

    expect(() => structuredLog("logging.test.v1", "info")).not.toThrow();
  });
});
