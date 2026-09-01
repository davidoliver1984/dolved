import { notFound } from "next/navigation";
import { BulkOperationHistory } from "@/components/BulkOperationHistory";
import { initialBulkOperations, userWorkspace } from "@/lib/server-api";

export default async function BulkOperationHistoryPage({ params, searchParams }: Readonly<{ params: Promise<{ workspacePublicId: string }>; searchParams: Promise<{ page?: string }> }>) {
  const { workspacePublicId } = await params;
  const query = await searchParams;
  const requestedPage = Math.max(1, Number.parseInt(query.page ?? "1", 10) || 1);
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace || workspace.role === "member") notFound();
  const history = await initialBulkOperations(workspacePublicId, requestedPage);

  return <div className="grid gap-6">
    <header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Knowledge library</p><h1 className="mt-2 text-3xl font-semibold">Bulk operation history</h1><p className="mt-2 max-w-2xl text-foreground-muted">Refresh-safe records of frozen membership, progress, exclusions and final outcomes.</p></header>
    <BulkOperationHistory page={history} workspacePublicId={workspacePublicId} />
  </div>;
}
