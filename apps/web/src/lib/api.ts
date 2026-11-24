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
  branding?: EffectiveBranding;
  pipeline_capabilities?: { document_processing: "dify" | "custom" | "unavailable"; chat_generation: "dify" | "custom"; status: "available" | "unavailable" };
};

export type BrandingAsset = {
  public_id: string;
  kind: "primary_logo" | "dark_logo" | "favicon";
  url: string;
  width: number;
  height: number;
};

export type BrandingConfiguration = {
  display_name: string;
  logo_label: string;
  primary_logo: BrandingAsset | null;
  dark_logo: BrandingAsset | null;
  favicon: BrandingAsset | null;
  primary_colour: string;
  accent_colour: string;
  primary_foreground: string;
  accent_foreground: string;
  sign_in_heading: string | null;
  sign_in_introduction: string | null;
  chat_welcome_heading: string | null;
  chat_welcome_introduction: string | null;
  sender_display_name: string | null;
  footer_text: string | null;
  support_contact: string | null;
};

export type EffectiveBranding = {
  mode: "default" | "custom";
  revision: number | null;
  revision_public_id?: string | null;
  configuration: BrandingConfiguration;
};

export type PlatformTenant = {
  public_id: string;
  name: string;
  slug: string;
  branding_mode: "default" | "custom";
};

export type TenantBrandingSnapshot = {
  tenant: { public_id: string; name: string; slug: string };
  mode: "default" | "custom";
  draft_version: number;
  draft: BrandingConfiguration;
  applied_revision: number | null;
  applied_revision_public_id: string | null;
  last_custom_revision: number | null;
  can_reapply_custom: boolean;
  effective: EffectiveBranding;
};

export type GovernanceNotification = {
  public_id: string;
  title: string;
  message: string;
  severity: "info" | "action_required" | "warning";
  target_label: string | null;
  target_route: string | null;
  read_at: string | null;
  dismissed_at: string | null;
  created_at: string;
};

export type GovernanceNotificationPage = {
  data: GovernanceNotification[];
  meta: { unread_count: number; next_cursor: string | null };
};

export type GovernanceActionableWork = {
  awaiting_approval: number;
  imports_processing: number;
  imports_warning: number;
  scheduled_changes: number;
  review_due_soon: number;
  review_overdue: number;
};

export type GovernanceNotificationPreferences = {
  workspace: {
    email_delivery_enabled: boolean;
    default_email_enabled: boolean;
    can_manage: boolean;
  };
  personal: Array<{ category_group: string; email_enabled: boolean }>;
};

export async function governanceNotifications(workspacePublicId: string): Promise<GovernanceNotificationPage> {
  return apiFetch<GovernanceNotificationPage>(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/governance-notifications`,
  );
}

export async function markGovernanceNotificationRead(workspacePublicId: string, notificationPublicId: string): Promise<void> {
  await apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/governance-notifications/${encodeURIComponent(notificationPublicId)}/read`,
    { method: "POST" },
  );
}

export async function dismissGovernanceNotification(workspacePublicId: string, notificationPublicId: string): Promise<void> {
  await apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/governance-notifications/${encodeURIComponent(notificationPublicId)}/dismiss`,
    { method: "POST" },
  );
}

export async function governanceNotificationPreferences(workspacePublicId: string): Promise<GovernanceNotificationPreferences> {
  const response = await apiFetch<{ data: GovernanceNotificationPreferences }>(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/governance-notification-preferences`,
  );
  return response.data;
}

