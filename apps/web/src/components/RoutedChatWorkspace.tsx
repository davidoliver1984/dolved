"use client";

import { useRouter } from "next/navigation";
import { ChatWorkspace } from "@/components/ChatWorkspace";
import type { StarterQuestion } from "@/components/KnowledgeReadinessPanel";

export function RoutedChatWorkspace({ conversationId, locations, searchableDocumentCount, starterQuestions, workspaceId, workspaceName }: Readonly<{ conversationId: string | null; locations: Array<{ public_id: string; name: string }>; searchableDocumentCount: number; starterQuestions: StarterQuestion[]; workspaceId: string; workspaceName: string }>) {
  const router = useRouter();
  return <ChatWorkspace initialConversationId={conversationId} locations={locations} onConversationCreated={(createdId) => router.replace(`/app/workspaces/${workspaceId}/conversations/${createdId}`)} searchableDocumentCount={searchableDocumentCount} showConversationNavigation={false} starterQuestions={starterQuestions} workspaceId={workspaceId} workspaceName={workspaceName} />;
}
