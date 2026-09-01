import { clientEnvironment } from "@/lib/env/client";

export type User = {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
};

export type WorkspaceRole = "owner" | "admin" | "member";

export type OperationalMetricValue = {
  labels: Record<string, string>;
  value: number | null;
};

export type OperationalMetric = {
  status: "available" | "unavailable";
  values: OperationalMetricValue[];
};

export type PlatformOperationsSnapshot = {
  status: "available" | "partial" | "unavailable";
  health_status: "healthy" | "degraded" | "unknown";
  as_of: string;
  freshness: "current" | "unavailable";
  metrics: Record<string, OperationalMetric>;
  slos: Array<{
    id: string;
    objective: number;
    window_days: number;
    status: "available" | "no_data" | "unavailable";
    value: number | null;
    compliant: boolean | null;
  }>;
  alerts: {
    status: "available" | "unavailable";
    values: Array<{
      name: string;
      severity: "warning" | "urgent";
      subsystem: string;
      state: string;
      started_at: string;
      impact: string;
      runbook_url: string;
    }>;
  };
  grafana_url: string;
  alertmanager_url: string;
  operational_policy: OperationalPolicy | null;
};

export type OperationalPolicyTarget = {
  target: string;
  plan_id: string;
  expected_digest: string;
  current_attempt_id: string | null;
  status: "ACTIVE" | "PENDING" | "FAILED";
  reconciled_at: string | null;
};

export type OperationalPolicy = {
  public_id: string;
  environment: string;
  version: number;
  manifest_version: string;
  manifest_digest: string;
  active_settings: number;
  total_settings: number;
  fully_active: boolean;
  settings: Array<{
    setting_key: string;
    desired_value: number;
    status: "ACTIVE" | "PENDING" | "FAILED";
    targets: OperationalPolicyTarget[];
  }>;
};

export async function createOperationalPolicy(values: Record<string, number>): Promise<
  | { status: "ok"; data: { policy: unknown } }
  | { status: "concealed" }
> {
  try {
    const response = await apiFetch<{ data: { policy: unknown } }>(
      "/api/platform/operations/policy",
      { method: "POST", body: JSON.stringify(values) },
    );
    return { status: "ok", data: response.data };
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return { status: "concealed" };
    }
    throw error;
  }
}

export type Workspace = {
  public_id: string;
  name: string;
  slug: string;
  role: WorkspaceRole;
};

export type WorkspaceMembership = {
  public_id: string;
  user: { name: string; email: string };
  role: WorkspaceRole;
  joined_at: string | null;
  capabilities: {
    change_role: boolean;
    remove: boolean;
    transfer_ownership: boolean;
  };
};

export type WorkspaceInvitation = {
  public_id: string;
  invited_email: string;
  intended_role: Exclude<WorkspaceRole, "owner">;
  status: "pending" | "accepted" | "revoked" | "expired";
  expires_at: string;
  created_at: string | null;
  capabilities: { revoke: boolean };
};

export type WorkspaceAdministrationPage<T> = {
  data: T[];
  meta: { total: number };
};

export type WorkspaceAdministrationSnapshot = {
  memberships: WorkspaceMembership[];
  invitations: WorkspaceInvitation[];
};

export type WorkspaceUsageSnapshot = {
  range: { key: "7d" | "30d" | "month"; start: string; end: string; semantics: string };
  as_of: string;
  gauges: { active_documents: number; logical_source_bytes: number; indexed_chunks: number };
  historical: {
    ingestion_failures: number;
    activity: Array<{ event_kind: string; outcome: string | null; aggregate_count: number | string }>;
    usage: Array<{
      operation_kind: string;
      provider: string;
      model: string;
      cost_basis: "provider_reported" | "estimated" | "unavailable" | "zero_cost_local";
      pricing_snapshot: string | null;
      request_count: number | string;
      retry_count: number | string;
      input_tokens: number | string | null;
      cached_input_tokens: number | string | null;
      output_tokens: number | string | null;
      latency_ms: number | string | null;
      cost_usd: number | string | null;
      observation_count: number | string;
    }>;
  };
  labels: { logical_source_bytes: string; cost: string };
};

