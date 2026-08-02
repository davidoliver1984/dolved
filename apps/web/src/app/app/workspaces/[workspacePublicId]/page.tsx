import { notFound } from "next/navigation";
import { DocumentUploadPanel } from "@/components/DocumentUploadPanel";
import { WorkspaceSwitcher } from "@/components/WorkspaceSwitcher";
import {
  userWorkspace,
  userWorkspaces,
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

  return (
    <div className="workspace-layout">
      <WorkspaceSwitcher
        activeWorkspace={activeWorkspace}
        workspaces={workspaces}
      />

      <div>
        <section className="workspace-welcome">
          <p className="eyebrow">Active workspace</p>
          <h1>{activeWorkspace.name}</h1>
          <p>
            You are viewing this workspace as{" "}
            <strong>{activeWorkspace.role}</strong>. Laravel verified this
            membership before returning the workspace.
          </p>
        </section>

        <DocumentUploadPanel
          configuration={uploadConfiguration}
          workspacePublicId={activeWorkspace.public_id}
        />
      </div>
    </div>
  );
}
