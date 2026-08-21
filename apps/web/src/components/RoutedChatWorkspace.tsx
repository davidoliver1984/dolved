"use client";

import { useRouter } from "next/navigation";
import { ChatWorkspace } from "@/components/ChatWorkspace";

export function RoutedChatWorkspace({ conversationId, workspaceId, workspaceName }: Readonly<{ conversationId: string | null; workspaceId: string; workspaceName: string }>) {
  const router = useRouter();
  return <ChatWorkspace initialConversationId={conversationId} onConversationCreated={(createdId) => router.replace(`/app/workspaces/${workspaceId}/conversations/${createdId}`)} showConversationNavigation={false} workspaceId={workspaceId} workspaceName={workspaceName} />;
}