export type AdminDocument = {
  public_id: string;
  source_filename: string;
  media_type: string;
  size_bytes: number;
  status: string;
  governance_status: string;
  failure_category: string | null;
  failure_message: string | null;
  extraction_warnings: { code: string; message: string }[];
  created_by?: { name: string | null };
  deletion?: {
    public_id: string;
    status: string;
    failure_code: string | null;
    stuck: boolean;
  } | null;
  capabilities: { retry: boolean; delete: boolean };
  created_at: string;
  updated_at: string;
};

export type DocumentPage = {
  data: AdminDocument[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export type DocumentCategory = {
  public_id: string;
  name: string;
  status: "active" | "archived";
};

export type DocumentMetadata = {
  categories: DocumentCategory[];
  tags: Array<{ public_id: string; name: string }>;
  owners: Array<{ public_id: string; name: string }>;
  locations: Array<{ public_id: string; name: string }>;
};

export type SavedViewDefinition = {
  search?: string;
  filters?: {
    category?: string | null;
    applicability?: string | null;
    owner?: string | null;
    review_status?: "unassigned" | "overdue" | "due_soon";
    status?: "uploading" | "uploaded" | "queued" | "processing" | "indexed" | "failed";
  };
  sort?: "last_meaningful_update" | "title" | "review_due_date";
  direction?: "asc" | "desc";
  page_size?: 25 | 50 | 100;
  historical?: boolean;
};

export type SavedView = {
  public_id: string;
  name: string;
  definition_schema_version: number;
  definition: SavedViewDefinition;
  notices: string[];
  created_at: string | null;
  updated_at: string | null;
};

export type DocumentFamilyLibraryRow = {
  public_id: string;
  name: string;
  description: string | null;
  category: { public_id: string; name: string; status: string } | null;
  tags: Array<{ public_id: string; name: string; status: string }>;
  owner: { public_id?: string; name: string; needs_reassignment: boolean };
  review_due_date: string | null;
  last_meaningful_update: string;
  state: "current" | "scheduled" | "draft" | "historical" | "uploading" | "uploaded" | "queued" | "processing" | "failed";
  scheduled_effective_from: string | null;
  version_count: number;
  historical: boolean;
  current_version: null | {
    public_id: string;
    technical_status: string;
    source_filename: string;
    media_type: string;
    size_bytes: number;
    checksum_verification_status: string;
    governance_status: string;
    effective_from: string | null;
    approved_at: string | null;
    withdrawn_at: string | null;
    extraction_warning_count: number;
  };
};

export type DocumentFamilyPage = {
  data: DocumentFamilyLibraryRow[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

export type BulkOperationType = "bulk_approval" | "bulk_applicability_change" | "bulk_category_assignment" | "bulk_owner_assignment" | "bulk_tag_change" | "bulk_review_date_assignment";

export type BulkOperationSnapshot = {
  public_id: string;
  operation_type: string;
  status: string;
  selection_mode: "current_page" | "all_filtered";
  payload: Record<string, unknown>;
  filters: Record<string, unknown>;
  membership_digest: string;
  confirmed_at: string | null;
  cancellation_requested_at: string | null;
  counts: {
    total: number; eligible: number; excluded: number; open_attempts: number;
    waiting_on_subordinate: number; succeeded: number; skipped: number;
    failed_retryable: number; failed_permanent: number; cancelled: number;
  };
  exclusions: Record<string, number>;
  items: Array<{
    ordinal: number; target_kind: string; target_public_id: string;
    target_display_label: string; eligibility_status: string;
    exclusion_reason: string | null; execution_status: string;
    terminal_reason: string | null; result_identity: string | null;
  }>;
};

export type BulkOperationSummary = Pick<BulkOperationSnapshot, "public_id" | "operation_type" | "status" | "selection_mode" | "counts"> & {
  created_at: string | null;
  confirmed_at: string | null;
};

export type BulkOperationPage = {
  data: BulkOperationSummary[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

export type DocumentVersion = {
  public_id: string;
  family_public_id: string;
  source_filename: string;
  publisher_label: string | null;
  source_url: string | null;
  media_type: string;
  size_bytes: number;
  status: string;
  governance_status: "draft" | "approved" | "withdrawn";
  predecessor_public_id: string | null;
  effective_from: string;
  approved_at: string | null;
  withdrawn_at: string | null;
  is_current_authority: boolean;
  extraction_warning_count: number;
  applicability: {
    scope: string;
    locations: Array<{ public_id: string; name: string }>;
  };
  capabilities: {
    approve: boolean;
    withdraw: boolean;
    reschedule: boolean;
    create_applicability_successor: boolean;
    correct_timestamps: boolean;
  };
};

export type DocumentVersionHistory = {
  data: DocumentVersion[];
  meta: {
    current_version_public_id: string | null;
    locations: Array<{ public_id: string; name: string }>;
  };
};

export function workspaceDocumentLibrary(workspacePublicId: string, query = ""): Promise<DocumentFamilyPage> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-library${query ? `?${query}` : ""}`);
}

export function createBulkOperation(workspacePublicId: string, values: {
  operation_type: BulkOperationType;
  selection_mode: "current_page" | "all_filtered";
  target_public_ids: string[];
  filters: Record<string, unknown>;
  payload: Record<string, unknown>;
}): Promise<{ data: BulkOperationSnapshot }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/bulk-operations`, {
    method: "POST",
    body: JSON.stringify({ ...values, idempotency_key: crypto.randomUUID() }),
  });
}

export function bulkOperation(workspacePublicId: string, operationPublicId: string): Promise<{ data: BulkOperationSnapshot }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/bulk-operations/${encodeURIComponent(operationPublicId)}`);
}

export function bulkOperations(workspacePublicId: string, page = 1): Promise<BulkOperationPage> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/bulk-operations?page=${page}`);
}

export function confirmBulkOperation(workspacePublicId: string, operationPublicId: string): Promise<{ data: BulkOperationSnapshot }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/bulk-operations/${encodeURIComponent(operationPublicId)}/confirm`, { method: "POST" });
}

export function cancelBulkOperation(workspacePublicId: string, operationPublicId: string): Promise<{ data: BulkOperationSnapshot }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/bulk-operations/${encodeURIComponent(operationPublicId)}/cancel`, { method: "POST" });
}

