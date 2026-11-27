import { notFound } from "next/navigation";
import { WorkspaceLocations } from "@/components/WorkspaceLocations";
import { userWorkspace } from "@/lib/server-api";

export default async function LocationsPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace || workspace.role === "member") notFound();
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Administration</p><h1 className="mt-2 text-3xl font-semibold">Locations</h1><p className="mt-2 text-foreground-muted">Manage the registered sites, services and regions used for structured applicability.</p></header><WorkspaceLocations workspaceId={workspacePublicId} /></div>;
}
