import Link from "next/link";
import { notFound } from "next/navigation";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { initialWorkspaceAdministration, initialWorkspaceUsage, userWorkspace } from "@/lib/server-api";

export default async function AdministrationPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace || workspace.role === "member") notFound();
  const [administration, usage] = await Promise.all([initialWorkspaceAdministration(workspacePublicId), initialWorkspaceUsage(workspacePublicId)]);
  const destinations = [
    { title: "People & roles", description: `${administration.memberships.length} current members`, href: `/app/workspaces/${workspacePublicId}/administration/people` },
    { title: "Invitations", description: `${administration.invitations.length} invitation records`, href: `/app/workspaces/${workspacePublicId}/administration/invitations` },
    { title: "Usage", description: `${usage.gauges.active_documents} active documents`, href: `/app/workspaces/${workspacePublicId}/administration/usage` },
  ];
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Administration</p><h1 className="mt-2 text-3xl font-semibold">{workspace.name}</h1><p className="mt-2 text-foreground-muted">Manage access and understand workspace activity.</p></header><div className="grid gap-4 md:grid-cols-3">{destinations.map((item) => <Link href={item.href} key={item.href}><Card className="h-full transition hover:border-brand"><CardHeader><CardTitle>{item.title}</CardTitle><CardDescription>{item.description}</CardDescription></CardHeader><CardContent><span className="text-sm font-semibold text-brand">Open section</span></CardContent></Card></Link>)}</div></div>;
}
