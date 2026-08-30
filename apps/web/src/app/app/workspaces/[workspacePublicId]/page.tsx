import { notFound } from "next/navigation";
import { RoutedChatWorkspace } from "@/components/RoutedChatWorkspace";
import { userWorkspace, workspaceKnowledgeReadiness, workspaceStarterQuestions } from "@/lib/server-api";

export default async function WorkspacePage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const [readiness, starterQuestions] = await Promise.all([workspaceKnowledgeReadiness(workspacePublicId), workspaceStarterQuestions(workspacePublicId)]);
  return <RoutedChatWorkspace conversationId={null} searchableDocumentCount={readiness.searchable_document_count} starterQuestions={starterQuestions} workspaceId={workspace.public_id} workspaceName={workspace.name} />;
}
