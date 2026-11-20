"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { ChatWorkspace } from "@/components/ChatWorkspace";
import { type EffectiveBranding, workspaceBranding } from "@/lib/api";

export function RoutedChatWorkspace({ conversationId, locations, workspaceId, workspaceName }: Readonly<{ conversationId: string | null; locations: Array<{ public_id: string; name: string }>; workspaceId: string; workspaceName: string }>) {
  const router = useRouter();
  const [branding, setBranding] = useState<EffectiveBranding | null>(null);

  useEffect(() => {
    let active = true;
    void workspaceBranding(workspaceId).then((resolved) => {
      if (active) setBranding(resolved);
    }).catch(() => {
      if (active) setBranding(null);
    });
    return () => { active = false; };
  }, [workspaceId]);

  return <ChatWorkspace
    initialConversationId={conversationId}
    locations={locations}
    onConversationCreated={(createdId) => router.replace(`/app/workspaces/${workspaceId}/conversations/${createdId}`)}
    showConversationNavigation={false}
    welcomeHeading={branding?.configuration.chat_welcome_heading}
    welcomeIntroduction={branding?.configuration.chat_welcome_introduction}
    workspaceId={workspaceId}
    workspaceName={workspaceName}
  />;
}
