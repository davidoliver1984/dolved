import { notFound } from "next/navigation";
import { WorkspaceUsage } from "@/components/WorkspaceUsage";
import { initialWorkspaceUsage, userWorkspace } from "@/lib/server-api";

export default async function UsagePage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace || workspace.role === "member") notFound();
  const usage = await initialWorkspaceUsage(workspacePublicId);
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Administration</p><h1 className="mt-2 text-3xl font-semibold">Usage</h1></header><WorkspaceUsage initialSnapshot={usage} workspaceId={workspacePublicId} /></div>;
}
