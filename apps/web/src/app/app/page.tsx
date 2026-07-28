import { redirect } from "next/navigation";
import { LogoutButton } from "@/components/LogoutButton";
import { currentUser, platformAccess, userWorkspaces } from "@/lib/server-api";

export default async function WorkspacePage() {
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

  const user = await currentUser();

  if (!user) {
    redirect("/login");
  }

  const workspaces = await userWorkspaces();

  if (workspaces[0]) {
    redirect(`/app/workspaces/${workspaces[0].public_id}`);
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

      <section className="workspace-welcome">
        <p className="eyebrow">No workspace assigned</p>
        <h1>Good to see you, {user.name.split(" ")[0]}.</h1>
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
    </main>
  );
}
