import { notFound, redirect } from "next/navigation";
import { LogoutButton } from "@/components/LogoutButton";
import { DocumentUploadPanel } from "@/components/DocumentUploadPanel";
import { WorkspaceSwitcher } from "@/components/WorkspaceSwitcher";
import {
  currentUser,
  platformAccess,
  userWorkspace,
  userWorkspaces,
} from "@/lib/server-api";

export default async function WorkspacePage({
  params,
}: PageProps<"/app/workspaces/[workspacePublicId]">) {
  const access = await platformAccess();

  if (access.status === 401) {
    redirect("/login");
  }

  if (access.status === 403) {
    redirect("/verify-email");
  }

  if (!access.ok) {
    throw new Error("The platform API is unavailable.");
  }

  const { workspacePublicId } = await params;
  const [user, workspaces, activeWorkspace] = await Promise.all([
    currentUser(),
    userWorkspaces(),
    userWorkspace(workspacePublicId),
  ]);

  if (!user) {
    redirect("/login");
  }

  if (!activeWorkspace) {
    notFound();
  }

  return (
    <main className="workspace-shell">
      <nav className="site-nav">
        <span className="wordmark">
          Make Time<span>.</span>
        </span>
        <div className="account-nav">
          <span>{user.email}</span>
          <LogoutButton />
        </div>
      </nav>

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

          <DocumentUploadPanel workspacePublicId={activeWorkspace.public_id} />
        </div>
      </div>
    </main>
  );
}
