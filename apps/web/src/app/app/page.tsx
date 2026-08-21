import { redirect } from "next/navigation";
import { Inbox } from "lucide-react";
import { EmptyState } from "@/components/ui/empty-state";
import { userWorkspaces } from "@/lib/server-api";

export default async function WorkspacePage() {
  const workspaces = await userWorkspaces();

  if (workspaces[0]) {
    redirect(`/app/workspaces/${workspaces[0].public_id}`);
  }

  return (
    <>
      <section>
        <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand">No workspace assigned</p>
        <h1 className="mt-2 text-3xl font-semibold">Your account is ready.</h1>
        <p className="mt-3 max-w-2xl text-foreground-muted">
          Your account is ready, but it has not yet been assigned to a
          workspace. A platform administrator can add your membership.
        </p>
      </section>

      <EmptyState className="mt-8" description="Workspace creation and membership management are administrator-controlled." icon={Inbox} title="No workspace access yet." />
    </>
  );
}
