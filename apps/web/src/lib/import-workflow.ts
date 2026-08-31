import { apiFetch } from "@/lib/api";
import { uploadToPresignedUrl, validateDocumentFile, type DocumentUploadConfiguration, type PresignedUpload } from "@/lib/document-upload";

export type ImportReviewOption = { public_id: string; name: string };
export type ImportConfiguration = DocumentUploadConfiguration & {
  retention_days: number;
  review_options: {
    categories: ImportReviewOption[];
    tags: ImportReviewOption[];
    owners: ImportReviewOption[];
    locations: ImportReviewOption[];
    current_user_public_id: string;
  };
};
export type ImportItem = {
  public_id: string;
  filename: string;
  declared_media_type: string;
  size_bytes: number | null;
  preflight_status: "pending" | "verified" | "rejected";
  preflight_rejection_reason: string | null;
  match_status: "pending" | "resolved";
  replaced_by_import_item_public_id?: string | null;
  decision_ready: boolean;
  promotion: null | { public_id: string; status: string; reason: string | null };
  document: null | { public_id: string; status: string };
};
export type ImportBatch = {
  public_id: string;
  status: "open" | "resolved" | "expired";
  retention_expires_at: string;
  created_at: string;
  items: ImportItem[];
};
export type ImportMatches = {
  profile_version: string;
  exact_live_duplicates: Array<{ document_id: string; family_id: string; status: string }>;
  deleted_duplicates: Array<{ document_id: string; family_id: string; status: string }>;
  applicability_only_redirect_document_id: string | null;
  family_candidates: Array<{ family_id: string; title: string; score_basis_points: number }>;
};
export type ImportDefinition = {
  family: { mode: "new"; title: string } | { mode: "successor"; family_public_id: string };
  metadata: {
    category_public_id: string | null;
    description: string | null;
    owner_user_public_id: string;
    publisher_label: string | null;
    review_due_date: string | null;
    source_url: string | null;
    tag_public_ids: string[];
  };
  applicability: { location_public_ids: string[] };
  effective_from: string;
};

const root = (workspaceId: string) => `/api/workspaces/${encodeURIComponent(workspaceId)}/imports`;

export async function importConfiguration(workspaceId: string): Promise<ImportConfiguration> {
  return (await apiFetch<{ data: ImportConfiguration }>(`${root(workspaceId)}/configuration`)).data;
}
export async function listImportBatches(workspaceId: string): Promise<ImportBatch[]> {
  return (await apiFetch<{ data: ImportBatch[] }>(root(workspaceId))).data;
}
export async function createImportBatch(workspaceId: string, files: File[]): Promise<{ batch: ImportBatch; uploads: Array<{ item_public_id: string; upload: PresignedUpload }> }> {
  return (await apiFetch<{ data: { batch: ImportBatch; uploads: Array<{ item_public_id: string; upload: PresignedUpload }> } }>(root(workspaceId), {
    method: "POST",
    body: JSON.stringify({ files: files.map((file) => ({ filename: file.name, media_type: file.type, size_bytes: file.size })) }),
  })).data;
}
export async function getImportBatch(workspaceId: string, batchId: string): Promise<ImportBatch> {
  return (await apiFetch<{ data: ImportBatch }>(`${root(workspaceId)}/${encodeURIComponent(batchId)}`)).data;
}
export async function stageImportFile(workspaceId: string, batchId: string, itemId: string, file: File, upload: PresignedUpload, onProgress: (progress: number) => void): Promise<void> {
  await uploadToPresignedUrl(file, upload, onProgress);
  await apiFetch(`${root(workspaceId)}/${encodeURIComponent(batchId)}/items/${encodeURIComponent(itemId)}/uploaded`, { method: "POST" });
}
export async function replaceImportFile(workspaceId: string, batchId: string, itemId: string, file: File, onProgress: (progress: number) => void): Promise<void> {
  const replacement = (await apiFetch<{ data: { item_public_id: string; upload: PresignedUpload } }>(`${root(workspaceId)}/${encodeURIComponent(batchId)}/items/${encodeURIComponent(itemId)}/replacements`, {
    method: "POST",
    body: JSON.stringify({ filename: file.name, media_type: file.type, size_bytes: file.size }),
  })).data;
  await stageImportFile(workspaceId, batchId, replacement.item_public_id, file, replacement.upload, onProgress);
}
export async function importMatches(workspaceId: string, batchId: string, itemId: string): Promise<ImportMatches> {
  return (await apiFetch<{ data: ImportMatches }>(`${root(workspaceId)}/${encodeURIComponent(batchId)}/items/${encodeURIComponent(itemId)}/matches`)).data;
}
export async function decideImport(workspaceId: string, batchId: string, itemId: string, definition: ImportDefinition): Promise<void> {
  await apiFetch(`${root(workspaceId)}/${encodeURIComponent(batchId)}/items/${encodeURIComponent(itemId)}/decision`, { method: "POST", body: JSON.stringify({ definition }) });
}
export async function promoteImport(workspaceId: string, batchId: string, itemId: string): Promise<void> {
  await apiFetch(`${root(workspaceId)}/${encodeURIComponent(batchId)}/items/${encodeURIComponent(itemId)}/promotions`, { method: "POST", body: JSON.stringify({ idempotency_key: crypto.randomUUID() }) });
}
export async function adoptImport(workspaceId: string, batchId: string, itemId: string, definition: ImportDefinition): Promise<void> {
  await apiFetch(`${root(workspaceId)}/${encodeURIComponent(batchId)}/items/${encodeURIComponent(itemId)}/adoptions`, { method: "POST", body: JSON.stringify({ definition, idempotency_key: crypto.randomUUID() }) });
}
export { validateDocumentFile };
