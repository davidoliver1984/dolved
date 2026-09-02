import { notFound } from "next/navigation";
import { GovernanceActionableWork } from "@/components/GovernanceActionableWork";
import { userWorkspace, workspaceGovernanceActionableWork } from "@/lib/server-api";

export default async function DocumentsAttentionPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  const work = await workspaceGovernanceActionableWork(workspacePublicId);
  return <GovernanceActionableWork data={work} workspacePublicId={workspacePublicId} />;
}
