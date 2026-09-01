import { Bookmark, TriangleAlert } from "lucide-react";
import { notFound } from "next/navigation";
import { DocumentLibraryTable } from "@/components/DocumentLibraryTable";
import { Card, CardContent } from "@/components/ui/card";
import { initialDocumentLibrary, initialDocumentMetadata, initialSavedView, userWorkspace } from "@/lib/server-api";
import { queryFromSavedViewDefinition } from "@/lib/saved-view";

export default async function SavedDocumentViewPage({ params }: Readonly<{ params: Promise<{ savedViewPublicId: string; workspacePublicId: string }> }>) {
  const { savedViewPublicId, workspacePublicId } = await params;
  const [workspace, savedView] = await Promise.all([
    userWorkspace(workspacePublicId),
    initialSavedView(workspacePublicId, savedViewPublicId),
  ]);
  if (!workspace || !savedView) notFound();
  const query = queryFromSavedViewDefinition(savedView.definition);
  const encoded = new URLSearchParams(query).toString();
  const [library, metadata] = await Promise.all([
    initialDocumentLibrary(workspacePublicId, encoded),
    initialDocumentMetadata(workspacePublicId),
  ]);

  return <div className="grid gap-6">
    <header className="flex items-start gap-3"><span className="grid size-11 shrink-0 place-items-center rounded-full bg-brand/10 text-brand"><Bookmark aria-hidden="true" className="size-5" /></span><div><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Saved view</p><h1 className="mt-1 text-3xl font-semibold">{savedView.name}</h1><p className="mt-2 text-foreground-muted">Re-evaluated now against {workspace.name}&apos;s live knowledge library.</p></div></header>
    {savedView.notices.map((notice) => <Card className="border-warning" key={notice}><CardContent className="flex items-start gap-2 pt-5 text-sm"><TriangleAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-warning" /><span>{notice}</span></CardContent></Card>)}
    <DocumentLibraryTable canManage={workspace.role !== "member"} metadata={metadata} page={library} query={query} workspacePublicId={workspacePublicId} />
  </div>;
}
