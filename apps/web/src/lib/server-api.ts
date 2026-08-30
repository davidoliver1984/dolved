import "server-only";

import type {
  AdminDocument,
  DocumentPage,
  DocumentFamilyPage,
  DocumentMetadata,
  DocumentVersionHistory,
  PlatformOperationsSnapshot,
  SavedView,
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

export type DocumentFamilyMetadata = {
  public_id: string;
  name: string;
  description: string | null;
  review_due_date: string | null;
  category: { public_id: string; name: string; status: string } | null;
  owner: { public_id: string; name: string } | null;
  tags: Array<{ public_id: string; name: string }>;
  capabilities: { edit: boolean };
  edit_options: null | {
    categories: Array<{ public_id: string; name: string }>;
    tags: Array<{ public_id: string; name: string }>;
    owners: Array<{ public_id: string; name: string }>;
  };
};

export type DocumentFamilyDetail = {
  family: DocumentFamilyMetadata;
  history: DocumentVersionHistory;
};

export type ExtractedTextPage = {
  label: string;
  notice: string;
  projection_generation_id: string;
  elements: Array<{ id: string; ordinal: number; kind: string; text: string; source_locations: Array<Record<string, unknown>>; level: number | null; rows: unknown[] | null }>;
  warnings: Array<{ code: string | null; message: string | null; element_id: string | null; source_location: unknown }>;
  warnings_truncated: boolean;
  changes: Array<{ code: string | null; message: string | null; source_element_ids: string[] }>;
  changes_truncated: boolean;
  pagination: { next_cursor: string | null; previous_cursor: string | null; per_page: number };
};

export type ComparisonElement = { id: string; ordinal: number; kind: string; text: string };
export type ComparisonSide = { document: { public_id: string; source_filename: string; publisher_label: string | null; source_url: string | null; governance_status: string; effective_from: string | null; approved_at: string | null; withdrawn_at: string | null }; content_available: boolean; truncated: boolean; elements: ComparisonElement[]; warnings: Array<{ code: string | null; message: string | null }> };
export type DocumentComparison = { available: boolean; reason?: string; family?: { public_id: string; name: string }; from?: ComparisonSide; to?: ComparisonSide; differences?: Array<{ ordinal: number; status: "added" | "removed" | "changed" | "unchanged"; before: ComparisonElement | null; after: ComparisonElement | null }> };
export type KnowledgeReadiness = { searchable_document_count: number };
export type StarterQuestion = { family_public_id: string; question: string };

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

export async function workspaceKnowledgeReadiness(workspacePublicId: string): Promise<KnowledgeReadiness> {
  const response = await serverFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/knowledge-readiness`);
  if (!response.ok) throw new Error("Knowledge readiness is unavailable.");
  const payload = (await response.json()) as { data: KnowledgeReadiness };
  return payload.data;
}

export async function workspaceStarterQuestions(workspacePublicId: string): Promise<StarterQuestion[]> {
  const response = await serverFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/starter-questions`);
  if (!response.ok) throw new Error("Starter questions are unavailable.");
  const payload = (await response.json()) as { data: StarterQuestion[] };
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

export async function initialDocumentLibrary(
  workspacePublicId: string,
  query = "",
): Promise<DocumentFamilyPage> {
  const response = await serverFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-library${query ? `?${query}` : ""}`,
  );
  if (!response.ok) throw new Error("The knowledge library is unavailable.");
  return (await response.json()) as DocumentFamilyPage;
}

export async function initialSavedViews(workspacePublicId: string): Promise<SavedView[]> {
  const response = await serverFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/saved-views`);
  if (!response.ok) throw new Error("Saved views are unavailable.");
  const payload = (await response.json()) as { data: SavedView[] };
  return payload.data;
}

export async function initialSavedView(workspacePublicId: string, savedViewPublicId: string): Promise<SavedView | null> {
  const response = await serverFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/saved-views/${encodeURIComponent(savedViewPublicId)}`);
  if (response.status === 404) return null;
  if (!response.ok) throw new Error("The saved view is unavailable.");
  const payload = (await response.json()) as { data: SavedView };
  return payload.data;
}

export async function initialDocumentMetadata(workspacePublicId: string): Promise<DocumentMetadata> {
  const response = await serverFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-metadata`);
  if (!response.ok) throw new Error("Library settings are unavailable.");
  const payload = (await response.json()) as { data: DocumentMetadata };
  return payload.data;
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

export async function initialExtractedText(workspacePublicId: string, documentPublicId: string, query = ""): Promise<ExtractedTextPage | null> {
  const response = await serverFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/${encodeURIComponent(documentPublicId)}/extracted-text${query ? `?${query}` : ""}`);
  if (response.status === 404) return null;
  if (!response.ok) throw new Error("Extracted text is unavailable.");
  const payload = (await response.json()) as { data: ExtractedTextPage };
  return payload.data;
}

export async function initialDocumentComparison(workspacePublicId: string, familyPublicId: string, query = ""): Promise<DocumentComparison | null> {
  const response = await serverFetch(`/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-families/${encodeURIComponent(familyPublicId)}/comparison${query ? `?${query}` : ""}`);
  if (response.status === 404) return null;
  if (!response.ok) throw new Error("The version comparison is unavailable.");
  const payload = (await response.json()) as { data: DocumentComparison };
  return payload.data;
}

export async function initialDocumentFamilyMetadata(
  workspacePublicId: string,
  familyPublicId: string,
): Promise<DocumentFamilyMetadata | null> {
  const response = await serverFetch(
    `/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-families/${encodeURIComponent(familyPublicId)}/metadata`,
  );
  if (response.status === 404) return null;
  if (!response.ok) throw new Error("The document family is unavailable.");
  const payload = (await response.json()) as { data: DocumentFamilyMetadata };
  return payload.data;
}

export async function initialDocumentFamilyDetail(
  workspacePublicId: string,
  familyPublicId: string,
): Promise<DocumentFamilyDetail | null> {
  const base = `/api/workspaces/${encodeURIComponent(workspacePublicId)}/document-families/${encodeURIComponent(familyPublicId)}`;
  const [familyResponse, historyResponse] = await Promise.all([
    serverFetch(`${base}/metadata`),
    serverFetch(`${base}/versions`),
  ]);
  if (familyResponse.status === 404 || historyResponse.status === 404) return null;
  if (!familyResponse.ok || !historyResponse.ok) {
    throw new Error("The document family is unavailable.");
  }
  const familyPayload = (await familyResponse.json()) as { data: DocumentFamilyMetadata };
  const history = (await historyResponse.json()) as DocumentVersionHistory;
  return { family: familyPayload.data, history };
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
