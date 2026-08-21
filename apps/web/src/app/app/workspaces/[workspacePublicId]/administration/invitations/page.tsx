import { notFound } from "next/navigation";
import { WorkspaceAdministration } from "@/components/WorkspaceAdministration";
import { initialWorkspaceAdministration, userWorkspace } from "@/lib/server-api";

export default async function InvitationsPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace || workspace.role === "member") notFound();
  const administration = await initialWorkspaceAdministration(workspacePublicId);
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Administration</p><h1 className="mt-2 text-3xl font-semibold">Invitations</h1><p className="mt-2 text-foreground-muted">Issue, track and revoke workspace invitations without overstating delivery.</p></header><WorkspaceAdministration actorRole={workspace.role} initialSnapshot={administration} view="invitations" workspaceId={workspacePublicId} /></div>;
}
