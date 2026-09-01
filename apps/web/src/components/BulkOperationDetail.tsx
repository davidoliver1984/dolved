"use client";

import { CheckCircle2, LoaderCircle, Play, RefreshCw, RotateCcw, Search, XCircle } from "lucide-react";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import { bulkOperation, cancelBulkOperation, confirmBulkOperation, retryBulkOperation, type BulkOperationSnapshot } from "@/lib/api";

const terminal = new Set(["completed", "completed_with_exclusions", "completed_with_exceptions", "cancelled", "cancelled_after_partial_execution", "failed_before_execution"]);
const labels: Record<string, string> = {
  awaiting_confirmation: "Awaiting confirmation", queued: "Queued", running: "Running",
  completed: "Completed", completed_with_exclusions: "Completed with exclusions",
  completed_with_exceptions: "Completed with exceptions", cancelled: "Cancelled",
  cancelled_after_partial_execution: "Cancelled after partial execution", failed_before_execution: "Failed before execution",
};
function label(value: string): string { return labels[value] ?? value.replaceAll("_", " ").replace(/^./, (letter) => letter.toUpperCase()); }
function tone(value: string): StatusTone { if (value === "completed" || value === "succeeded") return "success"; if (value.includes("failed")) return "destructive"; if (value.includes("cancel") || value.includes("exception") || value === "skipped") return "warning"; if (["queued", "running", "eligible", "waiting_on_subordinate"].includes(value)) return "pending"; return "unavailable"; }

