"use client";

import { useRouter } from "next/navigation";
import { ChatWorkspace } from "@/components/ChatWorkspace";
import type { StarterQuestion } from "@/components/KnowledgeReadinessPanel";

export function RoutedChatWorkspace({ conversationId, searchableDocumentCount, starterQuestions, workspaceId, workspaceName }: Readonly<{ conversationId: string | null; searchableDocumentCount: number; starterQuestions: StarterQuestion[]; workspaceId: string; workspaceName: string }>) {
  const router = useRouter();
  return <ChatWorkspace initialConversationId={conversationId} onConversationCreated={(createdId) => router.replace(`/app/workspaces/${workspaceId}/conversations/${createdId}`)} searchableDocumentCount={searchableDocumentCount} showConversationNavigation={false} starterQuestions={starterQuestions} workspaceId={workspaceId} workspaceName={workspaceName} />;
}
