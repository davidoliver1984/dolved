import { notFound } from "next/navigation";
import { DocumentUploadPanel } from "@/components/DocumentUploadPanel";
import { DocumentAdministration } from "@/components/DocumentAdministration";
import { ChatWorkspace } from "@/components/ChatWorkspace";
import { WorkspaceSwitcher } from "@/components/WorkspaceSwitcher";
import { WorkspaceAdministration } from "@/components/WorkspaceAdministration";
import { WorkspaceUsage } from "@/components/WorkspaceUsage";
import {
  initialWorkspaceAdministration,
  initialWorkspaceUsage,
  userWorkspace,
  userWorkspaces,
  initialWorkspaceDocuments,
  workspaceUploadConfiguration,
} from "@/lib/server-api";

export default async function WorkspacePage({
  params,
}: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const [workspaces, activeWorkspace, uploadConfiguration] = await Promise.all([
    userWorkspaces(),
    userWorkspace(workspacePublicId),
    workspaceUploadConfiguration(workspacePublicId),
  ]);

  if (!activeWorkspace || !uploadConfiguration) {
    notFound();
  }

  const documentPage = await initialWorkspaceDocuments(workspacePublicId);
  const administration = activeWorkspace.role === "member"
    ? null
    : await initialWorkspaceAdministration(workspacePublicId);
  const usage = activeWorkspace.role === "member"
    ? null
    : await initialWorkspaceUsage(workspacePublicId);

  return (
    <div className="workspace-layout">
      <WorkspaceSwitcher
        activeWorkspace={activeWorkspace}
        workspaces={workspaces}
      />

      <div>
        <section className="workspace-welcome compact-welcome">
          <p className="eyebrow">Active workspace</p>
          <h1>{activeWorkspace.name}</h1>
          <p>
            You are viewing this workspace as{" "}
            <strong>{activeWorkspace.role}</strong>. Laravel verified this
            membership before returning the workspace.
          </p>
        </section>

        <ChatWorkspace
          workspaceId={activeWorkspace.public_id}
          workspaceName={activeWorkspace.name}
        />

        <details className="document-tools">
          <summary>Document upload</summary>
          <DocumentUploadPanel
            configuration={uploadConfiguration}
            workspacePublicId={activeWorkspace.public_id}
          />
        </details>

        <DocumentAdministration
          initialPage={documentPage}
          workspacePublicId={activeWorkspace.public_id}
        />

        <WorkspaceAdministration
          actorRole={activeWorkspace.role}
          initialSnapshot={administration}
          workspaceId={activeWorkspace.public_id}
        />
        {usage ? <WorkspaceUsage initialSnapshot={usage} workspaceId={activeWorkspace.public_id} /> : null}
      </div>
    </div>
  );
}
