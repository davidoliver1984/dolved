import { afterEach, describe, expect, it, vi } from "vitest";
import {
  type DocumentUploadConfiguration,
  runWithConcurrency,
  type PresignedUpload,
  uploadToPresignedUrl,
  validateDocumentFile,
} from "@/lib/document-upload";

const configuration: DocumentUploadConfiguration = {
  formats: {
    pdf: ["application/pdf"],
    txt: ["text/plain"],
  },
  max_upload_bytes: 25 * 1024 * 1024,
  upload_concurrency: 3,
};

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("document upload helpers", () => {
  it("validates extension, declared type, empty files and size", () => {
    expect(
      validateDocumentFile(
        new File(["content"], "notes.txt", { type: "text/plain" }),
        configuration,
      ),
    ).toBeNull();
    expect(
      validateDocumentFile(
        new File(["content"], "notes.csv", { type: "text/csv" }),
        configuration,
      ),
    ).toMatch(/not supported/i);
    expect(
      validateDocumentFile(
        new File(["content"], "notes.pdf", { type: "text/plain" }),
        configuration,
      ),
    ).toMatch(/does not match/i);
    expect(
      validateDocumentFile(
        new File([], "empty.txt", { type: "text/plain" }),
        configuration,
      ),
    ).toMatch(/empty/i);
    expect(
      validateDocumentFile(
        new File(
          [new Uint8Array(configuration.max_upload_bytes + 1)],
          "large.pdf",
          { type: "application/pdf" },
        ),
        configuration,
      ),
    ).toMatch(/25 MB/i);
  });

  it("never exceeds the configured concurrency", async () => {
    let active = 0;
    let maximumActive = 0;
    const releases: Array<() => void> = [];

    const run = runWithConcurrency(
      [1, 2, 3, 4, 5, 6, 7],
      3,
      async () => {
        active += 1;
        maximumActive = Math.max(maximumActive, active);
        await new Promise<void>((resolve) => releases.push(resolve));
        active -= 1;
      },
    );

    await Promise.resolve();
    expect(maximumActive).toBe(3);

    while (releases.length > 0) {
      releases.shift()?.();
      await Promise.resolve();
    }

    await run;
    expect(maximumActive).toBe(3);
  });

  it("reports actual XMLHttpRequest byte progress and required headers", async () => {
    const progressListeners: Array<(event: ProgressEvent) => void> = [];
    const requestListeners = new Map<string, () => void>();
    const open = vi.fn();
    const setRequestHeader = vi.fn();
    const send = vi.fn(() => {
      progressListeners[0](
        new ProgressEvent("progress", {
          lengthComputable: true,
          loaded: 5,
          total: 10,
        }),
      );
      requestListeners.get("load")?.();
    });

    class FakeXMLHttpRequest {
      status = 200;
      upload = {
        addEventListener: (
          _event: string,
          listener: (event: ProgressEvent) => void,
        ) => progressListeners.push(listener),
      };
      open = open;
      setRequestHeader = setRequestHeader;
      send = send;

      addEventListener(event: string, listener: () => void) {
        requestListeners.set(event, listener);
      }
    }

    vi.stubGlobal("XMLHttpRequest", FakeXMLHttpRequest);

    const file = new File(["document"], "guide.pdf", {
      type: "application/pdf",
    });
    const upload: PresignedUpload = {
      url: "http://localhost:4566/bucket/object?signature=test",
      method: "PUT",
      headers: { "Content-Type": "application/pdf" },
      expires_at: "2026-07-28T13:00:00+01:00",
    };
    const progress: number[] = [];

    await uploadToPresignedUrl(file, upload, (percentage) => {
      progress.push(percentage);
    });

    expect(open).toHaveBeenCalledWith("PUT", upload.url);
    expect(setRequestHeader).toHaveBeenCalledWith(
      "Content-Type",
      "application/pdf",
    );
    expect(send).toHaveBeenCalledWith(file);
    expect(progress).toEqual([50, 100]);
  });
});
