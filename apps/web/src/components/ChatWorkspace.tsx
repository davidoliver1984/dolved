"use client";

import {
  type FormEvent,
  type KeyboardEvent,
  useCallback,
  useEffect,
  useRef,
  useState,
} from "react";
import { firstError } from "@/lib/api";
import {
  type ChatStreamEvent,
  subscribeToGenerationRun,
} from "@/lib/conversation-stream";
import {
  cancelConversationRun,
  type Conversation,
  createConversation,
  getConversation,
  listConversations,
  retryConversationRun,
  sendConversationMessage,
} from "@/lib/conversations";

type Citation = {
  id?: string;
  provisional_reference?: string;
  reference?: string;
  document_id?: string | null;
  cited_text?: string;
  source_provenance?: unknown[];
};
type AnswerPart = { id?: string; text: string; citations: Citation[] };

type ChatServices = {
  list: typeof listConversations;
  create: typeof createConversation;
  get: typeof getConversation;
  send: typeof sendConversationMessage;
  retry: typeof retryConversationRun;
  cancel: typeof cancelConversationRun;
  subscribe: typeof subscribeToGenerationRun;
};

const defaultServices: ChatServices = {
  list: listConversations,
  create: createConversation,
  get: getConversation,
  send: sendConversationMessage,
  retry: retryConversationRun,
  cancel: cancelConversationRun,
  subscribe: subscribeToGenerationRun,
};

type ChatWorkspaceProps = {
  workspaceId: string;
  workspaceName: string;
  services?: ChatServices;
};

const progressLabels: Record<string, string> = {
  planning: "Understanding your question…",
  retrieving: "Finding eligible evidence…",
  generating: "Preparing a grounded answer…",
};

function idempotencyKey(): string {
  return crypto.randomUUID();
}

function eventParts(event: ChatStreamEvent): AnswerPart[] {
  if (event.type === "answer_part_accepted_for_display") {
    return [
      {
        text: String(event.payload.text ?? ""),
        citations: Array.isArray(event.payload.citations)
          ? (event.payload.citations as Citation[])
          : [],
      },
    ];
  }
  return Array.isArray(event.payload.parts)
    ? (event.payload.parts as AnswerPart[])
    : [];
}

