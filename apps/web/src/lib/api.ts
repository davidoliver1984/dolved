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

export function workspaceDocumentLibrary(workspacePublicId: string, query = ""): Promise<DocumentFamilyPage> {
  return apiFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-library${query ? `?${query}` : ""}`);
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