export async function updatePersonalGovernanceNotificationPreference(workspacePublicId: string, categoryGroup: string, emailEnabled: boolean): Promise<void> {
  await apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/governance-notification-preferences/personal`,
    { method: "PUT", body: JSON.stringify({ category_group: categoryGroup, email_enabled: emailEnabled }) },
  );
}

export async function updateWorkspaceGovernanceNotificationPreferences(workspacePublicId: string, values: { email_delivery_enabled: boolean; default_email_enabled: boolean }): Promise<void> {
  await apiFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/governance-notification-preferences/workspace`,
    { method: "PUT", body: JSON.stringify(values) },
  );
}

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
  gauges: { active_documents: number; logical_source_bytes: number; indexed_chunks: number | null; current_processing_failures: number | null };
  historical: {
    ingestion_failures: number | null;
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
    chat: {
      accepted_submissions: number;
      statuses: Array<{ status: string; aggregate_count: number | string }>;
      first_visible_response_ms_average: number | null;
      completion_ms_average: number | null;
    };
    retrieval: { runs_with_recorded_sources: number; average_returned_passages: number | null; empty_retrievals: number };
    document_sync: Array<{ sync_status: string; aggregate_count: number | string }>;
    unknown_cost_observations: number | string;
  };
  coverage: { status: "no_data" | "partial" | "complete"; collected_at: string; usage_observation_count: number | string; limitations: string[] };
  pipeline: { document_processing: "dify" | "custom" | "unavailable"; chat_generation: "dify" | "custom"; status: "available" | "unavailable" };
  observations: { indexed_chunks: { status: "available" | "stale" | "unavailable"; source: string; observed_at: string | null } };
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
  searchable_copies?: Array<{
    scope_key: string;
    status: string;
    failure_code: string | null;
    synced_at: string | null;
  }>;
  created_by?: { name: string | null };
  deletion?: {
    public_id: string;
    status: string;
    failure_code: string | null;
    stuck: boolean;
  } | null;
  capabilities: { retry: boolean; delete: boolean; extracted_text?: boolean };
  presentation?: { extracted_text: boolean; projection: string };
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
  presentation?: { extracted_text: boolean; projection: string; comparison_compatible_with: string[] };
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

function feedback(message: string, tone: "success" | "destructive"): void {
  if (typeof window !== "undefined") window.dispatchEvent(new CustomEvent("dolved:feedback", { detail: { message, tone } }));
}

