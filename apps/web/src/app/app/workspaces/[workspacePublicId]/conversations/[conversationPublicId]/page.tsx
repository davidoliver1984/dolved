import { notFound } from "next/navigation";
import { RoutedChatWorkspace } from "@/components/RoutedChatWorkspace";
import { initialConversation, initialDocumentMetadata, userWorkspace, workspaceKnowledgeReadiness, workspaceStarterQuestions } from "@/lib/server-api";

export default async function ConversationPage({ params }: Readonly<{ params: Promise<{ conversationPublicId: string; workspacePublicId: string }> }>) {
  const { conversationPublicId, workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const [conversation, readiness, starterQuestions, metadata] = await Promise.all([initialConversation(workspacePublicId, conversationPublicId), workspaceKnowledgeReadiness(workspacePublicId), workspaceStarterQuestions(workspacePublicId), initialDocumentMetadata(workspacePublicId)]);
  if (!conversation) notFound();
  return <RoutedChatWorkspace conversationId={conversationPublicId} locations={metadata.locations} searchableDocumentCount={readiness.searchable_document_count} starterQuestions={starterQuestions} workspaceId={workspace.public_id} workspaceName={workspace.name} />;
}