export function ChatWorkspace({
  workspaceId,
  workspaceName,
  services = defaultServices,
}: ChatWorkspaceProps) {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [active, setActive] = useState<Conversation | null>(null);
  const [draft, setDraft] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [progress, setProgress] = useState<string | null>(null);
  const [provisionalParts, setProvisionalParts] = useState<AnswerPart[]>([]);
  const [completedParts, setCompletedParts] = useState<Record<string, AnswerPart[]>>({});
  const unsubscribe = useRef<null | (() => void)>(null);
  const transcript = useRef<HTMLDivElement>(null);

  const refreshList = useCallback(async () => {
    const items = await services.list(workspaceId);
    setConversations(items);
    return items;
  }, [services, workspaceId]);

  const openConversation = useCallback(
    async (conversationId: string) => {
      setError(null);
      setLoading(true);
      try {
        setActive(await services.get(workspaceId, conversationId));
      } catch (caught) {
        setError(firstError(caught));
      } finally {
        setLoading(false);
      }
    },
    [services, workspaceId],
  );

  useEffect(() => {
    let current = true;
    void services
      .list(workspaceId)
      .then(async (items) => {
        if (current) {
          setConversations(items);
          if (items[0]) {
            setActive(await services.get(workspaceId, items[0].id));
          }
        }
      })
      .catch((caught) => current && setError(firstError(caught)))
      .finally(() => current && setLoading(false));
    return () => {
      current = false;
      unsubscribe.current?.();
    };
  }, [refreshList, services, workspaceId]);

  useEffect(() => {
    if (transcript.current) {
      transcript.current.scrollTop = transcript.current.scrollHeight;
    }
  }, [active?.messages, provisionalParts]);

  const finishRun = useCallback(
    async (conversationId: string) => {
      setBusy(false);
      setProgress(null);
      setProvisionalParts([]);
      setActive(await services.get(workspaceId, conversationId));
      await refreshList();
    },
    [refreshList, services, workspaceId],
  );

  const watchRun = useCallback(
    (conversationId: string, runId: string) => {
      unsubscribe.current?.();
      unsubscribe.current = services.subscribe(
        workspaceId,
        conversationId,
        runId,
        (event) => {
          if (event.type === "run_progress") {
            const stage = String(event.payload.stage ?? "");
            setProgress(progressLabels[stage] ?? "Working on your answer…");
          } else if (event.type === "answer_part_accepted_for_display") {
            setProgress("Writing from the retrieved evidence…");
            setProvisionalParts((parts) => [...parts, ...eventParts(event)]);
          } else if (
            event.type === "answer_completed" ||
            event.type === "clarification_required"
          ) {
            setCompletedParts((current) => ({
              ...current,
              [runId]: eventParts(event),
            }));
            void finishRun(conversationId).catch((caught) =>
              setError(firstError(caught)),
            );
          } else if (event.type === "run_failed") {
            setError("The answer could not be completed. Your question is still saved and can be retried.");
            void finishRun(conversationId).catch((caught) =>
              setError(firstError(caught)),
            );
          } else if (event.type === "run_cancelled") {
            void finishRun(conversationId).catch((caught) =>
              setError(firstError(caught)),
            );
          } else if (event.type === "authorization_revoked") {
            setBusy(false);
            setProgress(null);
            setProvisionalParts([]);
            setError("Your access to this workspace has ended. No further answer content will be shown.");
          }
        },
        () => {
          setError("The live update connection was interrupted. Your conversation is safe and the connection is retrying.");
          setProgress("Reconnecting to live updates…");
        },
      );
    },
    [finishRun, services, workspaceId],
  );

  const newConversation = async () => {
    setBusy(true);
    setError(null);
    try {
      const created = await services.create(workspaceId);
      setActive({ ...created, messages: [], runs: [] });
      await refreshList();
    } catch (caught) {
      setError(firstError(caught));
    } finally {
      setBusy(false);
    }
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const message = draft.trim();
    if (!message || busy) return;
    setBusy(true);
    setError(null);
    setProgress("Sending your question…");
    setProvisionalParts([]);
    try {
      let conversation = active;
      if (!conversation) {
        conversation = await services.create(workspaceId);
        setActive({ ...conversation, messages: [], runs: [] });
      }
      const receipt = await services.send(
        workspaceId,
        conversation.id,
        message,
        idempotencyKey(),
      );
      setDraft("");
      setActive(await services.get(workspaceId, conversation.id));
      watchRun(conversation.id, receipt.run_id);
    } catch (caught) {
      setBusy(false);
      setProgress(null);
      setError(firstError(caught));
    }
  };

  const retry = async (runId: string) => {
    if (!active || busy) return;
    setBusy(true);
    setError(null);
    setProgress("Retrying your saved question…");
    try {
      const receipt = await services.retry(
        workspaceId,
        active.id,
        runId,
        idempotencyKey(),
      );
      watchRun(active.id, receipt.run_id);
    } catch (caught) {
      setBusy(false);
      setProgress(null);
      setError(firstError(caught));
    }
  };

  const cancel = async () => {
    const run = active?.runs?.find((item) => !item.completed_at);
    if (!active || !run) return;
    try {
      await services.cancel(workspaceId, active.id, run.id);
      setProgress("Cancelling…");
    } catch (caught) {
      setError(firstError(caught));
    }
  };

  const keyboardSubmit = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      event.currentTarget.form?.requestSubmit();
    }
  };

  const runsByMessage = new Map(
    (active?.runs ?? [])
      .filter((run) => run.assistant_message_id)
      .map((run) => [run.assistant_message_id, run]),
  );
  const failedRuns = (active?.runs ?? []).filter(
    (run) => run.status === "failed" && run.retryable,
  );

  return (
    <section className="chat-workspace" aria-labelledby="chat-heading">
      <aside className="conversation-sidebar" aria-label="Conversations">
        <div className="conversation-sidebar-heading">
          <div>
            <p className="eyebrow">Grounded chat</p>
            <h2 id="chat-heading">Ask {workspaceName}</h2>
          </div>
          <button className="primary-button compact" disabled={busy} onClick={newConversation} type="button">
            New chat
          </button>
        </div>
        <p className="scope-note">Scope: all documents eligible for this workspace and question.</p>
        <nav aria-label="Conversation history" className="conversation-list">
          {conversations.length === 0 && !loading ? (
            <p className="conversation-empty">No conversations yet.</p>
          ) : null}
          {conversations.map((conversation) => (
            <button
              aria-current={active?.id === conversation.id ? "page" : undefined}
              className={active?.id === conversation.id ? "conversation-link active" : "conversation-link"}
              key={conversation.id}
              onClick={() => void openConversation(conversation.id)}
              type="button"
            >
              <strong>{conversation.title || "New conversation"}</strong>
              <small>{new Date(conversation.updated_at).toLocaleDateString()}</small>
            </button>
          ))}
        </nav>
      </aside>

      <div className="chat-panel">
        <div aria-busy={loading} aria-live="polite" className="chat-transcript" ref={transcript}>
          {loading ? <p className="chat-placeholder">Loading conversation…</p> : null}
          {!loading && !(active?.messages?.length) ? (
            <div className="chat-empty">
              <span aria-hidden="true">?</span>
              <h3>What do you need to know?</h3>
              <p>Answers are grounded in eligible workspace evidence and include inspectable citation references.</p>
            </div>
          ) : null}
          {active?.messages?.map((message) => {
            const run = runsByMessage.get(message.id);
            const parts = run
              ? completedParts[run.id] ?? run.answer?.parts
              : undefined;
            return (
              <article className={`chat-message ${message.role}`} key={message.id}>
                <p className="message-role">{message.role === "user" ? "You" : "Dolved"}</p>
                {parts?.length ? parts.map((part, index) => (
                  <div className="answer-part" key={part.id ?? `${message.id}-${index}`}>
                    <p>{part.text}</p>
                    {part.citations.length ? (
                      <details className="citations">
                        <summary>{part.citations.length} {part.citations.length === 1 ? "citation" : "citations"}</summary>
                        <ol>
                          {part.citations.map((citation, citationIndex) => (
                            <li key={citation.id ?? citation.reference ?? citationIndex}>
                              <code>{citation.provisional_reference ?? citation.reference ?? citation.id}</code>
                              {citation.document_id ? <span>Document {citation.document_id}</span> : null}
                              {citation.cited_text ? <blockquote>{citation.cited_text}</blockquote> : null}
                              {citation.source_provenance?.length ? (
                                <details>
                                  <summary>Source location</summary>
                                  <pre>{JSON.stringify(citation.source_provenance, null, 2)}</pre>
                                </details>
                              ) : null}
                            </li>
                          ))}
                        </ol>
                      </details>
                    ) : null}
                  </div>
                )) : <p>{message.text}</p>}
              </article>
            );
          })}
          {provisionalParts.length ? (
            <article className="chat-message assistant provisional">
              <p className="message-role">Dolved · streaming</p>
              {provisionalParts.map((part, index) => <p key={index}>{part.text}</p>)}
            </article>
          ) : null}
          {progress ? <p className="chat-progress" role="status"><span aria-hidden="true" />{progress}</p> : null}
        </div>

        {error ? <div className="form-alert error chat-alert" role="alert">{error}</div> : null}
        {failedRuns.map((run) => (
          <button className="text-button retry-button" disabled={busy} key={run.id} onClick={() => void retry(run.id)} type="button">
            Retry failed answer
          </button>
        ))}
        <form className="chat-composer" onSubmit={submit}>
          <label htmlFor="chat-question">Ask a question</label>
          <textarea
            disabled={busy}
            id="chat-question"
            maxLength={8000}
            onChange={(event) => setDraft(event.target.value)}
            onKeyDown={keyboardSubmit}
            placeholder="Ask about an eligible policy or procedure…"
            rows={3}
            value={draft}
          />
          <div className="composer-actions">
            <small>Enter to send · Shift+Enter for a new line</small>
            {busy && active?.runs?.some((run) => !run.completed_at) ? (
              <button className="text-button" onClick={() => void cancel()} type="button">Cancel</button>
            ) : null}
            <button className="primary-button" disabled={busy || !draft.trim()} type="submit">Send</button>
          </div>
        </form>
      </div>
    </section>
  );
}
