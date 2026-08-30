import { CalendarClock, ChevronRight, CircleAlert, FileSearch } from "lucide-react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import type { DocumentFamilyLibraryRow, DocumentFamilyPage } from "@/lib/api";
import { formatDateTime } from "@/lib/date";

type Query = Record<string, string | string[] | undefined>;

function one(value: string | string[] | undefined): string { return Array.isArray(value) ? (value[0] ?? "") : (value ?? ""); }
function bytes(value: number): string { if (value < 1024) return `${value} B`; if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`; return `${(value / 1024 / 1024).toFixed(1)} MB`; }
function title(value: string): string { return value.replaceAll("_", " ").replace(/^./, (letter) => letter.toUpperCase()); }
function tone(row: DocumentFamilyLibraryRow): StatusTone { if (row.state === "current") return "success"; if (row.state === "failed") return "destructive"; if (["scheduled", "uploading", "uploaded", "queued", "processing"].includes(row.state)) return "pending"; if (row.state === "historical") return "unavailable"; return "info"; }
function stateLabel(row: DocumentFamilyLibraryRow): string {
  if (row.state === "current") return "Current";
  if (row.state === "scheduled") return `Scheduled${row.scheduled_effective_from ? ` ${new Date(row.scheduled_effective_from).toLocaleDateString("en-GB")}` : ""}`;
  if (row.state === "draft") return "Draft awaiting approval";
  if (row.state === "historical") return "No current version";
  return title(row.state);
}
function queryHref(base: string, current: Query, changes: Record<string, string>): string {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(current)) { const item = one(value); if (item) query.set(key, item); }
  for (const [key, value] of Object.entries(changes)) { if (value) query.set(key, value); else query.delete(key); }
  return `${base}?${query.toString()}`;
}

function FamilySummary({ row, workspacePublicId }: Readonly<{ row: DocumentFamilyLibraryRow; workspacePublicId: string }>) {
  const href = `/app/workspaces/${workspacePublicId}/documents/families/${row.public_id}`;
  return <div className="grid gap-2">
    <Link className="inline-flex items-center gap-1 font-semibold underline-offset-4 hover:text-brand hover:underline" href={href}>{row.name}<ChevronRight aria-hidden="true" className="size-4" /></Link>
    <div className="flex flex-wrap gap-1.5">{row.category ? <span className="rounded-full bg-surface-muted px-2 py-1 text-xs">{row.category.name}</span> : null}{row.tags.map((tag) => <span className="rounded-full border border-border px-2 py-1 text-xs" key={tag.public_id}>{tag.name}</span>)}</div>
    {row.current_version ? <p className="text-xs text-foreground-muted">{row.current_version.source_filename} · {row.current_version.media_type} · {bytes(row.current_version.size_bytes)}</p> : <p className="text-xs text-foreground-muted">Version details appear when a version is genuinely current.</p>}
  </div>;
}

export function DocumentLibraryTable({ page, query, workspacePublicId }: Readonly<{ page: DocumentFamilyPage; query: Query; workspacePublicId: string }>) {
  const base = `/app/workspaces/${workspacePublicId}/documents`;
  return <section aria-labelledby="library-heading" className="grid gap-5">
    <Card><CardHeader><CardTitle id="library-heading">Knowledge library</CardTitle><CardDescription>One row per document family. Current status is derived from authority at read time, never from the newest upload.</CardDescription></CardHeader><CardContent><form className="grid gap-4 lg:grid-cols-[minmax(14rem,1fr)_12rem_14rem_10rem_auto] lg:items-end" method="get"><label className="grid gap-2 text-sm font-semibold" htmlFor="library-search">Search<Input defaultValue={one(query.search)} id="library-search" name="search" placeholder="Title or filename" /></label><label className="grid gap-2 text-sm font-semibold">Status<select className="flex min-h-11 rounded-lg border border-input bg-background px-3" defaultValue={one(query.status)} name="status"><option value="">All statuses</option>{["uploading", "uploaded", "queued", "processing", "indexed", "failed"].map((value) => <option key={value} value={value}>{title(value)}</option>)}</select></label><label className="grid gap-2 text-sm font-semibold">Sort<select className="flex min-h-11 rounded-lg border border-input bg-background px-3" defaultValue={one(query.sort) || "last_meaningful_update"} name="sort"><option value="last_meaningful_update">Last meaningful update</option><option value="title">Title</option><option value="review_due_date">Review due</option></select></label><label className="grid gap-2 text-sm font-semibold">Page size<select className="flex min-h-11 rounded-lg border border-input bg-background px-3" defaultValue={one(query.per_page) || "25"} name="per_page"><option>25</option><option>50</option><option>100</option></select></label><Button type="submit" variant="secondary">Apply filters</Button><label className="flex min-h-11 items-center gap-2 text-sm lg:col-span-5"><input defaultChecked={one(query.historical) === "true"} name="historical" type="checkbox" value="true" />Include families with historical versions only</label></form></CardContent></Card>
    {page.data.length === 0 ? <EmptyState description="Try changing the filters, or upload a source to create the first document family." icon={FileSearch} title="No matching document families" /> : <>
      <div className="hidden overflow-x-auto rounded-xl border border-border md:block"><table className="w-full border-collapse text-left text-sm"><thead className="bg-surface-muted text-xs uppercase tracking-[0.08em] text-foreground-muted"><tr><th className="px-4 py-3">Document family</th><th className="px-4 py-3">Authority</th><th className="px-4 py-3">Owner</th><th className="px-4 py-3">Review due</th><th className="px-4 py-3">Last changed</th></tr></thead><tbody>{page.data.map((row) => <tr className="border-t border-border align-top" key={row.public_id}><td className="px-4 py-4"><FamilySummary row={row} workspacePublicId={workspacePublicId} /></td><td className="px-4 py-4"><StatusBadge status={tone(row)}>{stateLabel(row)}</StatusBadge>{row.current_version?.extraction_warning_count ? <p className="mt-2 flex gap-1 text-xs text-warning"><CircleAlert className="size-4" />{row.current_version.extraction_warning_count} extraction warning(s)</p> : null}</td><td className="px-4 py-4">{row.owner.name}</td><td className="px-4 py-4">{row.review_due_date ? new Date(`${row.review_due_date}T00:00:00`).toLocaleDateString("en-GB") : "Not set"}</td><td className="px-4 py-4">{formatDateTime(row.last_meaningful_update)}</td></tr>)}</tbody></table></div>
      <div className="grid gap-3 md:hidden">{page.data.map((row) => <Card key={row.public_id}><CardHeader><div className="flex items-start justify-between gap-3"><CardTitle><FamilySummary row={row} workspacePublicId={workspacePublicId} /></CardTitle><StatusBadge status={tone(row)}>{stateLabel(row)}</StatusBadge></div></CardHeader><CardContent><dl className="grid grid-cols-2 gap-3 text-sm"><div><dt className="text-foreground-muted">Owner</dt><dd className="mt-1 font-medium">{row.owner.name}</dd></div><div><dt className="text-foreground-muted">Versions</dt><dd className="mt-1 font-medium">{row.version_count}</dd></div><div><dt className="text-foreground-muted">Review due</dt><dd className="mt-1 font-medium">{row.review_due_date ?? "Not set"}</dd></div><div><dt className="text-foreground-muted">Last changed</dt><dd className="mt-1 font-medium">{formatDateTime(row.last_meaningful_update)}</dd></div></dl></CardContent></Card>)}</div>
    </>}
    {page.meta.last_page > 1 ? <nav aria-label="Library pages" className="flex items-center justify-between gap-4"><Button asChild={page.meta.current_page > 1} disabled={page.meta.current_page <= 1} variant="outline">{page.meta.current_page > 1 ? <Link href={queryHref(base, query, { page: String(page.meta.current_page - 1) })}>Previous</Link> : <span>Previous</span>}</Button><span className="text-sm text-foreground-muted">Page {page.meta.current_page} of {page.meta.last_page}</span><Button asChild={page.meta.current_page < page.meta.last_page} disabled={page.meta.current_page >= page.meta.last_page} variant="outline">{page.meta.current_page < page.meta.last_page ? <Link href={queryHref(base, query, { page: String(page.meta.current_page + 1) })}>Next</Link> : <span>Next</span>}</Button></nav> : null}
    <p className="flex items-start gap-2 text-sm text-foreground-muted"><CalendarClock aria-hidden="true" className="mt-0.5 size-4 shrink-0" />Applicability is not confidentiality. Location filters organise this library; actual evidence eligibility is resolved independently for each question.</p>
  </section>;
}