export function retryBulkOperation(workspacePublicId: string, operationPublicId: string): Promise<{ data: BulkOperationSnapshot }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/bulk-operations/${encodeURIComponent(operationPublicId)}/retry`, { method: "POST" });
}

function governancePath(workspacePublicId: string, documentPublicId: string, action: string): string {
  return `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/${encodeURIComponent(documentPublicId)}/governance/${action}`;
}

export function approveDocumentVersion(workspacePublicId: string, documentPublicId: string): Promise<unknown> {
  return apiFetch(governancePath(workspacePublicId, documentPublicId, "approve"), {
    method: "POST",
    body: JSON.stringify({ idempotency_key: crypto.randomUUID() }),
  });
}

export function withdrawDocumentVersion(workspacePublicId: string, documentPublicId: string): Promise<unknown> {
  return apiFetch(governancePath(workspacePublicId, documentPublicId, "withdraw"), {
    method: "POST",
    body: JSON.stringify({ idempotency_key: crypto.randomUUID() }),
  });
}

export function rescheduleDocumentVersion(workspacePublicId: string, documentPublicId: string, effectiveFrom: string): Promise<unknown> {
  return apiFetch(governancePath(workspacePublicId, documentPublicId, "schedule"), {
    method: "PATCH",
    body: JSON.stringify({ idempotency_key: crypto.randomUUID(), effective_from: effectiveFrom }),
  });
}

export function createApplicabilitySuccessor(workspacePublicId: string, documentPublicId: string, effectiveFrom: string, locationPublicIds: string[]): Promise<unknown> {
  return apiFetch(governancePath(workspacePublicId, documentPublicId, "applicability-successors"), {
    method: "POST",
    body: JSON.stringify({ idempotency_key: crypto.randomUUID(), effective_from: effectiveFrom, location_public_ids: locationPublicIds }),
  });
}

export function correctDocumentVersionTimestamps(workspacePublicId: string, documentPublicId: string, approvedAt: string, withdrawnAt: string | null, reason: string): Promise<unknown> {
  return apiFetch(governancePath(workspacePublicId, documentPublicId, "timestamps"), {
    method: "PATCH",
    body: JSON.stringify({ idempotency_key: crypto.randomUUID(), approved_at: approvedAt, withdrawn_at: withdrawnAt, reason }),
  });
}

export function updateDocumentFamilyMetadata(workspacePublicId: string, familyPublicId: string, values: { name: string; description: string | null; category_public_id: string | null; owner_public_id: string; review_due_date: string | null }): Promise<unknown> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-families/${encodeURIComponent(familyPublicId)}/metadata`, {
    method: "PUT",
    body: JSON.stringify(values),
  });
}

