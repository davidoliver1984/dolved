import { notFound } from "next/navigation";
import { RoutedChatWorkspace } from "@/components/RoutedChatWorkspace";
import { initialConversation, initialDocumentMetadata, userWorkspace } from "@/lib/server-api";

export default async function ConversationPage({ params }: Readonly<{ params: Promise<{ conversationPublicId: string; workspacePublicId: string }> }>) {
  const { conversationPublicId, workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const [conversation, metadata] = await Promise.all([initialConversation(workspacePublicId, conversationPublicId), initialDocumentMetadata(workspacePublicId)]);
  if (!conversation) notFound();
  return <RoutedChatWorkspace conversationId={conversationPublicId} locations={metadata.locations} workspaceId={workspace.public_id} workspaceName={workspace.name} />;
}
