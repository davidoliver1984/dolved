"use client";

import { Inbox, RefreshCw, Send, X } from "lucide-react";
import {
  type FormEvent,
  type KeyboardEvent,
  useCallback,
  useEffect,
  useRef,
  useState,
} from "react";
import { firstError } from "@/lib/api";
import { formatDate } from "@/lib/date";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { CitationButton, CitationChip } from "@/components/ui/citation-chip";
import { EmptyState } from "@/components/ui/empty-state";
import { EvidenceItem } from "@/components/ui/evidence-item";
import { Notice } from "@/components/ui/notice";
import { KnowledgeReadinessPanel, type StarterQuestion } from "@/components/KnowledgeReadinessPanel";
import { Skeleton } from "@/components/ui/skeleton";
import { StatusBadge } from "@/components/ui/status-badge";
import { StreamingStatus } from "@/components/ui/streaming-status";
import { Textarea } from "@/components/ui/textarea";
import {
  type ChatStreamEvent,
  subscribeToGenerationRun,
} from "@/lib/conversation-stream";
import {
  cancelConversationRun,
  type Conversation,
  type ConversationRun,
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
  display_name?: string;
  type_label?: string;
  size_bytes?: number | null;
  source_state?: "available" | "removed" | "unavailable";
  source_route?: string | null;
  cited_text?: string;
  source_provenance?: unknown[];
  provenance_label?: string;
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
  initialConversationId?: string | null;
  onConversationCreated?: (conversationId: string) => void;
  showConversationNavigation?: boolean;
  workspaceId: string;
  workspaceName: string;
  services?: ChatServices;
  searchableDocumentCount?: number;
  starterQuestions?: StarterQuestion[];
};

const progressLabels: Record<string, string> = {
  queued: "Your question is queued…",
  contextualising: "Understanding your question…",
  planning: "Understanding your question…",
  retrieving: "Finding eligible evidence…",
  preparing_evidence: "Preparing eligible evidence…",
  generating: "Preparing a grounded answer…",
  validating: "Validating the grounded answer…",
};

const timeoutFailureCodes = new Set(["provider_response_timeout", "run_timeout"]);

