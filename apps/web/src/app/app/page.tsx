import { redirect } from "next/navigation";
import { userWorkspaces } from "@/lib/server-api";

export default async function WorkspacePage() {
  const workspaces = await userWorkspaces();

  if (workspaces[0]) {
    redirect(`/app/workspaces/${workspaces[0].public_id}`);
  }

  return (
    <>
      <section className="workspace-welcome">
        <p className="eyebrow">No workspace assigned</p>
        <h1>Your account is ready.</h1>
        <p>
          Your account is ready, but it has not yet been assigned to a
          workspace. A platform administrator can add your membership.
        </p>
      </section>

      <section className="empty-workspace">
        <div className="empty-mark">M</div>
        <h2>No workspace access yet.</h2>
        <p>
          Workspace creation and membership management are
          administrator-controlled.
        </p>
      </section>
    </>
  );
}
