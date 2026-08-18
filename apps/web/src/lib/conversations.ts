import { apiFetch } from "@/lib/api";

export type ConversationMessage = {
  id: string;
  ordinal: number;
  role: "user" | "assistant";
  kind: string | null;
  text: string;
  in_reply_to_message_id: string | null;
  created_at: string;
};

export type ConversationRun = {
  id: string;
  user_message_id: string;
  assistant_message_id: string | null;
  retry_of_run_id: string | null;
  status: string;
  failure_code: string | null;
  delivery_mode: string;
  retryable: boolean;
  answer?: GroundedAnswer | null;
  created_at: string;
  completed_at: string | null;
};

export type GroundedCitation = {
  id: string;
  document_id: string | null;
  cited_text: string;
  source_provenance: unknown[];
};

export type GroundedAnswerPart = {
  id: string;
  text: string;
  citations: GroundedCitation[];
};

export type GroundedAnswer = {
  id: string;
  outcome: string;
  unsupported_aspects: string[];
  insufficiency_reason: string | null;
  parts: GroundedAnswerPart[];
};

export type Conversation = {
  id: string;
  title: string | null;
  status: string;
  created_at: string;
  updated_at: string;
  messages?: ConversationMessage[];
  runs?: ConversationRun[];
};

export type RunReceipt = { run_id: string; status: string };

type Collection<T> = { data: T[] };
type Resource<T> = { data: T };

const workspacePath = (workspaceId: string) =>
  `/api/workspaces/${encodeURIComponent(workspaceId)}`;

export async function listConversations(
  workspaceId: string,
): Promise<Conversation[]> {
  const response = await apiFetch<Collection<Conversation>>(
    `${workspacePath(workspaceId)}/conversations`,
  );
  return response.data;
}

export async function createConversation(
  workspaceId: string,
): Promise<Conversation> {
  const response = await apiFetch<Resource<Conversation>>(
    `${workspacePath(workspaceId)}/conversations`,
    { method: "POST" },
  );
  return response.data;
}

export async function getConversation(
  workspaceId: string,
  conversationId: string,
): Promise<Conversation> {
  const response = await apiFetch<Resource<Conversation>>(
    `${workspacePath(workspaceId)}/conversations/${encodeURIComponent(conversationId)}`,
  );
  return response.data;
}

export async function sendConversationMessage(
  workspaceId: string,
  conversationId: string,
  message: string,
  idempotencyKey: string,
): Promise<RunReceipt> {
  const response = await apiFetch<Resource<RunReceipt>>(
    `${workspacePath(workspaceId)}/conversations/${encodeURIComponent(conversationId)}/messages`,
    {
      method: "POST",
      body: JSON.stringify({ message, idempotency_key: idempotencyKey }),
    },
  );
  return response.data;
}

export async function retryConversationRun(
  workspaceId: string,
  conversationId: string,
  runId: string,
  idempotencyKey: string,
): Promise<RunReceipt> {
  const response = await apiFetch<Resource<RunReceipt>>(
    `${workspacePath(workspaceId)}/conversations/${encodeURIComponent(conversationId)}/runs/${encodeURIComponent(runId)}/retry`,
    {
      method: "POST",
      body: JSON.stringify({ idempotency_key: idempotencyKey }),
    },
  );
  return response.data;
}

export async function cancelConversationRun(
  workspaceId: string,
  conversationId: string,
  runId: string,
): Promise<RunReceipt> {
  const response = await apiFetch<Resource<RunReceipt>>(
    `${workspacePath(workspaceId)}/conversations/${encodeURIComponent(conversationId)}/runs/${encodeURIComponent(runId)}/cancel`,
    { method: "POST" },
  );
  return response.data;
}
