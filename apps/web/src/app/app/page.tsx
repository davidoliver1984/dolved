import { redirect } from "next/navigation";
import { LogoutButton } from "@/components/LogoutButton";
import { currentUser, platformAccess } from "@/lib/server-api";

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
        <p className="eyebrow">Workspace ready</p>
        <h1>Good to see you, {user.name.split(" ")[0]}.</h1>
        <p>
          Authentication and verified platform access are working. Document
          ingestion arrives in the next implementation phase.
        </p>
      </section>

      <section className="empty-workspace">
        <div className="empty-mark">M</div>
        <h2>Your knowledge space starts here.</h2>
        <p>The upload workflow will connect to this verified boundary.</p>
      </section>
    </main>
  );
}
