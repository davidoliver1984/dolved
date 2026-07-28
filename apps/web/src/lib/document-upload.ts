import { apiFetch } from "@/lib/api";

export type DocumentStatus =
  | "uploading"
  | "uploaded"
  | "queued"
  | "processing"
  | "indexed"
  | "failed"
  | "deleting"
  | "deleted";

export type Document = {
  public_id: string;
  source_filename: string;
  media_type: string;
  size_bytes: number;
  status: DocumentStatus;
  created_at: string;
  updated_at: string;
};

export type DocumentUploadConfiguration = {
  formats: Record<string, string[]>;
  max_upload_bytes: number;
  upload_concurrency: number;
};

export type PresignedUpload = {
  url: string;
  method: "PUT";
  headers: Record<string, string>;
  expires_at: string;
};

export type InitialisedDocumentUpload = {
  document: Document;
  upload: PresignedUpload;
};

export async function documentUploadConfiguration(
  workspacePublicId: string,
): Promise<DocumentUploadConfiguration> {
  const payload = await apiFetch<{ data: DocumentUploadConfiguration }>(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/uploads/configuration`,
  );

  return payload.data;
}

export async function initialiseDocumentUpload(
  workspacePublicId: string,
  file: File,
): Promise<InitialisedDocumentUpload> {
  const payload = await apiFetch<{ data: InitialisedDocumentUpload }>(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/uploads`,
    {
      method: "POST",
      body: JSON.stringify({
        filename: file.name,
        media_type: file.type,
        size_bytes: file.size,
      }),
    },
  );

  return payload.data;
}

export async function completeDocumentUpload(
  workspacePublicId: string,
  documentPublicId: string,
): Promise<Document> {
  const payload = await apiFetch<{ data: Document }>(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/${encodeURIComponent(documentPublicId)}/uploads/complete`,
    { method: "POST" },
  );

  return payload.data;
}

export function validateDocumentFile(
  file: File,
  configuration: DocumentUploadConfiguration,
): string | null {
  const extension = file.name.split(".").pop()?.toLowerCase() ?? "";
  const allowedMimeTypes = configuration.formats[extension];

  if (!allowedMimeTypes) {
    return `${file.name}: this document format is not supported.`;
  }

  if (file.size === 0) {
    return `${file.name}: empty files cannot be uploaded.`;
  }

  if (file.size > configuration.max_upload_bytes) {
    const maximumMegabytes = Math.floor(
      configuration.max_upload_bytes / 1024 / 1024,
    );

    return `${file.name}: files must be ${maximumMegabytes} MB or smaller.`;
  }

  if (!allowedMimeTypes.includes(file.type)) {
    return `${file.name}: its declared file type does not match the extension.`;
  }

  return null;
}

export function uploadToPresignedUrl(
  file: File,
  upload: PresignedUpload,
  onProgress: (percentage: number) => void,
): Promise<void> {
  return new Promise((resolve, reject) => {
    const request = new XMLHttpRequest();

    request.open(upload.method, upload.url);

    Object.entries(upload.headers).forEach(([name, value]) => {
      request.setRequestHeader(name, value);
    });

    request.upload.addEventListener("progress", (event) => {
      if (!event.lengthComputable || event.total === 0) {
        return;
      }

      onProgress(Math.min(100, Math.round((event.loaded / event.total) * 100)));
    });

    request.addEventListener("load", () => {
      if (request.status >= 200 && request.status < 300) {
        onProgress(100);
        resolve();
        return;
      }

      reject(new Error("Object storage rejected the upload."));
    });
    request.addEventListener("error", () => {
      reject(new Error("The upload could not reach object storage."));
    });
    request.addEventListener("abort", () => {
      reject(new Error("The upload was cancelled."));
    });
    request.send(file);
  });
}

export async function runWithConcurrency<T>(
  items: T[],
  limit: number,
  worker: (item: T) => Promise<void>,
): Promise<void> {
  let nextIndex = 0;
  const workerCount = Math.min(Math.max(1, limit), items.length);

  await Promise.all(
    Array.from({ length: workerCount }, async () => {
      while (nextIndex < items.length) {
        const item = items[nextIndex];
        nextIndex += 1;
        await worker(item);
      }
    }),
  );
}
