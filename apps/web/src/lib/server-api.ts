import "server-only";

import type { DocumentPage, User, Workspace } from "@/lib/api";
import { forwardedAuthCookieHeader } from "@/lib/auth-cookies";
import type { DocumentUploadConfiguration } from "@/lib/document-upload";
import { serverEnvironment } from "@/lib/env/server";

async function serverFetch(path: string): Promise<Response> {
  const cookieHeader = await forwardedAuthCookieHeader();
  const headers = new Headers({
    Accept: "application/json",
    Origin: serverEnvironment.FRONTEND_URL,
  });

  if (cookieHeader) {
    headers.set("Cookie", cookieHeader);
  }

  return fetch(`${serverEnvironment.API_INTERNAL_URL}${path}`, {
    cache: "no-store",
    headers,
  });
}

export async function platformAccess(): Promise<Response> {
  return serverFetch("/api/platform/status");
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
