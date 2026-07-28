"use client";

import {
  type ChangeEvent,
  type DragEvent,
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";
import { firstError } from "@/lib/api";
import {
  completeDocumentUpload,
  type Document,
  type DocumentUploadConfiguration,
  documentUploadConfiguration,
  initialiseDocumentUpload,
  type InitialisedDocumentUpload,
  runWithConcurrency,
  uploadToPresignedUrl,
  validateDocumentFile,
} from "@/lib/document-upload";

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
  configuration?: DocumentUploadConfiguration;
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

export function DocumentUploadPanel({
  workspacePublicId,
  configuration: providedConfiguration,
  initialise = initialiseDocumentUpload,
  complete = completeDocumentUpload,
  transport = uploadToPresignedUrl,
}: DocumentUploadPanelProps) {
  const [configuration, setConfiguration] =
    useState<DocumentUploadConfiguration | null>(
      providedConfiguration ?? null,
    );
  const [items, setItems] = useState<UploadItem[]>([]);
  const [selectionErrors, setSelectionErrors] = useState<string[]>([]);
  const [configurationError, setConfigurationError] = useState<string | null>(
    null,
  );
  const [batchRunning, setBatchRunning] = useState(false);

  useEffect(() => {
    if (providedConfiguration) {
      return;
    }

    let cancelled = false;

    documentUploadConfiguration(workspacePublicId)
      .then((loadedConfiguration) => {
        if (!cancelled) {
          setConfiguration(loadedConfiguration);
        }
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setConfigurationError(firstError(error));
        }
      });

    return () => {
      cancelled = true;
    };
  }, [providedConfiguration, workspacePublicId]);

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
      if (!configuration) {
        setSelectionErrors(["Upload configuration is still loading."]);
        return;
      }

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
    if (!configuration || batchRunning) {
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

  const acceptedExtensions = configuration
    ? Object.keys(configuration.formats)
        .map((extension) => `.${extension}`)
        .join(",")
    : "";
  const waitingCount = items.filter((item) => item.state === "waiting").length;

  return (
    <section className="document-upload-panel" aria-labelledby="upload-heading">
      <div className="upload-heading">
        <div>
          <p className="eyebrow">Documents</p>
          <h2 id="upload-heading">Add source material</h2>
          <p>
            Choose several supported documents. Each upload is verified
            independently before it becomes available for processing.
          </p>
        </div>
        <button
          className="primary-button"
          disabled={batchRunning || waitingCount === 0}
          onClick={startWaitingUploads}
          type="button"
        >
          {batchRunning
            ? "Uploading…"
            : waitingCount > 0
              ? `Upload ${waitingCount} file${waitingCount === 1 ? "" : "s"}`
              : "Upload files"}
        </button>
      </div>

      <div
        className="document-dropzone"
        onDragOver={(event) => event.preventDefault()}
        onDrop={handleDrop}
      >
        <strong>Drop documents here</strong>
        <span>or</span>
        <label className="secondary-button" htmlFor="document-files">
          Choose files
        </label>
        <input
          accept={acceptedExtensions}
          disabled={!configuration}
          id="document-files"
          multiple
          onChange={handleSelection}
          type="file"
        />
        <small>
          PDF, DOCX, DOC, RTF, TXT or Markdown · up to{" "}
          {configuration
            ? Math.floor(configuration.max_upload_bytes / 1024 / 1024)
            : "…"}{" "}
          MB each
        </small>
      </div>

      {configurationError ? (
        <p className="form-alert error" role="alert">
          {configurationError}
        </p>
      ) : null}

      {selectionErrors.length > 0 ? (
        <div className="form-alert error" role="alert">
          {selectionErrors.map((error) => (
            <p key={error}>{error}</p>
          ))}
        </div>
      ) : null}

      {items.length > 0 ? (
        <>
          <div className="batch-progress">
            <span>Batch progress</span>
            <strong>{overallProgress}%</strong>
            <progress max="100" value={overallProgress}>
              {overallProgress}%
            </progress>
          </div>

          <ul className="upload-queue" aria-label="Document upload queue">
            {items.map((item) => (
              <li className={`upload-item ${item.state}`} key={item.id}>
                <div className="upload-item-title">
                  <div>
                    <strong>{item.file.name}</strong>
                    <small>
                      {(item.file.size / 1024).toFixed(1)} KB ·{" "}
                      {stateLabel(item.state)}
                    </small>
                  </div>
                  <div className="upload-item-actions">
                    {item.state === "waiting" ? (
                      <button
                        className="text-button"
                        onClick={() =>
                          setItems((current) =>
                            current.filter(
                              (candidate) => candidate.id !== item.id,
                            ),
                          )
                        }
                        type="button"
                      >
                        Remove
                      </button>
                    ) : null}
                    {item.state === "failed" ? (
                      <button
                        className="text-button"
                        disabled={batchRunning}
                        onClick={() => retryItem(item)}
                        type="button"
                      >
                        Retry
                      </button>
                    ) : null}
                  </div>
                </div>
                <div
                  aria-label={`${item.file.name} upload progress`}
                  aria-valuemax={100}
                  aria-valuemin={0}
                  aria-valuenow={item.progress}
                  className="upload-progress"
                  role="progressbar"
                >
                  <span style={{ width: `${item.progress}%` }} />
                </div>
                <p className="upload-status" aria-live="polite">
                  {item.state === "verifying"
                    ? "Upload transferred. Laravel is verifying object storage."
                    : `${stateLabel(item.state)} · ${item.progress}%`}
                </p>
                {item.error ? (
                  <p className="upload-error" role="alert">
                    {item.error}
                  </p>
                ) : null}
              </li>
            ))}
          </ul>
        </>
      ) : null}
    </section>
  );
}
