import "server-only";

import type {
  AdminDocument,
  DocumentPage,
  PlatformOperationsSnapshot,
  User,
  Workspace,
  WorkspaceAdministrationPage,
  WorkspaceAdministrationSnapshot,
  WorkspaceInvitation,
  WorkspaceMembership,
  WorkspaceUsageSnapshot,
} from "@/lib/api";
import type { Conversation } from "@/lib/conversations";
import { forwardedAuthCookieHeader } from "@/lib/auth-cookies";
import type { DocumentUploadConfiguration } from "@/lib/document-upload";
import { serverEnvironment } from "@/lib/env/server";
import { structuredLog } from "@/lib/structured-log";

async function serverFetch(path: string): Promise<Response> {
  const cookieHeader = await forwardedAuthCookieHeader();
  const headers = new Headers({
    Accept: "application/json",
    Origin: serverEnvironment.FRONTEND_URL,
  });

  if (cookieHeader) {
    headers.set("Cookie", cookieHeader);
  }

  try {
    return await fetch(`${serverEnvironment.API_INTERNAL_URL}${path}`, {
      cache: "no-store",
      headers,
    });
  } catch (error) {
    structuredLog(
      "web.api.request_failed.v1",
      "error",
      { operation_kind: "api.request" },
      error,
    );
    throw error;
  }
}

export async function platformAccess(): Promise<Response> {
  return serverFetch("/api/platform/status");
}

export async function hasPlatformOperationsAccess(): Promise<boolean> {
  try {
    const response = await serverFetch("/api/platform/operations/access");
    return response.ok;
  } catch {
    return false;
  }
}

export async function platformOperations(): Promise<
  | { status: "ok"; data: PlatformOperationsSnapshot }
  | { status: "unauthorized" }
  | { status: "concealed" }
  | { status: "unavailable" }
> {
  try {
    const response = await serverFetch("/api/platform/operations/health");
    if (response.status === 401) return { status: "unauthorized" };
    if (response.status === 404) return { status: "concealed" };
    if (!response.ok) return { status: "unavailable" };
    const payload = (await response.json()) as {
      data: PlatformOperationsSnapshot;
    };
    return { status: "ok", data: payload.data };
  } catch {
    return { status: "unavailable" };
  }
}

export async function currentUser(): Promise<User | null> {
  const response = await serverFetch("/api/auth/user");

  if (response.status === 401) {
    return null;
  }

  if (!response.ok) {
    throw new Error("The current account is unavailable.");
  }

  const payload = (await response.json()) as { data: { user: User } };

  return payload.data.user;
}

export async function userWorkspaces(): Promise<Workspace[]> {
  const response = await serverFetch("/api/workspaces");

  if (!response.ok) {
    throw new Error("The workspace list is unavailable.");
  }

  const payload = (await response.json()) as { data: Workspace[] };

  return payload.data;
}

export async function userWorkspace(
  publicId: string,
): Promise<Workspace | null> {
  const response = await serverFetch(
    `/api/workspaces/${encodeURIComponent(publicId)}`,
  );

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error("The workspace is unavailable.");
  }

  const payload = (await response.json()) as { data: Workspace };

  return payload.data;
}

export async function workspaceUploadConfiguration(
  workspacePublicId: string,
): Promise<DocumentUploadConfiguration | null> {
  const response = await serverFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/uploads/configuration`,
  );

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error("Document upload configuration is unavailable.");
  }

  const payload = (await response.json()) as {
    data: DocumentUploadConfiguration;
  };

  return payload.data;
}

export async function initialWorkspaceDocuments(
  workspacePublicId: string,
): Promise<DocumentPage> {
  const response = await serverFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents?per_page=25`,
  );
  if (!response.ok) {
    throw new Error("The workspace document list is unavailable.");
  }
  return (await response.json()) as DocumentPage;
}

export async function initialWorkspaceDocument(
  workspacePublicId: string,
  documentPublicId: string,
): Promise<AdminDocument | null> {
  const response = await serverFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/${encodeURIComponent(documentPublicId)}`,
  );
  if (response.status === 404) return null;
  if (!response.ok) throw new Error("The workspace document is unavailable.");
  const payload = (await response.json()) as { data: AdminDocument };
  return payload.data;
}

export async function initialConversation(
  workspacePublicId: string,
  conversationPublicId: string,
): Promise<Conversation | null> {
  const response = await serverFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/conversations/${encodeURIComponent(conversationPublicId)}`,
  );
  if (response.status === 404) return null;
  if (!response.ok) throw new Error("The conversation is unavailable.");
  const payload = (await response.json()) as { data: Conversation };
  return payload.data;
}

export async function initialWorkspaceAdministration(
  workspacePublicId: string,
): Promise<WorkspaceAdministrationSnapshot> {
  const base = `/api/workspaces/${encodeURIComponent(workspacePublicId)}`;
  const [membersResponse, invitationsResponse] = await Promise.all([
    serverFetch(`${base}/members`),
    serverFetch(`${base}/invitations`),
  ]);
  if (!membersResponse.ok || !invitationsResponse.ok) {
    throw new Error("Workspace administration is unavailable.");
  }
  const memberships = (await membersResponse.json()) as WorkspaceAdministrationPage<WorkspaceMembership>;
  const invitations = (await invitationsResponse.json()) as WorkspaceAdministrationPage<WorkspaceInvitation>;

  return { memberships: memberships.data, invitations: invitations.data };
}

export async function initialWorkspaceUsage(
  workspacePublicId: string,
): Promise<WorkspaceUsageSnapshot> {
  const response = await serverFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/usage?range=30d`,
  );
  if (!response.ok) {
    throw new Error("Workspace usage is unavailable.");
  }
  const payload = (await response.json()) as { data: WorkspaceUsageSnapshot };
  return payload.data;
}