function bytes(value: number | null | undefined): string | null {
  if (value === null || value === undefined) return null;
  if (value < 1024) return `${value} B`;
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function failurePresentation(run: ConversationRun): { title: string; description: string } {
  if (run.provisional_content_retracted) {
    return {
      title: "Provisional answer retracted",
      description: "The attempted answer could not be safely completed, so its provisional content was removed.",
    };
  }
  if (run.failure_code && timeoutFailureCodes.has(run.failure_code)) {
    return {
      title: "Answer attempt timed out",
      description: "The attempt exceeded its allowed time. Your question is still saved and can be retried.",
    };
  }
  return {
    title: "Answer could not be completed",
    description: "The provider or generation process failed. Your question is still saved and can be retried where allowed.",
  };
}

function CitationEvidence({ citation, index }: Readonly<{ citation: Citation; index: number }>) {
  const [open, setOpen] = useState(false);
  const label = `[${index + 1}]`;
  const size = bytes(citation.size_bytes);
  const metadata = [citation.type_label ?? "Document", size].filter(Boolean).join(" · ");
  const unavailableLabel = citation.source_state === "removed"
    ? "Source removed; preserved evidence remains available"
    : "Source unavailable";

  return (
    <li className="grid gap-2">
      <CitationButton
        aria-expanded={open}
        aria-label={`${label}, ${open ? "hide" : "show"} source evidence`}
        className="w-fit"
        onClick={() => setOpen((value) => !value)}
      >
        {label}
      </CitationButton>
      {open ? (
        <EvidenceItem
          excerpt={citation.cited_text}
          metadata={metadata}
          provenance={citation.provenance_label}
          sourceHref={citation.source_route ?? undefined}
          sourceUnavailableLabel={unavailableLabel}
          title={citation.display_name ?? "Document source"}
        />
      ) : null}
    </li>
  );
}

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
  initialConversationId,
  onConversationCreated,
  showConversationNavigation = true,
  workspaceId,
  workspaceName,
  services = defaultServices,
  searchableDocumentCount,
  starterQuestions = [],
}: ChatWorkspaceProps) {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [active, setActive] = useState<Conversation | null>(null);
  const [draft, setDraft] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [progress, setProgress] = useState<string | null>(null);
  const [announcement, setAnnouncement] = useState("");
  const [provisionalParts, setProvisionalParts] = useState<AnswerPart[]>([]);
  const [completedParts, setCompletedParts] = useState<Record<string, AnswerPart[]>>({});
  const [accessRevoked, setAccessRevoked] = useState(false);
  const unsubscribe = useRef<null | (() => void)>(null);
  const transcript = useRef<HTMLDivElement>(null);
  const questionInput = useRef<HTMLTextAreaElement>(null);
  const provisionalPartsRef = useRef<AnswerPart[]>([]);

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
          const selectedId = initialConversationId === undefined
            ? items[0]?.id
            : initialConversationId;
          if (selectedId) {
            setActive(await services.get(workspaceId, selectedId));
          } else {
            setActive(null);
          }
        }
      })
      .catch((caught) => current && setError(firstError(caught)))
      .finally(() => current && setLoading(false));
    return () => {
      current = false;
      unsubscribe.current?.();
    };
  }, [initialConversationId, refreshList, services, workspaceId]);

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
      provisionalPartsRef.current = [];
      setActive(await services.get(workspaceId, conversationId));
      setCompletedParts({});
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
            const label = progressLabels[stage] ?? "Working on your answer…";
            setProgress(label);
            setAnnouncement(label);
          } else if (event.type === "answer_part_accepted_for_display") {
            setProgress("Writing from the retrieved evidence…");
            setAnnouncement("Dolved is writing from the retrieved evidence.");
            setProvisionalParts((parts) => {
              const updated = [...parts, ...eventParts(event)];
              provisionalPartsRef.current = updated;
              return updated;
            });
          } else if (
            event.type === "answer_completed" ||
            event.type === "clarification_required"
          ) {
            setCompletedParts((current) => ({
              ...current,
              [runId]: eventParts(event),
            }));
            setAnnouncement(event.type === "clarification_required" ? "Clarification required." : "Answer complete.");
            void finishRun(conversationId).catch((caught) =>
              setError(firstError(caught)),
            );
          } else if (event.type === "run_failed") {
            const failureCode = String(event.payload.failure_code ?? "");
            setAnnouncement(provisionalPartsRef.current.length > 0
              ? "Provisional answer retracted because it could not be safely completed."
              : timeoutFailureCodes.has(failureCode)
                ? "Answer attempt timed out."
                : "Answer could not be completed.");
            void finishRun(conversationId).catch((caught) =>
              setError(firstError(caught)),
            );
          } else if (event.type === "run_cancelled") {
            setError("Answer generation was cancelled. Your question remains in the conversation.");
            setAnnouncement("Answer generation cancelled.");
            void finishRun(conversationId).catch((caught) =>
              setError(firstError(caught)),
            );
          } else if (event.type === "authorization_revoked") {
            setAccessRevoked(true);
            setBusy(false);
            setProgress(null);
            setProvisionalParts([]);
            provisionalPartsRef.current = [];
            setError("Your access to this workspace has ended. No further answer content will be shown.");
            setAnnouncement("Workspace access ended.");
          }
        },
        () => {
          setError("The live update connection was interrupted. Your conversation is safe and the connection is retrying.");
          setProgress("Reconnecting to live updates…");
          setAnnouncement("Live updates interrupted. Reconnecting.");
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
    if (!message || busy || accessRevoked) return;
    setBusy(true);
    setError(null);
    setProgress("Sending your question…");
    setAnnouncement("Sending your question.");
    setProvisionalParts([]);
    provisionalPartsRef.current = [];
    try {
      let conversation = active;
      if (!conversation) {
        conversation = await services.create(workspaceId);
        setActive({ ...conversation, messages: [], runs: [] });
        onConversationCreated?.(conversation.id);
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
    setAnnouncement("Retrying your saved question.");
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
      setAnnouncement("Cancelling answer generation.");
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

  const focusQuestion = () => questionInput.current?.focus();
  const chooseQuestion = (question: string) => {
    setDraft(question);
    requestAnimationFrame(focusQuestion);
  };

  const runsByMessage = new Map(
    (active?.runs ?? [])
      .filter((run) => run.assistant_message_id)
      .map((run) => [run.assistant_message_id, run]),
  );
  const failedRuns = (active?.runs ?? []).filter((run) => run.status === "failed");

  return (
    <section className={showConversationNavigation ? "grid min-h-[42rem] overflow-hidden rounded-xl border border-border bg-card lg:grid-cols-[18rem_1fr]" : "grid min-h-[42rem]"} aria-labelledby="chat-heading">
      {showConversationNavigation ? <aside className="border-r border-border bg-surface-raised p-4" aria-label="Conversations">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Grounded chat</p>
            <h2 id="chat-heading">Ask {workspaceName}</h2>
          </div>
          <Button disabled={busy} onClick={newConversation} size="sm" type="button">
            New chat
          </Button>
        </div>
        <p className="mt-4 text-xs leading-5 text-foreground-muted">Scope: all documents eligible for this workspace and question.</p>
        <nav aria-label="Conversation history" className="mt-5 grid gap-2">
          {conversations.length === 0 && !loading ? (
            <p className="text-sm text-foreground-muted">No conversations yet.</p>
          ) : null}
          {conversations.map((conversation) => (
            <button
              aria-current={active?.id === conversation.id ? "page" : undefined}
              className={active?.id === conversation.id
                ? "grid min-h-11 rounded-lg border border-brand bg-brand/10 px-3 py-2 text-left"
                : "grid min-h-11 rounded-lg border border-transparent px-3 py-2 text-left hover:bg-surface-hover"}
              key={conversation.id}
              onClick={() => void openConversation(conversation.id)}
              type="button"
            >
              <strong>{conversation.title || "New conversation"}</strong>
              <small>{formatDate(conversation.updated_at)}</small>
            </button>
          ))}
        </nav>
      </aside> : null}

      <div className="grid min-h-0 grid-rows-[auto_1fr_auto_auto] gap-4">
        {!showConversationNavigation ? <header className="mb-5"><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Grounded chat</p><h1 className="mt-2 text-3xl font-semibold" id="chat-heading">Ask {workspaceName}</h1><p className="mt-2 text-sm text-foreground-muted">Answers use only evidence eligible for this workspace and question.</p></header> : null}
        {searchableDocumentCount !== undefined ? <KnowledgeReadinessPanel onAsk={focusQuestion} onChooseQuestion={chooseQuestion} searchableDocumentCount={searchableDocumentCount} starterQuestions={starterQuestions} workspaceId={workspaceId} /> : null}
        <p aria-atomic="true" aria-live="polite" className="sr-only" role="status">{announcement}</p>
        <div aria-busy={loading} className="grid min-h-96 content-start gap-4 overflow-y-auto rounded-xl border border-border bg-surface p-4 sm:p-6" ref={transcript}>
          {loading ? <div aria-label="Loading conversation" className="grid gap-4"><Skeleton className="h-20 w-3/4" /><Skeleton className="ml-auto h-16 w-2/3" /><Skeleton className="h-28 w-4/5" /></div> : null}
          {!loading && !(active?.messages?.length) ? (
            <EmptyState description="Answers use eligible workspace evidence and keep their citation references close." icon={Inbox} title="What do you need to know?" />
          ) : null}
          {active?.messages?.map((message) => {
            const run = runsByMessage.get(message.id);
            const parts = run
              ? completedParts[run.id] ?? run.answer?.parts
              : undefined;
            return (
              <Card className={message.role === "user" ? "ml-auto w-fit max-w-[85%] border-brand/30 bg-brand/10 p-4" : "max-w-[92%] p-4"} key={message.id}>
                <div className="mb-2 flex items-center gap-2"><p className="text-xs font-bold uppercase tracking-[0.12em] text-foreground-muted">{message.role === "user" ? "You" : "Dolved"}</p>{run?.answer?.outcome ? <StatusBadge status={run.answer.outcome === "answered" ? "success" : "warning"}>{run.answer.outcome.replaceAll("_", " ")}</StatusBadge> : null}</div>
                {parts?.length ? parts.map((part, index) => (
                  <div className="grid gap-3" key={part.id ?? `${message.id}-${index}`}>
                    <p>{part.text}</p>
                    {part.citations.length ? (
                      <div className="mt-3 rounded-lg border border-border bg-surface-raised p-3">
                        <p className="text-sm font-semibold">{part.citations.length} {part.citations.length === 1 ? "source" : "sources"}</p>
                        <ol className="mt-3 flex flex-wrap gap-2">
                          {part.citations.map((citation, citationIndex) => (
                            citation.id
                              ? <CitationEvidence citation={citation} index={citationIndex} key={citation.id ?? citationIndex} />
                              : <li key={citation.reference ?? citationIndex}><CitationChip label={citation.provisional_reference ?? citation.reference ?? `[${citationIndex + 1}]`} provisional /></li>
                          ))}
                        </ol>
                      </div>
                    ) : null}
                  </div>
                )) : <p className="leading-7">{message.text}</p>}
                {run?.answer?.insufficiency_reason ? <Notice className="mt-3" tone="warning">{run.answer.insufficiency_reason}</Notice> : null}
                {run?.answer?.unsupported_aspects.length ? <p className="mt-3 text-sm text-foreground-muted">Not established by the available evidence: {run.answer.unsupported_aspects.join(", ")}</p> : null}
              </Card>
            );
          })}
          {provisionalParts.length ? (
            <Card className="max-w-[92%] border-dashed p-4">
              <p className="mb-2 text-xs font-bold uppercase tracking-[0.12em] text-foreground-muted">Dolved · streaming</p>
              {provisionalParts.map((part, index) => <p key={index}>{part.text}</p>)}
            </Card>
          ) : null}
          {failedRuns.map((run) => {
            const presentation = failurePresentation(run);
            return (
              <Notice key={run.id} tone="destructive">
                <strong>{presentation.title}</strong>
                <p className="mt-1">{presentation.description}</p>
                {run.retryable ? <Button className="mt-3" disabled={busy || accessRevoked} onClick={() => void retry(run.id)} type="button" variant="outline"><RefreshCw />Retry answer</Button> : null}
              </Notice>
            );
          })}
          {progress ? <StreamingStatus label={progress} /> : null}
        </div>

        {error ? <Notice tone={accessRevoked ? "warning" : "destructive"}>{accessRevoked ? <div><strong>Workspace access ended</strong><p className="mt-1">{error}</p></div> : error}</Notice> : null}
        <form className="grid gap-3 rounded-xl border border-border bg-card p-4" onSubmit={submit}>
          <label className="text-sm font-semibold" htmlFor="chat-question">Ask a question</label>
          <Textarea
            disabled={busy || accessRevoked}
            id="chat-question"
            maxLength={8000}
            onChange={(event) => setDraft(event.target.value)}
            onKeyDown={keyboardSubmit}
            placeholder="Ask about an eligible policy or procedure…"
            ref={questionInput}
            rows={3}
            value={draft}
          />
          <div className="flex flex-wrap items-center justify-between gap-3">
            <small className="text-foreground-muted">{accessRevoked ? "You no longer have access to send messages." : "Enter to send · Shift+Enter for a new line"}</small>
            {busy && active?.runs?.some((run) => !run.completed_at) ? (
              <Button onClick={() => void cancel()} type="button" variant="ghost"><X />Cancel</Button>
            ) : null}
            <Button disabled={busy || accessRevoked || !draft.trim()} type="submit"><Send />Send</Button>
          </div>
        </form>
      </div>
    </section>
  );
}
