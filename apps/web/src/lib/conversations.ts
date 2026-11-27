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
  interaction?: { progress: "dify_blocking" | "custom_pipeline"; stop_action: "stop_waiting" | "cancel" };
  presentation?: "saved-message-v1" | null;
  retryable: boolean;
  provisional_content_retracted: boolean;
  feedback?: AnswerFeedback | null;
  answer?: GroundedAnswer | null;
  created_at: string;
  completed_at: string | null;
};

export type AnswerFeedback = {
  rating: "helpful" | "not_helpful";
  reason_code: "inaccurate" | "incomplete" | "unclear" | "outdated" | "other" | null;
  comment: string | null;
  submitted_at: string;
};

export type GroundedCitation = {
  id: string;
  document_id: string | null;
  display_name: string;
  type_label: string;
  size_bytes: number | null;
  source_state: "available" | "removed" | "unavailable";
  source_route: string | null;
  cited_text: string;
  source_provenance: unknown[];
  provenance_label: string;
};

export type GroundedAnswerPart = {
  id: string;
  text: string;
  citations: GroundedCitation[];
};

export type GroundedAnswer = {
  id: string;
  outcome: string;
  presentation?: "saved-message-v1";
  text?: string;
  citations?: GroundedCitation[];
  scope_citations?: Array<{ snapshot_digest: string; policy_title: string; document_version_id: string;
    snapshot: { scope: string }; source_route: string | null; source_state: string }>;
  unsupported_aspects?: string[];
  insufficiency_reason?: string | null;
  parts?: GroundedAnswerPart[];
  sources_retrieved?: RetrievedSource[];
};

export type RetrievedSource = {
  id: string;
  rank: number;
  score: number | null;
  document_id: string | null;
  display_name: string;
  source_state: "available" | "removed" | "unavailable";
  source_route: string | null;
  retrieved_text: string;
};

export type SiteContext = {
  scope_key: string;
  location_id: string | null;
  name: string;
  status: "active" | "retired";
};

export type ConversationContinuation = {
  allowed: boolean;
  reason: "remote_history_inconsistent" | "location_unavailable" | "conversation_not_active" | null;
  message: string | null;
  start_new_required: boolean;
};

export type Conversation = {
  id: string;
  title: string | null;
  status: string;
  created_at: string;
  updated_at: string;
  site_context?: SiteContext;
  continuation?: ConversationContinuation;
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
  selectedLocationId: string | null = null,
): Promise<Conversation> {
  const response = await apiFetch<Resource<Conversation>>(
    `${workspacePath(workspaceId)}/conversations`,
    {
      method: "POST",
      body: JSON.stringify({ selected_location_id: selectedLocationId }),
    },
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

export async function updateAnswerFeedback(
  workspaceId: string,
  conversationId: string,
  runId: string,
  feedback: Pick<AnswerFeedback, "rating" | "reason_code" | "comment">,
): Promise<AnswerFeedback> {
  const response = await apiFetch<Resource<AnswerFeedback>>(
    `${workspacePath(workspaceId)}/conversations/${encodeURIComponent(conversationId)}/runs/${encodeURIComponent(runId)}/feedback`,
    { method: "PUT", body: JSON.stringify(feedback) },
  );
  return response.data;
}

export async function removeAnswerFeedback(
  workspaceId: string,
  conversationId: string,
  runId: string,
): Promise<void> {
  await apiFetch(
    `${workspacePath(workspaceId)}/conversations/${encodeURIComponent(conversationId)}/runs/${encodeURIComponent(runId)}/feedback`,
    { method: "DELETE" },
  );
}
