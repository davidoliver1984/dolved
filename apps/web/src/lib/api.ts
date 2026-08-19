import { clientEnvironment } from "@/lib/env/client";

export type User = {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
};

export type WorkspaceRole = "owner" | "admin" | "member";

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
    await fetch(`${clientEnvironment.NEXT_PUBLIC_API_URL}/sanctum/csrf-cookie`, {
      credentials: "include",
      headers: { Accept: "application/json" },
    });

    const token = cookieValue("XSRF-TOKEN");

    if (token) {
      headers.set("X-XSRF-TOKEN", token);
    }
  }

  const response = await fetch(
    `${clientEnvironment.NEXT_PUBLIC_API_URL}${path}`,
    {
      ...init,
      credentials: "include",
      headers,
    },
  );

  const payload = response.status === 204 ? null : await response.json();

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
