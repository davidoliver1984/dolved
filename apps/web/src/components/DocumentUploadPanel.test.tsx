import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { DocumentUploadPanel } from "@/components/DocumentUploadPanel";
import type {
  Document,
  DocumentUploadConfiguration,
  InitialisedDocumentUpload,
  PresignedUpload,
} from "@/lib/document-upload";

const configuration: DocumentUploadConfiguration = {
  formats: {
    pdf: ["application/pdf"],
    txt: ["text/plain"],
  },
  max_upload_bytes: 25 * 1024 * 1024,
  upload_concurrency: 3,
};

const upload: PresignedUpload = {
  url: "http://localhost:4566/bucket/object?signature=test",
  method: "PUT",
  headers: { "Content-Type": "application/pdf" },
  expires_at: "2026-07-28T13:00:00+01:00",
};

afterEach(cleanup);

function documentFor(file: File, status: "uploading" | "uploaded"): Document {
  return {
    public_id: `${file.name}-public-id`,
    source_filename: file.name,
    media_type: file.type,
    size_bytes: file.size,
    status,
    created_at: "2026-07-28T12:00:00+01:00",
    updated_at: "2026-07-28T12:00:00+01:00",
  };
}

function initialised(file: File): InitialisedDocumentUpload {
  return {
    document: documentFor(file, "uploading"),
    upload,
  };
}

describe("DocumentUploadPanel", () => {
  it("queues multiple valid files and reports invalid selections", () => {
    render(
      <DocumentUploadPanel
        configuration={configuration}
        workspacePublicId="workspace-id"
      />,
    );

    const input = screen.getByLabelText(/choose files/i);
    const validPdf = new File(["pdf"], "guide.pdf", {
      type: "application/pdf",
    });
    const validText = new File(["text"], "notes.txt", {
      type: "text/plain",
    });
    const invalid = new File(["csv"], "budget.csv", { type: "text/csv" });

    fireEvent.change(input, {
      target: { files: [validPdf, validText, invalid, validPdf] },
    });

    expect(screen.getByText("guide.pdf")).toBeTruthy();
    expect(screen.getByText("notes.txt")).toBeTruthy();
    expect(screen.getByRole("alert").textContent).toMatch(/not supported/i);
    expect(screen.getByRole("alert").textContent).toMatch(/already in the queue/i);
    expect(
      screen.getByRole("button", { name: /upload 2 files/i }),
    ).toBeTruthy();
  });

  it("shows byte progress then verifying and completes only after API confirmation", async () => {
    const user = userEvent.setup();
    const file = new File(["document"], "guide.pdf", {
      type: "application/pdf",
    });
    const initialise = vi.fn(async () => initialised(file));
    let confirmCompletion: ((document: Document) => void) | undefined;
    const complete = vi.fn(
      () =>
        new Promise<Document>((resolve) => {
          confirmCompletion = resolve;
        }),
    );
    const transport = vi.fn(
      async (
        _file: File,
        _upload: PresignedUpload,
        onProgress: (percentage: number) => void,
      ) => {
        onProgress(45);
        onProgress(100);
      },
    );

    render(
      <DocumentUploadPanel
        complete={complete}
        configuration={configuration}
        initialise={initialise}
        transport={transport}
        workspacePublicId="workspace-id"
      />,
    );

    fireEvent.change(screen.getByLabelText(/choose files/i), {
      target: { files: [file] },
    });
    await user.click(screen.getByRole("button", { name: /upload 1 file/i }));

    await waitFor(() =>
      expect(screen.getByText(/Laravel is verifying/i)).toBeTruthy(),
    );
    expect(screen.queryByText(/^Complete ·/i)).toBeNull();

    confirmCompletion?.(documentFor(file, "uploaded"));

    await waitFor(() =>
      expect(screen.getByText("Complete · 100%")).toBeTruthy(),
    );
    expect(complete).toHaveBeenCalledWith(
      "workspace-id",
      "guide.pdf-public-id",
    );
  });

  it("keeps failures independent and retries only the failed file", async () => {
    const user = userEvent.setup();
    const failedFile = new File(["first"], "first.pdf", {
      type: "application/pdf",
    });
    const successfulFile = new File(["second"], "second.pdf", {
      type: "application/pdf",
    });
    const initialise = vi.fn(
      async (_workspacePublicId: string, file: File) => initialised(file),
    );
    let firstAttempts = 0;
    const transport = vi.fn(
      async (
        file: File,
        _upload: PresignedUpload,
        onProgress: (percentage: number) => void,
      ) => {
        if (file.name === "first.pdf" && firstAttempts === 0) {
          firstAttempts += 1;
          throw new Error("Network interrupted this file.");
        }

        onProgress(100);
      },
    );
    const complete = vi.fn(
      async (_workspacePublicId: string, publicId: string) =>
        documentFor(
          publicId.startsWith("first") ? failedFile : successfulFile,
          "uploaded",
        ),
    );

    render(
      <DocumentUploadPanel
        complete={complete}
        configuration={configuration}
        initialise={initialise}
        transport={transport}
        workspacePublicId="workspace-id"
      />,
    );

    fireEvent.change(screen.getByLabelText(/choose files/i), {
      target: { files: [failedFile, successfulFile] },
    });
    await user.click(screen.getByRole("button", { name: /upload 2 files/i }));

    await waitFor(() =>
      expect(screen.getByText("Network interrupted this file.")).toBeTruthy(),
    );
    expect(screen.getByText("Complete · 100%")).toBeTruthy();

    await user.click(screen.getByRole("button", { name: "Retry" }));

    await waitFor(() =>
      expect(screen.getAllByText("Complete · 100%")).toHaveLength(2),
    );
    expect(initialise).toHaveBeenCalledTimes(3);
  });
});
