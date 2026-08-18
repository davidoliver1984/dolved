import { clientEnvironment } from "@/lib/env/client";

export type ChatStreamEventType =
  | "run_progress"
  | "answer_part_accepted_for_display"
  | "answer_completed"
  | "clarification_required"
  | "run_failed"
  | "run_cancelled";

export type ChatStreamEvent = {
  sequence: number;
  type: ChatStreamEventType;
  provisional: boolean;
  payload: Record<string, unknown>;
};

const terminal = new Set<ChatStreamEventType>([
  "answer_completed",
  "clarification_required",
  "run_failed",
  "run_cancelled",
]);

export function subscribeToGenerationRun(
  workspaceId: string,
  conversationId: string,
  runId: string,
  receive: (event: ChatStreamEvent) => void,
  fail: (event: Event) => void,
): () => void {
  const path = `/api/workspaces/${encodeURIComponent(workspaceId)}/conversations/${encodeURIComponent(conversationId)}/runs/${encodeURIComponent(runId)}/events`;
  const source = new EventSource(
    `${clientEnvironment.NEXT_PUBLIC_API_URL}${path}`,
    { withCredentials: true },
  );
  let sequence = 0;
  const accept = (message: MessageEvent<string>) => {
    try {
      const event = JSON.parse(message.data) as ChatStreamEvent;
      if (!Number.isInteger(event.sequence) || event.sequence <= sequence) return;
      sequence = event.sequence;
      receive(event);
      if (terminal.has(event.type)) source.close();
    } catch {
      source.close();
      fail(new Event("invalid_chat_stream_event"));
    }
  };
  for (const type of [
    "run_progress",
    "answer_part_accepted_for_display",
    "answer_completed",
    "clarification_required",
    "run_failed",
    "run_cancelled",
  ] satisfies ChatStreamEventType[]) {
    source.addEventListener(type, accept as EventListener);
  }
  source.onerror = fail;

  return () => source.close();
}
