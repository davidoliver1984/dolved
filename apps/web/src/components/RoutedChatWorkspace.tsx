"use client";

import { useRouter } from "next/navigation";
import { ChatWorkspace } from "@/components/ChatWorkspace";

export function RoutedChatWorkspace({ conversationId, locations, workspaceId, workspaceName }: Readonly<{ conversationId: string | null; locations: Array<{ public_id: string; name: string }>; workspaceId: string; workspaceName: string }>) {
  const router = useRouter();
  return <ChatWorkspace initialConversationId={conversationId} locations={locations} onConversationCreated={(createdId) => router.replace(`/app/workspaces/${workspaceId}/conversations/${createdId}`)} showConversationNavigation={false} workspaceId={workspaceId} workspaceName={workspaceName} />;
}