function mutationFeedback(path: string, method: string): string | null {
  const cleanPath = path.split("?")[0];
  const verb = method.toUpperCase();
  const matches = (pattern: RegExp) => pattern.test(cleanPath);

  if (matches(/\/governance-notifications\/[^/]+\/(read|dismiss)$/)) return null;
  if (cleanPath === "/api/auth/login") return "Signed in successfully.";
  if (cleanPath === "/api/auth/logout") return "Signed out successfully.";
  if (cleanPath === "/api/auth/register") return "Account created. Check your email to verify it.";
  if (cleanPath === "/api/auth/forgot-password") return "If an account exists for that email, a reset link has been sent.";
  if (cleanPath === "/api/auth/reset-password") return "Password changed successfully.";
  if (cleanPath === "/api/auth/email/verification-notification") return "Verification email sent.";
  if (cleanPath === "/api/workspace-invitations/accept") return "Invitation accepted successfully.";

  if (matches(/\/documents\/[^/]+\/governance\/approve$/)) return "Document approved.";
  if (matches(/\/documents\/[^/]+\/governance\/withdraw$/)) return "Document withdrawn.";
  if (matches(/\/documents\/[^/]+\/governance\/schedule$/)) return "Document effective date updated.";
  if (matches(/\/documents\/[^/]+\/governance\/applicability-successors$/)) return "Document applicability updated and successor version created.";
  if (matches(/\/documents\/[^/]+\/governance\/timestamps$/)) return "Historical document timestamps corrected.";
  if (matches(/\/document-families\/[^/]+\/metadata$/)) return "Document family details updated.";
  if (matches(/\/document-families\/[^/]+\/tags$/)) return "Document family tags updated.";
  if (matches(/\/documents\/[^/]+\/retries$/)) return "Document retry queued.";
  if (verb === "DELETE" && matches(/\/documents\/[^/]+$/)) return "Document deletion queued.";

  if (verb === "POST" && matches(/\/saved-views$/)) return "View saved.";
  if (verb === "PATCH" && matches(/\/saved-views\/[^/]+$/)) return "Saved view renamed.";
  if (verb === "DELETE" && matches(/\/saved-views\/[^/]+$/)) return "Saved view deleted.";
  if (verb === "POST" && matches(/\/document-categories$/)) return "Category created.";
  if (matches(/\/document-categories\/[^/]+\/archive$/)) return "Category archived.";
  if (verb === "PATCH" && matches(/\/document-categories\/[^/]+$/)) return "Category renamed.";

  if (verb === "POST" && matches(/\/bulk-operations$/)) return "Bulk operation review prepared.";
  if (matches(/\/bulk-operations\/[^/]+\/confirm$/)) return "Bulk operation started.";
  if (matches(/\/bulk-operations\/[^/]+\/cancel$/)) return "Bulk operation cancellation requested.";
  if (matches(/\/bulk-operations\/[^/]+\/retry$/)) return "Bulk operation retry queued.";

  if (verb === "POST" && matches(/\/invitations$/)) return "Invitation sent.";
  if (verb === "DELETE" && matches(/\/invitations\/[^/]+$/)) return "Invitation revoked.";
  if (matches(/\/memberships\/[^/]+\/role$/)) return "Member role updated.";
  if (verb === "DELETE" && matches(/\/memberships\/[^/]+$/)) return "Member removed from the workspace.";
  if (matches(/\/memberships\/[^/]+\/ownership-transfers$/)) return "Workspace ownership transferred.";
  if (verb === "DELETE" && matches(/\/membership$/)) return "You left the workspace.";

  if (matches(/\/branding\/draft$/)) return "Branding draft saved.";
  if (matches(/\/branding\/apply$/)) return "Custom branding applied.";
  if (matches(/\/branding\/mode$/)) return "Branding mode updated.";
  if (matches(/\/branding\/assets$/)) return "Branding asset uploaded.";

  if (matches(/\/governance-notification-preferences\/personal$/)) return "Your notification preference was updated.";
  if (matches(/\/governance-notification-preferences\/workspace$/)) return "Workspace notification preferences updated.";
  if (matches(/\/question-review\/policy$/)) return "Question review policy updated.";
  if (verb === "PATCH" && matches(/\/question-review\/questions\/[^/]+$/)) return "Question review saved.";
  if (verb === "POST" && matches(/\/question-review\/subjects$/)) return "Subject added.";
  if (verb === "POST" && matches(/\/locations$/)) return "Location created.";
  if (cleanPath === "/api/platform/operations/policy") return "Operational policy submitted.";

  if (verb === "POST" && matches(/\/imports$/)) return "Import batch created.";
  if (matches(/\/imports\/[^/]+\/items\/[^/]+\/uploaded$/)) return "Document uploaded for import review.";
  if (matches(/\/imports\/[^/]+\/items\/[^/]+\/replacements$/)) return "Replacement document prepared.";
  if (matches(/\/imports\/[^/]+\/items\/[^/]+\/decision$/)) return "Import review decision saved.";
  if (matches(/\/imports\/[^/]+\/items\/[^/]+\/promotions$/)) return "Document promotion started.";
  if (matches(/\/imports\/[^/]+\/items\/[^/]+\/adoptions$/)) return "Revised import decision adopted.";

  // Background and conversational writes do not need a generic success toast.
  return null;
}

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const method = init.method ?? "GET";
  const headers = new Headers(init.headers);

  headers.set("Accept", "application/json");

  if (init.body && !(init.body instanceof FormData) && !headers.has("Content-Type")) {
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
      const message = "We could not reach the service. Please try again.";
      feedback(message, "destructive");
      throw new ApiError(message, 0);
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
    const message = "We could not reach the service. Please try again.";
    feedback(message, "destructive");
    throw new ApiError(message, 0);
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
        "The service returned an unexpected response. Please try again.",
        response.status,
      );
    }
  }

  if (!response.ok) {
    const message = payload?.message ?? "The request could not be completed.";
    const validationMessage = Object.values((payload?.errors ?? {}) as ValidationErrors).flat()[0];
    if (isUnsafe(method) && response.status !== 401) feedback(validationMessage ?? message, "destructive");
    throw new ApiError(
      message,
      response.status,
      payload?.errors,
    );
  }

  const success = isUnsafe(method) ? mutationFeedback(path, method) : null;
  if (success) feedback(success, "success");

  return payload as T;
}

