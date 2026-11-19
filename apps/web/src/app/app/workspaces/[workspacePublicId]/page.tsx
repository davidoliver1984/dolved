import { notFound } from "next/navigation";
import { RoutedChatWorkspace } from "@/components/RoutedChatWorkspace";
import { initialDocumentMetadata, userWorkspace, workspaceKnowledgeReadiness, workspaceStarterQuestions } from "@/lib/server-api";

export default async function WorkspacePage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const [readiness, starterQuestions, metadata] = await Promise.all([workspaceKnowledgeReadiness(workspacePublicId), workspaceStarterQuestions(workspacePublicId), initialDocumentMetadata(workspacePublicId)]);
  return <RoutedChatWorkspace conversationId={null} locations={metadata.locations} searchableDocumentCount={readiness.searchable_document_count} starterQuestions={starterQuestions} workspaceId={workspace.public_id} workspaceName={workspace.name} />;
}
