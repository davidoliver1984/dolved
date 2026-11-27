"use client";

import { Bookmark, CalendarClock, CheckSquare2, ChevronRight, CircleAlert, FileSearch, FileUp, Info, LoaderCircle } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import { SavedViewsPanel } from "@/components/SavedViewsPanel";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import { confirmBulkOperation, createBulkOperation, type BulkOperationSnapshot, type BulkOperationType, type DocumentFamilyLibraryRow, type DocumentFamilyPage, type DocumentMetadata, type SavedView, type SavedViewDefinition } from "@/lib/api";
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

const operationLabels: Record<BulkOperationType, string> = {
  bulk_approval: "Approve latest draft versions",
  bulk_applicability_change: "Change locations",
  bulk_category_assignment: "Set category",
  bulk_owner_assignment: "Assign owner",
  bulk_tag_change: "Replace tags",
  bulk_review_date_assignment: "Set review date",
};

function operationPayload(type: BulkOperationType, category: string, owner: string, tags: string[], reviewDate: string, locations: string[]): Record<string, unknown> {
  if (type === "bulk_approval") return {};
  if (type === "bulk_applicability_change") return { location_public_ids: locations };
  if (type === "bulk_category_assignment") return { category_public_id: category || null };
  if (type === "bulk_owner_assignment") return { owner_user_public_id: owner };
  if (type === "bulk_tag_change") return { mode: "replace", tag_public_ids: tags };
  return { review_due_date: reviewDate || null };
}

function operationFilters(query: Query): Record<string, unknown> {
  const allowed = ["search", "category", "owner", "applicability", "review_status", "searchable", "status", "historical"];
  return Object.fromEntries(allowed.flatMap((key) => {
    const value = one(query[key]);
    if (!value) return [];
    return [[key, ["historical", "searchable"].includes(key) ? value === "true" : value]];
  }));
}

