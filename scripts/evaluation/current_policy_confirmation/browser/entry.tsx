import React from "react";
import { createRoot } from "react-dom/client";
import { ChatWorkspace } from "@/components/ChatWorkspace";
import "@/app/globals.css";
const params = new URLSearchParams(location.search);
createRoot(document.getElementById("root")!).render(<ChatWorkspace
  workspaceId={params.get("workspace")!} workspaceName="Disposable verification workspace"
  initialConversationId={params.get("conversation")}
  onConversationCreated={(id) => { params.set("conversation", id); history.replaceState(null, "", `?${params}`); }} />);
