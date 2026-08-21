"use client";

import { Activity, Database, FileStack, Gauge, ReceiptText } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Notice } from "@/components/ui/notice";
import { StatusBadge } from "@/components/ui/status-badge";
import { firstError, workspaceUsage, type WorkspaceUsageSnapshot } from "@/lib/api";

type Props = { initialSnapshot: WorkspaceUsageSnapshot; workspaceId: string };
const integer = new Intl.NumberFormat("en-GB");
const usd = new Intl.NumberFormat("en-GB", { style: "currency", currency: "USD", minimumFractionDigits: 4, maximumFractionDigits: 8 });
function value(number: number | string | null): number | null { if (number === null) return null; const parsed = Number(number); return Number.isFinite(parsed) ? parsed : null; }
function costLabel(basis: WorkspaceUsageSnapshot["historical"]["usage"][number]["cost_basis"], cost: number | null): string { if (basis === "unavailable" || cost === null) return "Unavailable"; if (basis === "zero_cost_local") return `${usd.format(cost)} · local`; if (basis === "provider_reported") return `${usd.format(cost)} · provider reported`; return `${usd.format(cost)} · estimated`; }
function tokenLabel(label: string, count: number | string | null): string { const parsed = value(count); return parsed === null ? `${label}: unavailable` : `${label}: ${integer.format(parsed)}`; }

export function WorkspaceUsage({ initialSnapshot, workspaceId }: Props) {
  const [snapshot, setSnapshot] = useState(initialSnapshot);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const selectRange = async (range: "7d" | "30d" | "month") => { setBusy(true); setError(null); try { setSnapshot((await workspaceUsage(workspaceId, range)).data); } catch (caught) { setError(firstError(caught)); } finally { setBusy(false); } };
  const gauges = [
    { label: "Active documents", value: snapshot.gauges.active_documents, icon: FileStack },
    { label: "Logical source bytes", value: snapshot.gauges.logical_source_bytes, icon: Database },
    { label: "Indexed chunks", value: snapshot.gauges.indexed_chunks, icon: Gauge },
  ];
  return <div className="grid gap-6">
    <div className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="font-semibold">Reporting period</h2><p className="text-sm text-foreground-muted">Updated {new Date(snapshot.as_of).toLocaleString("en-GB")}</p></div><div aria-label="Usage interval" className="flex flex-wrap gap-2">{(["7d", "30d", "month"] as const).map((range) => <Button disabled={busy || snapshot.range.key === range} key={range} onClick={() => void selectRange(range)} size="sm" type="button" variant={snapshot.range.key === range ? "default" : "outline"}>{range === "month" ? "Current month" : range}</Button>)}</div></div>
    {error ? <Notice tone="destructive">{error}</Notice> : null}
    <div className="grid gap-4 sm:grid-cols-3">{gauges.map(({ icon: Icon, label, value: gaugeValue }) => <Card key={label}><CardHeader><span className="grid size-10 place-items-center rounded-full bg-surface-raised text-brand"><Icon className="size-5" /></span><CardDescription>{label}</CardDescription><CardTitle className="text-3xl">{integer.format(gaugeValue)}</CardTitle></CardHeader></Card>)}</div>
    <Notice tone="info">{snapshot.labels.logical_source_bytes}</Notice>
    <div className="grid gap-6 xl:grid-cols-2"><Card><CardHeader><CardTitle className="flex items-center gap-2"><Activity className="size-5 text-brand" />Workspace activity</CardTitle><CardDescription>{new Date(snapshot.range.start).toLocaleString("en-GB")} to {new Date(snapshot.range.end).toLocaleString("en-GB")} · {snapshot.range.semantics}</CardDescription></CardHeader><CardContent className="grid gap-3"><div className="flex items-center justify-between rounded-lg border border-border p-4"><span>Ingestion failures</span><StatusBadge status={snapshot.historical.ingestion_failures > 0 ? "warning" : "success"}>{integer.format(snapshot.historical.ingestion_failures)}</StatusBadge></div>{snapshot.historical.activity.length === 0 ? <EmptyState description="No activity observations are available for this interval." icon={Activity} title="No recorded activity" /> : snapshot.historical.activity.map((item) => <div className="flex items-center justify-between gap-4 rounded-lg border border-border p-4" key={`${item.event_kind}-${item.outcome ?? "none"}`}><span className="text-sm">{item.event_kind.replaceAll("_", " ")}{item.outcome ? ` · ${item.outcome.replaceAll("_", " ")}` : ""}</span><strong>{integer.format(Number(item.aggregate_count))}</strong></div>)}</CardContent></Card>
    <Card><CardHeader><CardTitle className="flex items-center gap-2"><ReceiptText className="size-5 text-brand" />Provider and local usage</CardTitle><CardDescription>Unavailable pricing is shown as unavailable, never as zero.</CardDescription></CardHeader><CardContent className="grid gap-3">{snapshot.historical.usage.length === 0 ? <EmptyState description="No provider or local usage observations are available for this interval." icon={ReceiptText} title="Usage unavailable" /> : snapshot.historical.usage.map((item) => { const cost = value(item.cost_usd); return <article className="rounded-lg border border-border p-4" key={`${item.operation_kind}-${item.provider}-${item.model}-${item.cost_basis}-${item.pricing_snapshot ?? "none"}`}><div className="flex flex-wrap items-start justify-between gap-2"><div><h3 className="font-semibold">{item.operation_kind.replaceAll("_", " ")}</h3><p className="text-sm text-foreground-muted">{item.provider} · {item.model}</p></div><StatusBadge status={item.cost_basis === "unavailable" ? "unavailable" : item.cost_basis === "estimated" ? "warning" : "info"}>{item.cost_basis.replaceAll("_", " ")}</StatusBadge></div><dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2"><div><dt className="text-foreground-muted">Requests / retries</dt><dd>{integer.format(Number(item.request_count))} / {integer.format(Number(item.retry_count))}</dd></div><div><dt className="text-foreground-muted">Cost</dt><dd>{costLabel(item.cost_basis, cost)}</dd></div><div><dt className="text-foreground-muted">Tokens</dt><dd>{tokenLabel("Input", item.input_tokens)} · {tokenLabel("Output", item.output_tokens)}</dd></div><div><dt className="text-foreground-muted">Latency</dt><dd>{value(item.latency_ms) === null ? "Unavailable" : `${integer.format(Math.round(value(item.latency_ms) ?? 0))} ms`}</dd></div></dl></article>; })}</CardContent></Card></div>
    <p className="text-xs text-foreground-muted">{snapshot.labels.cost}</p>
  </div>;
}
