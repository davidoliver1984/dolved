import { notFound } from "next/navigation";
import { DocumentAdministration } from "@/components/DocumentAdministration";
import { ThemeToggle } from "@/components/ThemeToggle";
import { Wordmark } from "@/components/Wordmark";
import { WorkspaceAdministration } from "@/components/WorkspaceAdministration";
import { WorkspaceUsage } from "@/components/WorkspaceUsage";
import { Button } from "@/components/ui/button";
import type { DocumentPage, WorkspaceAdministrationSnapshot, WorkspaceUsageSnapshot } from "@/lib/api";

export const metadata = { title: "Administration design review", robots: { index: false, follow: false } };

const administration: WorkspaceAdministrationSnapshot = {
  memberships: [
    { public_id: "owner", user: { name: "David Oliver", email: "david@dolved.test" }, role: "owner", joined_at: "2026-08-01T09:00:00Z", capabilities: { change_role: false, remove: false, transfer_ownership: false } },
    { public_id: "admin", user: { name: "Maya Chen", email: "maya@dolved.test" }, role: "admin", joined_at: "2026-08-04T09:00:00Z", capabilities: { change_role: true, remove: true, transfer_ownership: true } },
    { public_id: "member", user: { name: "Alex Morgan", email: "alex@dolved.test" }, role: "member", joined_at: "2026-08-12T09:00:00Z", capabilities: { change_role: true, remove: true, transfer_ownership: true } },
  ],
  invitations: [
    { public_id: "pending", invited_email: "new.member@example.test", intended_role: "member", status: "pending", expires_at: "2026-08-28T09:00:00Z", created_at: "2026-08-21T09:00:00Z", capabilities: { revoke: true } },
    { public_id: "accepted", invited_email: "accepted@example.test", intended_role: "member", status: "accepted", expires_at: "2026-08-25T09:00:00Z", created_at: "2026-08-18T09:00:00Z", capabilities: { revoke: false } },
    { public_id: "expired", invited_email: "expired@example.test", intended_role: "admin", status: "expired", expires_at: "2026-08-20T09:00:00Z", created_at: "2026-08-13T09:00:00Z", capabilities: { revoke: false } },
  ],
};

const documents: DocumentPage = {
  data: [
    { public_id: "indexed", source_filename: "Medication administration procedure.pdf", media_type: "application/pdf", size_bytes: 438272, status: "indexed", governance_status: "approved", failure_category: null, failure_message: null, extraction_warnings: [], created_by: { name: "David Oliver" }, deletion: null, capabilities: { retry: false, delete: true }, created_at: "2026-08-12T09:30:00Z", updated_at: "2026-08-12T09:34:00Z" },
    { public_id: "failed", source_filename: "Safeguarding guidance.docx", media_type: "application/vnd.openxmlformats-officedocument.wordprocessingml.document", size_bytes: 98304, status: "failed", governance_status: "draft", failure_category: "extraction_failed", failure_message: "The document could not be extracted safely. Review the source and retry.", extraction_warnings: [{ code: "embedded_media_skipped", message: "One embedded object was not extracted." }], created_by: { name: "Maya Chen" }, deletion: null, capabilities: { retry: true, delete: true }, created_at: "2026-08-20T14:00:00Z", updated_at: "2026-08-20T14:02:00Z" },
    { public_id: "deleting", source_filename: "Superseded infection-control memo.pdf", media_type: "application/pdf", size_bytes: 64120, status: "deleting", governance_status: "superseded", failure_category: null, failure_message: null, extraction_warnings: [], created_by: { name: "Alex Morgan" }, deletion: { public_id: "deletion", status: "processing", failure_code: null, stuck: false }, capabilities: { retry: false, delete: false }, created_at: "2026-08-02T11:00:00Z", updated_at: "2026-08-21T08:00:00Z" },
  ],
  meta: { current_page: 1, last_page: 1, per_page: 25, total: 3 },
};

