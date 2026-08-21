import { notFound } from "next/navigation";
import { RoutedChatWorkspace } from "@/components/RoutedChatWorkspace";
import { userWorkspace } from "@/lib/server-api";

export default async function WorkspacePage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  return <RoutedChatWorkspace conversationId={null} workspaceId={workspace.public_id} workspaceName={workspace.name} />;
}