export function DocumentLibraryTable({ canManage = false, currentSavedViewDefinition, initialSavedViews, metadata, page, query, workspacePublicId }: Readonly<{ canManage?: boolean; currentSavedViewDefinition?: SavedViewDefinition; initialSavedViews?: SavedView[]; metadata?: DocumentMetadata; page: DocumentFamilyPage; query: Query; workspacePublicId: string }>) {
  const router = useRouter();
  const base = `/app/workspaces/${workspacePublicId}/documents`;
  const [selected, setSelected] = useState<string[]>([]);
  const [allFiltered, setAllFiltered] = useState(false);
  const [operationType, setOperationType] = useState<BulkOperationType>("bulk_review_date_assignment");
  const [category, setCategory] = useState("");
  const [owner, setOwner] = useState("");
  const [tags, setTags] = useState<string[]>([]);
  const [locations, setLocations] = useState<string[]>([]);
  const [applicabilityMode, setApplicabilityMode] = useState<"universal" | "selected">("universal");
  const [applicabilityConfigured, setApplicabilityConfigured] = useState(false);
  const [applicabilityOpen, setApplicabilityOpen] = useState(false);
  const [savedViewsOpen, setSavedViewsOpen] = useState(false);
  const [reviewDate, setReviewDate] = useState("");
  const [preflight, setPreflight] = useState<BulkOperationSnapshot | null>(null);
  const [exclusionSearch, setExclusionSearch] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const pageIds = useMemo(() => page.data.map((row) => row.public_id), [page.data]);
  const selectionCount = allFiltered ? page.meta.total : selected.length;
  const allPageSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
  const excludedItems = preflight?.items.filter((item) => item.exclusion_reason && (!exclusionSearch.trim() || [item.target_display_label, item.exclusion_reason].some((value) => value?.toLocaleLowerCase().includes(exclusionSearch.trim().toLocaleLowerCase())))) ?? [];
  const toggle = (id: string) => { setAllFiltered(false); if (operationType === "bulk_approval") setOperationType("bulk_review_date_assignment"); setPreflight(null); setSelected((current) => current.includes(id) ? current.filter((value) => value !== id) : [...current, id]); };
  const selectPage = () => { setAllFiltered(false); if (operationType === "bulk_approval") setOperationType("bulk_review_date_assignment"); setPreflight(null); setSelected(allPageSelected ? [] : pageIds); };
  const selectAllFiltered = () => {
    if (allFiltered) {
      setAllFiltered(false);
      setSelected([]);
      if (operationType === "bulk_approval") setOperationType("bulk_review_date_assignment");
    } else {
      setAllFiltered(true);
      setSelected(pageIds);
    }
    setPreflight(null);
  };
  const prepare = async () => {
    setBusy(true); setError(null);
    try {
      const response = await createBulkOperation(workspacePublicId, {
        operation_type: operationType,
        selection_mode: allFiltered ? "all_filtered" : "current_page",
        target_public_ids: allFiltered ? [] : selected,
        filters: operationFilters(query),
        payload: operationPayload(operationType, category, owner, tags, reviewDate, locations),
      });
      setPreflight(response.data);
      requestAnimationFrame(() => document.querySelector<HTMLElement>("#bulk-preflight-heading")?.focus());
    } catch { setError("We could not prepare this bulk operation. No changes were made."); }
    finally { setBusy(false); }
  };
  const confirm = async () => {
    if (!preflight) return;
    setBusy(true); setError(null);
    try {
      await confirmBulkOperation(workspacePublicId, preflight.public_id);
      router.push(`${base}/bulk/${preflight.public_id}`);
    } catch { setError("We could not start this action. Your reviewed selection has not changed."); setBusy(false); }
  };
  return <section aria-labelledby="library-heading" className="grid gap-5">
    <div className="flex justify-end"><Button asChild><Link href={`${base}/imports`}><FileUp />Import documents</Link></Button></div>
    <Card className="border-brand/15"><CardHeader><CardTitle id="library-heading">Knowledge library</CardTitle><CardDescription>Each document appears once, with its current version and complete version history.</CardDescription></CardHeader><CardContent><form className="grid gap-4 lg:grid-cols-[minmax(14rem,1fr)_12rem_14rem_10rem_auto] lg:items-end" method="get"><label className="grid gap-2 text-sm font-semibold" htmlFor="library-search">Search<Input defaultValue={one(query.search)} id="library-search" name="search" placeholder="Title or filename" /></label><label className="grid gap-2 text-sm font-semibold">Status<select className="flex min-h-11 rounded-lg border border-input bg-background px-3" defaultValue={one(query.status)} name="status"><option value="">All statuses</option>{["uploading", "uploaded", "queued", "processing", "indexed", "failed"].map((value) => <option key={value} value={value}>{title(value)}</option>)}</select></label><label className="grid gap-2 text-sm font-semibold">Sort<select className="flex min-h-11 rounded-lg border border-input bg-background px-3" defaultValue={one(query.sort) || "last_meaningful_update"} name="sort"><option value="last_meaningful_update">Last meaningful update</option><option value="title">Title</option><option value="review_due_date">Review due</option></select></label><label className="grid gap-2 text-sm font-semibold">Page size<select className="flex min-h-11 rounded-lg border border-input bg-background px-3" defaultValue={one(query.per_page) || "25"} name="per_page"><option>25</option><option>50</option><option>100</option></select></label><div className="flex flex-wrap gap-2"><Button type="submit" variant="secondary">Apply filters</Button>{currentSavedViewDefinition && initialSavedViews ? <Button onClick={(event) => { event.preventDefault(); setSavedViewsOpen(true); }} type="button" variant="outline"><Bookmark />Save view</Button> : null}{canManage && pageIds.length ? <Button onClick={(event) => { event.preventDefault(); selectAllFiltered(); }} type="button" variant="outline"><CheckSquare2 />{allFiltered ? "Unselect all" : "Select all"}</Button> : null}</div><label className="flex min-h-11 items-center gap-2 text-sm lg:col-span-5"><input defaultChecked={one(query.historical) === "true"} name="historical" type="checkbox" value="true" />Include documents with previous versions only</label></form></CardContent></Card>
    {currentSavedViewDefinition && initialSavedViews ? <Dialog onOpenChange={setSavedViewsOpen} open={savedViewsOpen}><DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto"><DialogHeader><DialogTitle>Save view</DialogTitle><DialogDescription>Save the current filters or manage views you already use.</DialogDescription></DialogHeader><SavedViewsPanel currentDefinition={currentSavedViewDefinition} initialViews={initialSavedViews} workspacePublicId={workspacePublicId} /></DialogContent></Dialog> : null}
    {page.data.length === 0 ? <EmptyState description="Try changing the filters, or upload a file to add the first document." icon={FileSearch} title="No matching documents" /> : <>
      {canManage && selectionCount ? <Card className="border-brand/20 bg-surface-muted/70"><CardContent className="grid gap-4 p-4"><div aria-live="polite"><strong>{selectionCount} selected</strong></div>{selectionCount ? <div className="grid gap-3 border-t border-border pt-4 md:grid-cols-[minmax(12rem,1fr)_minmax(12rem,1fr)_auto] md:items-end"><label className="grid gap-2 text-sm font-semibold" htmlFor="bulk-action">Bulk action<select className="min-h-11 rounded-lg border border-input bg-background px-3" id="bulk-action" onChange={(event) => { const value = event.target.value as BulkOperationType; setOperationType(value); setPreflight(null); if (value === "bulk_applicability_change") setApplicabilityOpen(true); }} value={operationType}>{Object.entries(operationLabels).filter(([value]) => allFiltered || value !== "bulk_approval").map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>{operationType === "bulk_review_date_assignment" ? <label className="grid gap-2 text-sm font-semibold" htmlFor="bulk-review-date">Review date<Input id="bulk-review-date" onChange={(event) => setReviewDate(event.target.value)} type="date" value={reviewDate} /></label> : null}{operationType === "bulk_category_assignment" ? <label className="grid gap-2 text-sm font-semibold" htmlFor="bulk-category">Category<select className="min-h-11 rounded-lg border border-input bg-background px-3" id="bulk-category" onChange={(event) => setCategory(event.target.value)} value={category}><option value="">No category</option>{metadata?.categories.filter((item) => item.status === "active").map((item) => <option key={item.public_id} value={item.public_id}>{item.name}</option>)}</select></label> : null}{operationType === "bulk_owner_assignment" ? <label className="grid gap-2 text-sm font-semibold" htmlFor="bulk-owner">Owner<select className="min-h-11 rounded-lg border border-input bg-background px-3" id="bulk-owner" onChange={(event) => setOwner(event.target.value)} value={owner}><option value="">Choose owner</option>{metadata?.owners.map((item) => <option key={item.public_id} value={item.public_id}>{item.name}</option>)}</select></label> : null}{operationType === "bulk_tag_change" ? <label className="grid gap-2 text-sm font-semibold" htmlFor="bulk-tags">Tags<select className="min-h-24 rounded-lg border border-input bg-background px-3 py-2" id="bulk-tags" multiple onChange={(event) => setTags(Array.from(event.target.selectedOptions, (option) => option.value))} value={tags}>{metadata?.tags.map((item) => <option key={item.public_id} value={item.public_id}>{item.name}</option>)}</select></label> : null}{operationType === "bulk_applicability_change" ? <div className="grid gap-2"><span className="text-sm font-semibold">Locations</span><Button onClick={() => setApplicabilityOpen(true)} type="button" variant="outline">{applicabilityConfigured ? "Edit locations" : "Choose locations"}</Button>{applicabilityConfigured ? <p className="text-xs text-foreground-muted">{applicabilityMode === "universal" ? "Organisation-wide" : `${locations.length} selected location${locations.length === 1 ? "" : "s"}`}</p> : null}</div> : null}{operationType === "bulk_approval" ? <Notice tone="info"><Info className="size-5 shrink-0" /><span>Approval applies to the latest draft version of each selected document. Indexing status is shown separately.</span></Notice> : null}<Button disabled={busy || (operationType === "bulk_owner_assignment" && !owner) || (operationType === "bulk_applicability_change" && !applicabilityConfigured)} onClick={() => void prepare()}>{busy ? <LoaderCircle className="animate-spin" /> : null}Review: {operationLabels[operationType]}</Button></div> : null}{error ? <Notice tone="destructive">{error}</Notice> : null}</CardContent></Card> : null}
      <Dialog onOpenChange={setApplicabilityOpen} open={applicabilityOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Change document locations</DialogTitle><DialogDescription>Choose where every selected document should be used. This records a new version and does not change who can access it.</DialogDescription></DialogHeader>
          <fieldset className="grid gap-3"><legend className="sr-only">Location choice</legend>
            <label className="flex items-center gap-2 text-sm"><input checked={applicabilityMode === "universal"} name="bulk_applicability_mode" onChange={() => { setApplicabilityMode("universal"); setLocations([]); }} type="radio" />Organisation-wide</label>
            <label className="flex items-center gap-2 text-sm"><input checked={applicabilityMode === "selected"} name="bulk_applicability_mode" onChange={() => setApplicabilityMode("selected")} type="radio" />Selected registered locations</label>
            {applicabilityMode === "selected" ? <div className="ml-6 grid max-h-64 gap-2 overflow-y-auto rounded-lg border border-border p-3">{metadata?.locations.length ? metadata.locations.map((location) => <label className="flex items-center gap-2 text-sm" key={location.public_id}><input checked={locations.includes(location.public_id)} onChange={() => setLocations((current) => current.includes(location.public_id) ? current.filter((value) => value !== location.public_id) : [...current, location.public_id])} type="checkbox" />{location.name}</label>) : <p className="text-sm text-foreground-muted">No registered locations are available.</p>}</div> : null}
          </fieldset>
          {applicabilityMode === "selected" && locations.length === 0 ? <p className="text-sm text-destructive" role="alert">Select at least one location, or choose organisation-wide.</p> : null}
          <DialogFooter><Button onClick={() => setApplicabilityOpen(false)} type="button" variant="outline">Cancel</Button><Button disabled={applicabilityMode === "selected" && locations.length === 0} onClick={() => { setApplicabilityConfigured(true); setApplicabilityOpen(false); }} type="button">Apply selection</Button></DialogFooter>
        </DialogContent>
      </Dialog>
      {preflight ? <Card className="border-brand/40"><CardHeader><CardTitle className="outline-none" id="bulk-preflight-heading" tabIndex={-1}>Confirm {operationLabels[preflight.operation_type as BulkOperationType]}</CardTitle><CardDescription>Your selection contains {preflight.counts.total} document{preflight.counts.total === 1 ? "" : "s"}. Later filter changes will not alter this action.</CardDescription></CardHeader><CardContent className="grid gap-4"><div className="grid gap-3 sm:grid-cols-3"><div className="rounded-lg bg-surface-muted p-3"><span className="text-sm text-foreground-muted">Selected</span><strong className="mt-1 block text-2xl">{preflight.counts.total}</strong></div><div className="rounded-lg bg-success/10 p-3"><span className="text-sm text-foreground-muted">Ready</span><strong className="mt-1 block text-2xl">{preflight.counts.eligible}</strong></div><div className="rounded-lg bg-warning/10 p-3"><span className="text-sm text-foreground-muted">Excluded</span><strong className="mt-1 block text-2xl">{preflight.counts.excluded}</strong></div></div>{preflight.counts.excluded ? <details><summary className="cursor-pointer font-semibold">Review excluded items</summary><label className="mt-3 grid max-w-md gap-2 text-sm font-semibold" htmlFor="bulk-exclusion-search">Search exclusions<Input id="bulk-exclusion-search" onChange={(event) => setExclusionSearch(event.target.value)} placeholder="Name or reason" value={exclusionSearch} /></label><ul className="mt-3 grid gap-2">{excludedItems.map((item) => <li className="rounded-lg border border-border p-3" key={item.ordinal}><strong>{item.target_display_label}</strong><p className="text-sm text-foreground-muted">{title(item.exclusion_reason ?? "excluded")}</p></li>)}</ul>{excludedItems.length === 0 ? <p className="mt-3 text-sm text-foreground-muted">No exclusions match that search.</p> : null}</details> : <Notice tone="success">All selected documents are ready.</Notice>}{operationType === "bulk_applicability_change" ? <Notice tone="warning"><CircleAlert className="size-5 shrink-0" /><span>This changes the locations for every ready document to {locations.length ? `${locations.length} selected location${locations.length === 1 ? "" : "s"}` : "organisation-wide"}. Review this high-consequence change before confirming.</span></Notice> : null}{preflight.counts.eligible === 0 ? <Notice tone="warning">Nothing can be changed. Review the exclusions or start a new selection.</Notice> : null}<div className="flex flex-wrap justify-end gap-2"><Button onClick={() => setPreflight(null)} variant="outline">Back</Button><Button disabled={busy || preflight.counts.eligible === 0} onClick={() => void confirm()}>{busy ? <LoaderCircle className="animate-spin" /> : null}Confirm and start</Button></div></CardContent></Card> : null}
      <div className="hidden overflow-x-auto rounded-xl border border-border bg-card shadow-panel md:block"><table className="w-full border-collapse text-left text-sm"><thead className="bg-surface-muted text-xs uppercase tracking-[0.08em] text-foreground-muted"><tr>{canManage ? <th className="w-12 px-4 py-3"><input aria-label="Select all" checked={allPageSelected} onChange={selectPage} type="checkbox" /></th> : null}<th className="px-4 py-3">Document</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Owner</th><th className="px-4 py-3">Review due</th><th className="px-4 py-3">Last changed</th></tr></thead><tbody>{page.data.map((row) => <tr className="border-t border-border align-top even:bg-surface-muted/45 hover:bg-surface-hover/60" key={row.public_id}>{canManage ? <td className="px-4 py-4"><input aria-label={`Select ${row.name}`} checked={selected.includes(row.public_id) || allFiltered} disabled={allFiltered} onChange={() => toggle(row.public_id)} type="checkbox" /></td> : null}<td className="px-4 py-4"><FamilySummary row={row} workspacePublicId={workspacePublicId} /></td><td className="px-4 py-4"><StatusBadge status={tone(row)}>{stateLabel(row)}</StatusBadge>{row.current_version?.extraction_warning_count ? <p className="mt-2 flex gap-1 text-xs text-warning"><CircleAlert className="size-4" />{row.current_version.extraction_warning_count} extraction warning(s)</p> : null}</td><td className="px-4 py-4">{row.owner.name}</td><td className="px-4 py-4">{row.review_due_date ? new Date(`${row.review_due_date}T00:00:00`).toLocaleDateString("en-GB") : "Not set"}</td><td className="px-4 py-4">{formatDateTime(row.last_meaningful_update)}</td></tr>)}</tbody></table></div>
      <div className="grid gap-3 md:hidden">{page.data.map((row) => <Card key={row.public_id}><CardHeader><div className="flex items-start justify-between gap-3"><div className="flex min-w-0 items-start gap-3">{canManage ? <input aria-label={`Select ${row.name}`} checked={selected.includes(row.public_id) || allFiltered} className="mt-1" disabled={allFiltered} onChange={() => toggle(row.public_id)} type="checkbox" /> : null}<CardTitle><FamilySummary row={row} workspacePublicId={workspacePublicId} /></CardTitle></div><StatusBadge status={tone(row)}>{stateLabel(row)}</StatusBadge></div></CardHeader><CardContent><dl className="grid grid-cols-2 gap-3 text-sm"><div><dt className="text-foreground-muted">Owner</dt><dd className="mt-1 font-medium">{row.owner.name}</dd></div><div><dt className="text-foreground-muted">Versions</dt><dd className="mt-1 font-medium">{row.version_count}</dd></div><div><dt className="text-foreground-muted">Review due</dt><dd className="mt-1 font-medium">{row.review_due_date ?? "Not set"}</dd></div><div><dt className="text-foreground-muted">Last changed</dt><dd className="mt-1 font-medium">{formatDateTime(row.last_meaningful_update)}</dd></div></dl></CardContent></Card>)}</div>
    </>}
    {page.meta.last_page > 1 ? <nav aria-label="Library pages" className="flex items-center justify-between gap-4"><Button asChild={page.meta.current_page > 1} disabled={page.meta.current_page <= 1} variant="outline">{page.meta.current_page > 1 ? <Link href={queryHref(base, query, { page: String(page.meta.current_page - 1) })}>Previous</Link> : <span>Previous</span>}</Button><span className="text-sm text-foreground-muted">Page {page.meta.current_page} of {page.meta.last_page}</span><Button asChild={page.meta.current_page < page.meta.last_page} disabled={page.meta.current_page >= page.meta.last_page} variant="outline">{page.meta.current_page < page.meta.last_page ? <Link href={queryHref(base, query, { page: String(page.meta.current_page + 1) })}>Next</Link> : <span>Next</span>}</Button></nav> : null}
    <p className="flex items-start gap-2 text-sm text-foreground-muted"><CalendarClock aria-hidden="true" className="mt-0.5 size-4 shrink-0" />Location filters help organise which documents are used for a question. They do not change who can access the library.</p>
  </section>;
}