const usage: WorkspaceUsageSnapshot = {
  range: { key: "30d", start: "2026-07-22T00:00:00Z", end: "2026-08-21T00:00:00Z", semantics: "[start,end) UTC" }, as_of: "2026-08-21T16:00:00Z",
  gauges: { active_documents: 94, logical_source_bytes: 18530224, indexed_chunks: 100 },
  historical: { ingestion_failures: 1, activity: [{ event_kind: "conversation_run", outcome: "completed", aggregate_count: 28 }, { event_kind: "document_ingestion", outcome: "completed", aggregate_count: 12 }], usage: [{ operation_kind: "generation", provider: "openai", model: "gpt-5-mini", cost_basis: "estimated", pricing_snapshot: "2026-08-01", request_count: 28, retry_count: 1, input_tokens: 48210, cached_input_tokens: 4000, output_tokens: 9920, latency_ms: 154000, cost_usd: "0.0842", observation_count: 28 }, { operation_kind: "sparse_retrieval", provider: "local", model: "prithivida/Splade_PP_en_v1", cost_basis: "zero_cost_local", pricing_snapshot: null, request_count: 31, retry_count: 0, input_tokens: null, cached_input_tokens: null, output_tokens: null, latency_ms: 8240, cost_usd: 0, observation_count: 31 }, { operation_kind: "reranking", provider: "voyage", model: "rerank-2.5", cost_basis: "unavailable", pricing_snapshot: null, request_count: 28, retry_count: 0, input_tokens: null, cached_input_tokens: null, output_tokens: null, latency_ms: 11900, cost_usd: null, observation_count: 28 }] },
  labels: { logical_source_bytes: "Logical uploaded source bytes; not physical storage or billing usage.", cost: "Estimated costs use the recorded pricing snapshot. Provider billing remains authoritative." },
};

export default function AdministrationDesignReviewPage() {
  if (process.env.NODE_ENV === "production") notFound();
  return <main className="min-h-dvh bg-background px-4 py-8 text-foreground sm:px-8 lg:px-12"><div className="mx-auto max-w-7xl"><header className="flex flex-wrap items-start justify-between gap-6 border-b border-border pb-8"><div><Wordmark /><p className="mt-4 text-sm font-bold uppercase tracking-[0.16em] text-brand">R21-S02 administration review</p><h1 className="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Workspace control, without ambiguity.</h1><p className="mt-4 max-w-2xl text-lg text-foreground-muted">Structured product screens for document state, access boundaries, invitations and truthful usage reporting.</p></div><ThemeToggle /></header><nav aria-label="Review sections" className="sticky top-3 z-10 mt-5 flex flex-wrap gap-2 rounded-xl border border-border bg-background/90 p-2 shadow-sm backdrop-blur"><Button asChild size="sm" variant="ghost"><a href="#documents">Documents</a></Button><Button asChild size="sm" variant="ghost"><a href="#people">People</a></Button><Button asChild size="sm" variant="ghost"><a href="#invitations">Invitations</a></Button><Button asChild size="sm" variant="ghost"><a href="#usage">Usage</a></Button></nav><section className="grid gap-6 border-b border-border py-10" id="documents"><header><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Documents</p><h2 className="mt-2 text-3xl font-semibold">Knowledge sources</h2></header><DocumentAdministration initialPage={documents} workspacePublicId="design-review" /></section><section className="grid gap-6 border-b border-border py-10" id="people"><header><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Administration</p><h2 className="mt-2 text-3xl font-semibold">People &amp; roles</h2></header><WorkspaceAdministration actorRole="owner" initialSnapshot={administration} view="people" workspaceId="design-review" /></section><section className="grid gap-6 border-b border-border py-10" id="invitations"><header><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Administration</p><h2 className="mt-2 text-3xl font-semibold">Invitations</h2></header><WorkspaceAdministration actorRole="owner" initialSnapshot={administration} view="invitations" workspaceId="design-review" /></section><section className="grid gap-6 py-10" id="usage"><header><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Administration</p><h2 className="mt-2 text-3xl font-semibold">Usage</h2></header><WorkspaceUsage initialSnapshot={usage} workspaceId="design-review" /></section><footer className="border-t border-border py-6 text-sm text-foreground-muted">Development/test review route · representative fixture data · no production access</footer></div></main>;
}
