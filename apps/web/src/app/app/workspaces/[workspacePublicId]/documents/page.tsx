import { notFound } from "next/navigation";
import { DocumentAdministration } from "@/components/DocumentAdministration";
import { DocumentUploadPanel } from "@/components/DocumentUploadPanel";
import {
  initialWorkspaceDocuments,
  userWorkspace,
  workspaceUploadConfiguration,
} from "@/lib/server-api";

export default async function DocumentsPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const [configuration, documents] = await Promise.all([
    workspaceUploadConfiguration(workspacePublicId),
    initialWorkspaceDocuments(workspacePublicId),
  ]);
  if (!configuration) notFound();
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Documents</p><h1 className="mt-2 text-3xl font-semibold">Knowledge sources</h1><p className="mt-2 text-foreground-muted">Upload, review and manage the sources available to {workspace.name}.</p></header><DocumentUploadPanel configuration={configuration} workspacePublicId={workspacePublicId} /><DocumentAdministration initialPage={documents} workspacePublicId={workspacePublicId} /></div>;
}
