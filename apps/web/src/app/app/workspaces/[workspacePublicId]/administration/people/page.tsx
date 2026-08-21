import { notFound } from "next/navigation";
import { WorkspaceAdministration } from "@/components/WorkspaceAdministration";
import { initialWorkspaceAdministration, userWorkspace } from "@/lib/server-api";

export default async function PeoplePage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace || workspace.role === "member") notFound();
  const administration = await initialWorkspaceAdministration(workspacePublicId);
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Administration</p><h1 className="mt-2 text-3xl font-semibold">People &amp; roles</h1></header><WorkspaceAdministration actorRole={workspace.role} initialSnapshot={administration} workspaceId={workspacePublicId} /></div>;
}