export function BulkOperationDetail({ initial, poll = true, workspacePublicId }: Readonly<{ initial: BulkOperationSnapshot; poll?: boolean; workspacePublicId: string }>) {
  const [operation, setOperation] = useState(initial);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [itemSearch, setItemSearch] = useState("");
  const previousStatus = useRef(initial.status);
  const base = `/app/workspaces/${workspacePublicId}/documents`;
  const refresh = async () => {
    try { setOperation((await bulkOperation(workspacePublicId, operation.public_id)).data); setError(null); }
    catch { setError("The latest operation state could not be loaded. The durable operation remains safe."); }
  };
  useEffect(() => {
    if (!poll || terminal.has(operation.status)) return;
    const timer = window.setInterval(() => void refresh(), 2000);
    return () => window.clearInterval(timer);
  });
  useEffect(() => {
    if (previousStatus.current !== operation.status) {
      previousStatus.current = operation.status;
      document.querySelector<HTMLElement>("#bulk-operation-heading")?.focus();
    }
  }, [operation.status]);
  const act = async (kind: "cancel" | "confirm" | "retry") => {
    setBusy(true); setError(null);
    const action = kind === "cancel" ? cancelBulkOperation : kind === "confirm" ? confirmBulkOperation : retryBulkOperation;
    try { setOperation((await action(workspacePublicId, operation.public_id)).data); }
    catch { setError(`Dolved could not ${kind} this operation. No operation evidence was rewritten.`); }
    finally { setBusy(false); }
  };
  const canCancel = !terminal.has(operation.status) && operation.status !== "awaiting_confirmation";
  const canConfirm = operation.status === "awaiting_confirmation" && operation.counts.eligible > 0;
  const canRetry = operation.counts.failed_retryable > 0;
  const visibleItems = operation.items.filter((item) => {
    const term = itemSearch.trim().toLocaleLowerCase();
    return !term || [item.target_display_label, item.execution_status, item.exclusion_reason, item.terminal_reason]
      .some((value) => value?.toLocaleLowerCase().includes(term));
  });
  const counts = [
    ["Eligible / pending", operation.counts.eligible], ["Open attempts", operation.counts.open_attempts],
    ["Waiting on related work", operation.counts.waiting_on_subordinate], ["Succeeded", operation.counts.succeeded],
    ["Excluded", operation.counts.excluded], ["Skipped", operation.counts.skipped],
    ["Retryable failures", operation.counts.failed_retryable], ["Permanent failures", operation.counts.failed_permanent],
    ["Cancelled", operation.counts.cancelled],
  ] as const;
  return <div className="grid gap-6">
    <header className="flex flex-wrap items-start justify-between gap-4"><div><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Bulk operation</p><h1 className="mt-2 outline-none" id="bulk-operation-heading" tabIndex={-1}>{label(operation.operation_type)}</h1><p className="mt-2 text-foreground-muted">Frozen membership: {operation.counts.total} item{operation.counts.total === 1 ? "" : "s"} · {operation.selection_mode === "all_filtered" ? "every filtered result" : "selected page items"}</p></div><StatusBadge status={tone(operation.status)}>{label(operation.status)}</StatusBadge></header>
    <p aria-live="polite" className="sr-only">Operation status changed to {label(operation.status)}.</p>
    {error ? <Notice tone="destructive">{error}</Notice> : null}
    {operation.cancellation_requested_at && !terminal.has(operation.status) ? <Notice tone="warning">Cancellation requested. Open attempts and already-started related work will converge truthfully.</Notice> : null}
    <Card><CardHeader><CardTitle>Progress</CardTitle><CardDescription>Counts come from durable item and attempt states. There is no estimated or fabricated percentage.</CardDescription></CardHeader><CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{counts.map(([name, value]) => <div className="rounded-lg border border-border bg-surface-muted p-4" key={name}><span className="text-sm text-foreground-muted">{name}</span><strong className="mt-1 block text-2xl">{value}</strong></div>)}</CardContent></Card>
    {canConfirm ? <Notice tone="warning"><div><strong>Ready for aggregate confirmation</strong><p className="mt-1">Confirming starts the frozen eligible set only. {operation.counts.excluded} excluded item{operation.counts.excluded === 1 ? " remains" : "s remain"} unchanged.</p></div></Notice> : null}
    <div className="flex flex-wrap gap-2">{canConfirm ? <Button disabled={busy} onClick={() => void act("confirm")}><Play />Confirm and start</Button> : null}<Button disabled={busy} onClick={() => void refresh()} variant="outline">{busy ? <LoaderCircle className="animate-spin" /> : <RefreshCw />}Refresh</Button>{canRetry ? <Button disabled={busy} onClick={() => void act("retry")}><RotateCcw />Retry eligible failures</Button> : null}{canCancel ? <Button disabled={busy} onClick={() => void act("cancel")} variant="destructive"><XCircle />Cancel remaining work</Button> : null}<Button asChild variant="ghost"><Link href={base}>Back to library</Link></Button></div>
    <Card><CardHeader><CardTitle>Item outcomes</CardTitle><CardDescription>Summary facts stay visible. Search, then expand an item for its exact exclusion, skip or failure reason.</CardDescription></CardHeader><CardContent className="grid gap-3"><label className="grid max-w-md gap-2 text-sm font-semibold" htmlFor="bulk-item-search">Search item outcomes<span className="relative"><Search aria-hidden="true" className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-foreground-muted" /><Input className="pl-9" id="bulk-item-search" onChange={(event) => setItemSearch(event.target.value)} placeholder="Name, status or reason" value={itemSearch} /></span></label>{visibleItems.map((item) => {
      const href = item.target_kind === "family" ? `${base}/families/${item.target_public_id}` : item.target_kind === "version" ? `${base}/${item.target_public_id}` : `${base}/imports`;
      return <details className="rounded-lg border border-border p-4" key={item.ordinal}><summary className="flex cursor-pointer list-none items-center justify-between gap-3"><span className="min-w-0"><strong className="block truncate">{item.target_display_label}</strong><span className="text-sm text-foreground-muted">Item {item.ordinal}</span></span><StatusBadge status={tone(item.execution_status)}>{label(item.execution_status)}</StatusBadge></summary><div className="mt-4 grid gap-3 border-t border-border pt-4 text-sm">{item.exclusion_reason ? <Notice tone="info">Excluded: {label(item.exclusion_reason)}</Notice> : null}{item.terminal_reason ? <p><strong>Outcome:</strong> {label(item.terminal_reason)}</p> : null}{item.execution_status === "succeeded" ? <p className="flex items-center gap-2 text-success"><CheckCircle2 className="size-4" />The authoritative action completed.</p> : null}<Button asChild className="w-fit" size="sm" variant="outline"><Link href={href}>View affected {item.target_kind}</Link></Button></div></details>;
    })}{visibleItems.length === 0 ? <p className="rounded-lg border border-dashed border-border p-4 text-sm text-foreground-muted">No item outcomes match that search.</p> : null}</CardContent></Card>
  </div>;
}
