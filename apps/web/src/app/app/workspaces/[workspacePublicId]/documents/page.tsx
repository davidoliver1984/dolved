import { notFound } from "next/navigation";
import { DocumentAdministration } from "@/components/DocumentAdministration";
import { DocumentLibraryTable } from "@/components/DocumentLibraryTable";
import {
  initialWorkspaceDocuments,
  initialDocumentLibrary,
  initialDocumentMetadata,
  initialSavedViews,
  userWorkspace,
} from "@/lib/server-api";
import { savedViewDefinitionFromQuery } from "@/lib/saved-view";

export default async function DocumentsPage({ params, searchParams }: Readonly<{ params: Promise<{ workspacePublicId: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }>) {
  const { workspacePublicId } = await params;
  const query = await searchParams;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const libraryQuery = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) { const item = Array.isArray(value) ? value[0] : value; if (item) libraryQuery.set(key, item); }
  const [documents, library, metadata, savedViews] = await Promise.all([
    initialWorkspaceDocuments(workspacePublicId),
    initialDocumentLibrary(workspacePublicId, libraryQuery.toString()),
    initialDocumentMetadata(workspacePublicId),
    initialSavedViews(workspacePublicId),
  ]);
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Documents</p><h1 className="mt-2 text-3xl font-semibold">Knowledge sources</h1><p className="mt-2 text-foreground-muted">Review and manage the sources available to {workspace.name}.</p></header><DocumentLibraryTable canManage={workspace.role !== "member"} currentSavedViewDefinition={savedViewDefinitionFromQuery(query)} initialSavedViews={savedViews} metadata={metadata} page={library} query={query} workspacePublicId={workspacePublicId} /><details><summary className="cursor-pointer text-sm font-semibold text-foreground-muted">Technical ingestion controls</summary><div className="mt-4"><DocumentAdministration initialPage={documents} workspacePublicId={workspacePublicId} /></div></details></div>;
}
