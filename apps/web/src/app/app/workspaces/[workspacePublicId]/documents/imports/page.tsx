import { notFound } from "next/navigation";
import { ImportWorkflow } from "@/components/ImportWorkflow";
import { userWorkspace } from "@/lib/server-api";

export default async function ImportsPage({ params, searchParams }: Readonly<{ params: Promise<{ workspacePublicId: string }>; searchParams: Promise<{ batch?: string }> }>) {
  const { workspacePublicId } = await params;
  const { batch } = await searchParams;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();

  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Knowledge library</p><h1 className="mt-2 text-3xl font-semibold">Import documents</h1><p className="mt-2 max-w-3xl text-foreground-muted">Stage source files privately, verify their contents, resolve likely matches and review metadata before anything enters {workspace.name}.</p></header><ImportWorkflow initialBatchPublicId={batch} workspacePublicId={workspacePublicId} /></div>;
}
