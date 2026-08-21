"use client";

import { FileUp, RefreshCw, Trash2, UploadCloud } from "lucide-react";
import {
  type ChangeEvent,
  type DragEvent,
  useCallback,
  useMemo,
  useState,
} from "react";
import {
  completeDocumentUpload,
  type Document,
  type DocumentUploadConfiguration,
  initialiseDocumentUpload,
  type InitialisedDocumentUpload,
  runWithConcurrency,
  uploadToPresignedUrl,
  validateDocumentFile,
} from "@/lib/document-upload";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";

type UploadState =
  | "waiting"
  | "initialising"
  | "uploading"
  | "verifying"
  | "complete"
  | "failed";

type UploadItem = {
  id: string;
  file: File;
  state: UploadState;
  progress: number;
  error: string | null;
  document: Document | null;
};

type DocumentUploadPanelProps = {
  workspacePublicId: string;
  configuration: DocumentUploadConfiguration;
  initialise?: (
    workspacePublicId: string,
    file: File,
  ) => Promise<InitialisedDocumentUpload>;
  complete?: (
    workspacePublicId: string,
    documentPublicId: string,
  ) => Promise<Document>;
  transport?: typeof uploadToPresignedUrl;
};

function fileIdentity(file: File): string {
  return `${file.name}:${file.size}:${file.lastModified}`;
}

function stateLabel(state: UploadState): string {
  return {
    waiting: "Waiting",
    initialising: "Initialising",
    uploading: "Uploading",
    verifying: "Verifying",
    complete: "Complete",
    failed: "Failed",
  }[state];
}

function stateTone(state: UploadState): StatusTone {
  if (state === "complete") return "success";
  if (state === "failed") return "destructive";
  if (state === "waiting") return "unavailable";
  return "pending";
}