export function updateDocumentFamilyTags(workspacePublicId: string, familyPublicId: string, tagPublicIds: string[]): Promise<unknown> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-families/${encodeURIComponent(familyPublicId)}/tags`, {
    method: "PUT",
    body: JSON.stringify({ tag_public_ids: tagPublicIds }),
  });
}

export function createSavedView(workspacePublicId: string, name: string, definition: SavedViewDefinition): Promise<{ data: SavedView }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/saved-views`, {
    method: "POST",
    body: JSON.stringify({ name, definition }),
  });
}

export function renameSavedView(workspacePublicId: string, savedViewPublicId: string, name: string): Promise<{ data: SavedView }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/saved-views/${encodeURIComponent(savedViewPublicId)}`, {
    method: "PATCH",
    body: JSON.stringify({ name }),
  });
}

export function deleteSavedView(workspacePublicId: string, savedViewPublicId: string): Promise<unknown> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/saved-views/${encodeURIComponent(savedViewPublicId)}`, { method: "DELETE" });
}

export function createDocumentCategory(workspacePublicId: string, name: string): Promise<{ data: DocumentCategory }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-categories`, {
    method: "POST",
    body: JSON.stringify({ name }),
  });
}

export function renameDocumentCategory(workspacePublicId: string, categoryPublicId: string, name: string): Promise<{ data: DocumentCategory }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-categories/${encodeURIComponent(categoryPublicId)}`, {
    method: "PATCH",
    body: JSON.stringify({ name }),
  });
}

export function archiveDocumentCategory(workspacePublicId: string, categoryPublicId: string): Promise<{ data: DocumentCategory }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-categories/${encodeURIComponent(categoryPublicId)}/archive`, { method: "PATCH" });
}

export function workspaceDocuments(
  workspacePublicId: string,
  query = "",
): Promise<DocumentPage> {
  return apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents${query ? `?${query}` : ""}`,
  );
}

export function retryWorkspaceDocument(
  workspacePublicId: string,
  documentPublicId: string,
  idempotencyKey: string,
): Promise<unknown> {
  return apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/${encodeURIComponent(documentPublicId)}/retries`,
    { method: "POST", body: JSON.stringify({ idempotency_key: idempotencyKey }) },
  );
}

export function deleteWorkspaceDocument(
  workspacePublicId: string,
  documentPublicId: string,
): Promise<unknown> {
  return apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/${encodeURIComponent(documentPublicId)}`,
    { method: "DELETE" },
  );
}

export function workspaceMembers(
  workspacePublicId: string,
): Promise<WorkspaceAdministrationPage<WorkspaceMembership>> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/members`);
}

export function workspaceInvitations(
  workspacePublicId: string,
): Promise<WorkspaceAdministrationPage<WorkspaceInvitation>> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/invitations`);
}

export function workspaceUsage(
  workspacePublicId: string,
  range: "7d" | "30d" | "month" = "30d",
): Promise<{ data: WorkspaceUsageSnapshot }> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/usage?range=${range}`);
}

export function issueWorkspaceInvitation(
  workspacePublicId: string,
  email: string,
  role: Exclude<WorkspaceRole, "owner">,
  idempotencyKey: string,
): Promise<{
  data: {
    invitation: WorkspaceInvitation | null;
    invitation_link: string | null;
    link_returned_once: boolean;
    delivery_status: "sent" | "unavailable" | "not_attempted";
    replayed: boolean;
    already_member: boolean;
  };
}> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/invitations`, {
    method: "POST",
    body: JSON.stringify({ email, role, idempotency_key: idempotencyKey }),
  });
}

