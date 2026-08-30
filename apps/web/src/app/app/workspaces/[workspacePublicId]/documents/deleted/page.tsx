import { notFound } from "next/navigation";
import { DeletedDocumentFamilyHistory } from "@/components/DeletedDocumentFamilyHistory";
import { initialDeletedDocumentFamilies, userWorkspace } from "@/lib/server-api";

export default async function DeletedDocumentsPage({ params, searchParams }: Readonly<{ params: Promise<{ workspacePublicId: string }>; searchParams: Promise<{ page?: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace || workspace.role === "member") notFound();
  const requestedPage = Number.parseInt((await searchParams).page ?? "1", 10);
  const page = await initialDeletedDocumentFamilies(workspacePublicId, Number.isFinite(requestedPage) && requestedPage > 0 ? requestedPage : 1);
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Knowledge library</p><h1 className="mt-2 text-3xl font-semibold">Deleted history</h1><p className="mt-2 max-w-2xl text-foreground-muted">An immutable, owner-visible history of document families removed from the active library.</p></header><DeletedDocumentFamilyHistory page={page} workspacePublicId={workspacePublicId} /></div>;
}