export async function workspaceBranding(workspacePublicId: string): Promise<EffectiveBranding> {
  const response = await apiFetch<{ data: EffectiveBranding }>(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/branding`);
  return response.data;
}

export async function deploymentBranding(): Promise<EffectiveBranding> {
  const response = await apiFetch<{ data: EffectiveBranding }>("/api/branding/effective");
  return response.data;
}

export async function invitationBranding(token: string): Promise<EffectiveBranding> {
  const response = await apiFetch<{ data: EffectiveBranding }>(`/api/branding/invitations/${encodeURIComponent(token)}`);
  return response.data;
}

export async function saveTenantBrandingDraft(workspacePublicId: string, draftVersion: number, configuration: BrandingConfiguration): Promise<TenantBrandingSnapshot> {
  const assetId = (asset: BrandingAsset | null) => asset?.public_id ?? null;
  const response = await apiFetch<{ data: TenantBrandingSnapshot }>(`/api/platform/tenants/${encodeURIComponent(workspacePublicId)}/branding/draft`, {
    method: "PUT",
    body: JSON.stringify({
      draft_version: draftVersion,
      configuration: {
        ...configuration,
        primary_logo: assetId(configuration.primary_logo),
        dark_logo: assetId(configuration.dark_logo),
        favicon: assetId(configuration.favicon),
      },
    }),
  });
  return response.data;
}

export async function applyTenantBranding(workspacePublicId: string, draftVersion: number): Promise<TenantBrandingSnapshot> {
  const response = await apiFetch<{ data: TenantBrandingSnapshot }>(`/api/platform/tenants/${encodeURIComponent(workspacePublicId)}/branding/apply`, {
    method: "POST", body: JSON.stringify({ draft_version: draftVersion }),
  });
  return response.data;
}

export async function switchTenantBrandingMode(workspacePublicId: string, mode: "default" | "custom"): Promise<TenantBrandingSnapshot> {
  const response = await apiFetch<{ data: TenantBrandingSnapshot }>(`/api/platform/tenants/${encodeURIComponent(workspacePublicId)}/branding/mode`, {
    method: "PUT", body: JSON.stringify({ mode }),
  });
  return response.data;
}

export async function uploadTenantBrandingAsset(workspacePublicId: string, kind: BrandingAsset["kind"], file: File): Promise<BrandingAsset> {
  const body = new FormData();
  body.set("kind", kind);
  body.set("asset", file);
  const response = await apiFetch<{ data: BrandingAsset }>(`/api/platform/tenants/${encodeURIComponent(workspacePublicId)}/branding/assets`, { method: "POST", body });
  return response.data;
}

export type QuestionReviewPolicy = {
  enabled: boolean;
  policy_version: string;
  effective_from: string | null;
  review_from: string | null;
  retrospective_days: number;
  can_manage: boolean;
  can_review: boolean;
  retrospective_default: "off";
};

export type QuestionReviewSubject = { public_id: string; name: string; status: string; priority: string };
export type QuestionReviewState = {
  public_id?: string;
  review_status: string;
  issue_type: string | null;
  workflow_state: string;
  priority: string;
  review_notes: string | null;
  linked_document_public_id?: string | null;
  owner_user_id?: string | null;
  reviewed_at?: string | null;
  subjects: QuestionReviewSubject[];
};
export type QuestionReviewItem = {
  run_id: string;
  occurred_at: string;
  run_status: string;
  retrieval_outcome: string | null;
  site_context: string;
  review: QuestionReviewState;
};
export type QuestionReviewDetail = QuestionReviewItem & {
  question: string;
  answer: string | null;
  sources: Array<{ public_id: string; rank: number; display_name: string; document_id: string | null }>;
  notice: string;
};
export type KnowledgeGap = QuestionReviewSubject & {
  question_count: number;
  reviewed_count: number;
  unresolved_count: number;
  confirmed_gap_count: number;
};

const questionReviewRoot = (workspaceId: string) => `/api/workspaces/${encodeURIComponent(workspaceId)}/question-review`;
export async function questionReviewPolicy(workspaceId: string): Promise<QuestionReviewPolicy> {
  return (await apiFetch<{ data: QuestionReviewPolicy }>(`${questionReviewRoot(workspaceId)}/policy`)).data;
}
export async function updateQuestionReviewPolicy(workspaceId: string, enabled: boolean, includeRetrospective: boolean, retrospectiveDays: number): Promise<QuestionReviewPolicy> {
  return (await apiFetch<{ data: QuestionReviewPolicy }>(`${questionReviewRoot(workspaceId)}/policy`, { method: "PUT", body: JSON.stringify({ enabled, include_retrospective: includeRetrospective, retrospective_days: retrospectiveDays, privacy_confirmation: true }) })).data;
}
export async function questionReviewItems(workspaceId: string): Promise<QuestionReviewItem[]> {
  return (await apiFetch<{ data: QuestionReviewItem[] }>(`${questionReviewRoot(workspaceId)}/questions`)).data;
}
export async function questionReviewDetail(workspaceId: string, runId: string, purpose: string): Promise<QuestionReviewDetail> {
  return (await apiFetch<{ data: QuestionReviewDetail }>(`${questionReviewRoot(workspaceId)}/questions/${encodeURIComponent(runId)}?purpose=${encodeURIComponent(purpose)}`)).data;
}
export async function updateQuestionReview(workspaceId: string, runId: string, review: Omit<QuestionReviewState, "public_id" | "reviewed_at" | "subjects"> & { subject_ids: string[] }): Promise<QuestionReviewState> {
  return (await apiFetch<{ data: QuestionReviewState }>(`${questionReviewRoot(workspaceId)}/questions/${encodeURIComponent(runId)}`, { method: "PATCH", body: JSON.stringify(review) })).data;
}
export async function questionReviewSubjects(workspaceId: string): Promise<QuestionReviewSubject[]> {
  return (await apiFetch<{ data: QuestionReviewSubject[] }>(`${questionReviewRoot(workspaceId)}/subjects`)).data;
}
export async function createQuestionReviewSubject(workspaceId: string, name: string, priority: string): Promise<QuestionReviewSubject> {
  return (await apiFetch<{ data: QuestionReviewSubject }>(`${questionReviewRoot(workspaceId)}/subjects`, { method: "POST", body: JSON.stringify({ name, priority }) })).data;
}
export async function questionReviewKnowledgeGaps(workspaceId: string): Promise<{ data: KnowledgeGap[]; description: string }> {
  const response = await apiFetch<{ data: KnowledgeGap[]; meta: { description: string } }>(`${questionReviewRoot(workspaceId)}/knowledge-gaps`);
  return { data: response.data, description: response.meta.description };
}

export type WorkspaceLocation = { public_id: string; name: string; kind: "site" | "service" | "region"; parent: { public_id: string; name: string } | null };
export async function workspaceLocations(workspaceId: string): Promise<WorkspaceLocation[]> {
  return (await apiFetch<{ data: WorkspaceLocation[] }>(`/api/workspaces/${encodeURIComponent(workspaceId)}/locations`)).data;
}
export async function createWorkspaceLocation(workspaceId: string, name: string, kind: WorkspaceLocation["kind"], parentPublicId: string | null): Promise<WorkspaceLocation> {
  return (await apiFetch<{ data: WorkspaceLocation }>(`/api/workspaces/${encodeURIComponent(workspaceId)}/locations`, { method: "POST", body: JSON.stringify({ name, kind, parent_public_id: parentPublicId }) })).data;
}

export function firstError(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return "Something went wrong. Please try again.";
  }

  const validationMessage = Object.values(error.errors).flat()[0];

  return validationMessage ?? error.message;
}