export function DocumentUploadPanel({
  workspacePublicId,
  configuration,
  initialise = initialiseDocumentUpload,
  complete = completeDocumentUpload,
  transport = uploadToPresignedUrl,
}: DocumentUploadPanelProps) {
  const [items, setItems] = useState<UploadItem[]>([]);
  const [selectionErrors, setSelectionErrors] = useState<string[]>([]);
  const [batchRunning, setBatchRunning] = useState(false);

  const updateItem = useCallback(
    (id: string, changes: Partial<UploadItem>) => {
      setItems((current) =>
        current.map((item) =>
          item.id === id ? { ...item, ...changes } : item,
        ),
      );
    },
    [],
  );

  const uploadItem = useCallback(
    async (item: UploadItem): Promise<void> => {
      updateItem(item.id, {
        state: "initialising",
        progress: 0,
        error: null,
        document: null,
      });

      try {
        const initialised = await initialise(workspacePublicId, item.file);

        updateItem(item.id, {
          state: "uploading",
          document: initialised.document,
        });

        await transport(item.file, initialised.upload, (progress) => {
          updateItem(item.id, { progress, state: "uploading" });
        });

        updateItem(item.id, { state: "verifying", progress: 100 });

        const document = await complete(
          workspacePublicId,
          initialised.document.public_id,
        );

        if (document.status !== "uploaded") {
          throw new Error("Upload verification returned an unexpected state.");
        }

        updateItem(item.id, {
          state: "complete",
          progress: 100,
          document,
        });
      } catch (error) {
        updateItem(item.id, {
          state: "failed",
          error:
            error instanceof Error
              ? error.message
              : "The upload could not be completed.",
        });
      }
    },
    [complete, initialise, transport, updateItem, workspacePublicId],
  );

  const addFiles = useCallback(
    (files: File[]) => {
      const existingIds = new Set(items.map((item) => item.id));
      const errors: string[] = [];
      const accepted: UploadItem[] = [];

      files.forEach((file) => {
        const id = fileIdentity(file);
        const validationError = validateDocumentFile(file, configuration);

        if (validationError) {
          errors.push(validationError);
          return;
        }

        if (existingIds.has(id)) {
          errors.push(`${file.name}: this file is already in the queue.`);
          return;
        }

        existingIds.add(id);
        accepted.push({
          id,
          file,
          state: "waiting",
          progress: 0,
          error: null,
          document: null,
        });
      });

      setSelectionErrors(errors);
      setItems((current) => [...current, ...accepted]);
    },
    [configuration, items],
  );

  const handleSelection = (event: ChangeEvent<HTMLInputElement>) => {
    addFiles(Array.from(event.target.files ?? []));
    event.target.value = "";
  };

  const handleDrop = (event: DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    addFiles(Array.from(event.dataTransfer.files));
  };

  const startWaitingUploads = async () => {
    if (batchRunning) {
      return;
    }

    const waiting = items.filter((item) => item.state === "waiting");

    if (waiting.length === 0) {
      return;
    }

    setBatchRunning(true);

    try {
      await runWithConcurrency(
        waiting,
        configuration.upload_concurrency,
        uploadItem,
      );
    } finally {
      setBatchRunning(false);
    }
  };

  const retryItem = async (item: UploadItem) => {
    if (batchRunning) {
      return;
    }

    setBatchRunning(true);

    try {
      await uploadItem(item);
    } finally {
      setBatchRunning(false);
    }
  };

  const overallProgress = useMemo(() => {
    if (items.length === 0) {
      return 0;
    }

    return Math.round(
      items.reduce((total, item) => total + item.progress, 0) / items.length,
    );
  }, [items]);

  const acceptedExtensions = Object.keys(configuration.formats)
    .map((extension) => `.${extension}`)
    .join(",");
  const waitingCount = items.filter((item) => item.state === "waiting").length;

  return (
    <Card aria-labelledby="upload-heading">
      <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <CardTitle className="flex items-center gap-2" id="upload-heading"><FileUp className="size-5 text-brand" />Add source material</CardTitle>
          <CardDescription className="mt-2 max-w-2xl">
            Choose several supported documents. Each upload is verified
            independently before it becomes available for processing.
          </CardDescription>
        </div>
        <Button
          disabled={batchRunning || waitingCount === 0}
          onClick={startWaitingUploads}
          type="button"
        >
          {batchRunning
            ? "Uploading…"
            : waitingCount > 0
              ? `Upload ${waitingCount} file${waitingCount === 1 ? "" : "s"}`
              : "Upload files"}
        </Button>
      </CardHeader>

      <CardContent className="grid gap-5">
      <div
        className="grid min-h-48 place-items-center rounded-xl border border-dashed border-border bg-surface p-6 text-center"
        onDragOver={(event) => event.preventDefault()}
        onDrop={handleDrop}
      >
        <div className="grid justify-items-center gap-3"><span className="grid size-12 place-items-center rounded-full bg-surface-raised text-brand"><UploadCloud /></span><strong>Drop documents here</strong><span className="text-sm text-foreground-muted">or</span>
        <label className={cn(buttonVariants({ variant: "secondary" }), "cursor-pointer")} htmlFor="document-files">
          Choose files
        </label>
        <input
          accept={acceptedExtensions}
          id="document-files"
          multiple
          onChange={handleSelection}
          type="file"
        />
        <small className="text-foreground-muted">
          PDF, DOCX, DOC, RTF, TXT or Markdown · up to{" "}
          {Math.floor(configuration.max_upload_bytes / 1024 / 1024)}{" "}
          MB each
        </small></div>
      </div>

      {selectionErrors.length > 0 ? (
        <Notice tone="destructive">
          {selectionErrors.map((error) => (
            <p key={error}>{error}</p>
          ))}
        </Notice>
      ) : null}

      {items.length > 0 ? (
        <>
          <div className="grid gap-2"><div className="flex items-center justify-between text-sm"><span>Batch progress</span><strong>{overallProgress}%</strong></div>
            <progress className="h-2 w-full accent-brand" max="100" value={overallProgress}>
              {overallProgress}%
            </progress>
          </div>

          <ul className="grid gap-3" aria-label="Document upload queue">
            {items.map((item) => (
              <li className="rounded-lg border border-border bg-surface p-4" key={item.id}>
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <strong>{item.file.name}</strong>
                    <p className="mt-1 text-sm text-foreground-muted">{(item.file.size / 1024).toFixed(1)} KB</p>
                  </div>
                  <div className="flex items-center gap-2"><StatusBadge status={stateTone(item.state)}>{stateLabel(item.state)}</StatusBadge>
                    {item.state === "waiting" ? (
                      <Button
                        aria-label={`Remove ${item.file.name}`}
                        onClick={() =>
                          setItems((current) =>
                            current.filter(
                              (candidate) => candidate.id !== item.id,
                            ),
                          )
                        }
                        size="icon" type="button" variant="ghost"><Trash2 /></Button>
                    ) : null}
                    {item.state === "failed" ? (
                      <Button
                        disabled={batchRunning}
                        onClick={() => retryItem(item)}
                        size="sm" type="button" variant="outline"><RefreshCw />Retry</Button>
                    ) : null}
                  </div>
                </div>
                <div
                  aria-label={`${item.file.name} upload progress`}
                  aria-valuemax={100}
                  aria-valuemin={0}
                  aria-valuenow={item.progress}
                  className="mt-3 h-2 overflow-hidden rounded-full bg-surface-raised"
                  role="progressbar"
                >
                  <span className="block h-full bg-brand transition-[width] motion-reduce:transition-none" style={{ width: `${item.progress}%` }} />
                </div>
                <p className="mt-2 text-sm text-foreground-muted" aria-live="polite">
                  {item.state === "verifying"
                    ? "Upload transferred. Laravel is verifying object storage."
                    : `${stateLabel(item.state)} · ${item.progress}%`}
                </p>
                {item.error ? (
                  <Notice className="mt-3" tone="destructive">{item.error}</Notice>
                ) : null}
              </li>
            ))}
          </ul>
        </>
      ) : null}
      </CardContent>
    </Card>
  );
}
