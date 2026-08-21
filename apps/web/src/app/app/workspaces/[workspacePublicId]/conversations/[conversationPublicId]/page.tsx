import { notFound } from "next/navigation";
import { RoutedChatWorkspace } from "@/components/RoutedChatWorkspace";
import { initialConversation, userWorkspace } from "@/lib/server-api";

export default async function ConversationPage({ params }: Readonly<{ params: Promise<{ conversationPublicId: string; workspacePublicId: string }> }>) {
  const { conversationPublicId, workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const conversation = await initialConversation(workspacePublicId, conversationPublicId);
  if (!conversation) notFound();
  return <RoutedChatWorkspace conversationId={conversationPublicId} workspaceId={workspace.public_id} workspaceName={workspace.name} />;
}