export function revokeWorkspaceInvitation(
  workspacePublicId: string,
  invitationPublicId: string,
  idempotencyKey: string,
): Promise<unknown> {
  return apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/invitations/${encodeURIComponent(invitationPublicId)}`,
    { method: "DELETE", body: JSON.stringify({ idempotency_key: idempotencyKey }) },
  );
}

export function changeWorkspaceMemberRole(
  workspacePublicId: string,
  membershipPublicId: string,
  role: Exclude<WorkspaceRole, "owner">,
  idempotencyKey: string,
): Promise<unknown> {
  return apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/memberships/${encodeURIComponent(membershipPublicId)}/role`,
    { method: "PATCH", body: JSON.stringify({ role, idempotency_key: idempotencyKey }) },
  );
}

export function removeWorkspaceMember(
  workspacePublicId: string,
  membershipPublicId: string,
  idempotencyKey: string,
): Promise<unknown> {
  return apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/memberships/${encodeURIComponent(membershipPublicId)}`,
    { method: "DELETE", body: JSON.stringify({ idempotency_key: idempotencyKey }) },
  );
}

export function transferWorkspaceOwnership(
  workspacePublicId: string,
  membershipPublicId: string,
  idempotencyKey: string,
): Promise<unknown> {
  return apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/memberships/${encodeURIComponent(membershipPublicId)}/ownership-transfers`,
    { method: "POST", body: JSON.stringify({ idempotency_key: idempotencyKey }) },
  );
}

export function leaveWorkspace(workspacePublicId: string): Promise<unknown> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/membership`, {
    method: "DELETE",
  });
}

export function acceptWorkspaceInvitation(token: string): Promise<{
  data: { workspace_id: string; membership_id: string; role: WorkspaceRole };
}> {
  return apiFetch("/api/workspace-invitations/accept", {
    method: "POST",
    body: JSON.stringify({ token }),
  });
}

type ValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors: ValidationErrors = {},
  ) {
    super(message);
  }
}

function cookieValue(name: string): string | null {
  const prefix = `${name}=`;
  const cookie = document.cookie
    .split("; ")
    .find((item) => item.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null;
}

function isUnsafe(method: string): boolean {
  return !["GET", "HEAD", "OPTIONS"].includes(method.toUpperCase());
}

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const method = init.method ?? "GET";
  const headers = new Headers(init.headers);

  headers.set("Accept", "application/json");

  if (init.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  if (isUnsafe(method)) {
    try {
      await fetch(
        `${clientEnvironment.NEXT_PUBLIC_API_URL}/sanctum/csrf-cookie`,
        {
          credentials: "include",
          headers: { Accept: "application/json" },
        },
      );
    } catch {
      throw new ApiError(
        "Dolved could not reach the API. Please try again.",
        0,
      );
    }

    const token = cookieValue("XSRF-TOKEN");

    if (token) {
      headers.set("X-XSRF-TOKEN", token);
    }
  }

  let response: Response;

  try {
    response = await fetch(
      `${clientEnvironment.NEXT_PUBLIC_API_URL}${path}`,
      {
        ...init,
        credentials: "include",
        headers,
      },
    );
  } catch {
    throw new ApiError("Dolved could not reach the API. Please try again.", 0);
  }

  if (response.redirected) {
    throw new ApiError(
      "Your session changed. Refresh the page and try again.",
      response.status,
    );
  }

  let payload = null;

  if (response.status !== 204) {
    try {
      payload = await response.json();
    } catch {
      throw new ApiError(
        "Dolved received an unexpected response. Please try again.",
        response.status,
      );
    }
  }

  if (!response.ok) {
    throw new ApiError(
      payload?.message ?? "The request could not be completed.",
      response.status,
      payload?.errors,
    );
  }

  return payload as T;
}

export function firstError(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return "Something went wrong. Please try again.";
  }

  const validationMessage = Object.values(error.errors).flat()[0];

  return validationMessage ?? error.message;
}
