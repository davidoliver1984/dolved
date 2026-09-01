import { notFound } from "next/navigation";
import { BulkOperationDetail } from "@/components/BulkOperationDetail";
import { initialBulkOperation, userWorkspace } from "@/lib/server-api";

export default async function BulkOperationPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string; bulkOperationPublicId: string }> }>) {
  const { workspacePublicId, bulkOperationPublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace || workspace.role === "member") notFound();
  const operation = await initialBulkOperation(workspacePublicId, bulkOperationPublicId);
  if (!operation) notFound();
  return <BulkOperationDetail initial={operation} workspacePublicId={workspacePublicId} />;
}
