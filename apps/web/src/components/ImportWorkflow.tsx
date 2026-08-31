"use client";

import { Check, ChevronRight, CircleAlert, FileCheck2, FileSearch, FileUp, FolderInput, LoaderCircle, Rocket, ShieldCheck, UploadCloud, X } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { ApiError } from "@/lib/api";
import {
  createImportBatch,
  decideImport,
  getImportBatch,
  importConfiguration,
  importMatches,
  listImportBatches,
  promoteImport,
  stageImportFile,
  validateDocumentFile,
  type ImportBatch,
  type ImportConfiguration,
  type ImportDefinition,
  type ImportItem,
  type ImportMatches,
} from "@/lib/import-workflow";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import { StatusBadge } from "@/components/ui/status-badge";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";

const stages = [
  ["Select", FileUp], ["Stage", UploadCloud], ["Verify", ShieldCheck], ["Match", FileSearch],
  ["Review", FileCheck2], ["Promote", Rocket], ["Index", FolderInput],
] as const;

type SelectedFile = { file: File; progress: number; error: string | null };
type ReviewState = { item: ImportItem; matches: ImportMatches };

function itemStage(item: ImportItem): number {
  if (item.document?.status === "indexed") return 7;
  if (item.document) return item.document.status === "processing" ? 6 : 6;
  if (item.promotion) return 6;
  if (item.decision_ready) return 5;
  if (item.preflight_status === "verified") return 4;
  return 3;
}

function itemStatus(item: ImportItem): { label: string; tone: "success" | "pending" | "destructive" | "unavailable" | "info" } {
  if (item.preflight_status === "rejected") return { label: "Needs attention", tone: "destructive" };
  if (item.promotion?.status === "conflict" || item.promotion?.status === "failed") return { label: "Promotion stopped", tone: "destructive" };
  if (item.document?.status === "indexed") return { label: "Indexed", tone: "success" };
  if (item.document?.status === "processing") return { label: "Processing", tone: "pending" };
  if (item.document) return { label: "Promoted · queued", tone: "info" };
  if (item.promotion) return { label: "Promoting", tone: "pending" };
  if (item.decision_ready) return { label: "Ready to promote", tone: "info" };
  if (item.preflight_status === "verified") return { label: "Ready for review", tone: "info" };
  return { label: "Verifying", tone: "pending" };
}

export function ImportWorkflow({ workspacePublicId }: Readonly<{ workspacePublicId: string }>) {
  const [configuration, setConfiguration] = useState<ImportConfiguration | null>(null);
  const [batches, setBatches] = useState<ImportBatch[]>([]);
  const [batch, setBatch] = useState<ImportBatch | null>(null);
  const [files, setFiles] = useState<SelectedFile[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [review, setReview] = useState<ReviewState | null>(null);

  const refresh = useCallback(async () => {
    const recent = await listImportBatches(workspacePublicId);
    setBatches(recent);
    if (batch) setBatch(await getImportBatch(workspacePublicId, batch.public_id));
  }, [batch, workspacePublicId]);

  useEffect(() => {
    void Promise.all([importConfiguration(workspacePublicId), listImportBatches(workspacePublicId)])
      .then(([nextConfiguration, recent]) => { setConfiguration(nextConfiguration); setBatches(recent); })
      .catch(() => setError("The import workspace could not be loaded."));
  }, [workspacePublicId]);

  useEffect(() => {
    if (!batch || batch.items.every((item) => item.preflight_status !== "pending" && (!item.promotion || item.promotion.status === "committed") && (!item.document || ["indexed", "failed"].includes(item.document.status)))) return;
    const timer = window.setInterval(() => void refresh().catch(() => undefined), 2000);
    return () => window.clearInterval(timer);
  }, [batch, refresh]);

  const accepted = useMemo(() => configuration ? Object.keys(configuration.formats).map((extension) => `.${extension}`).join(",") : "", [configuration]);

  const chooseFiles = (selected: File[]) => {
    if (!configuration) return;
    const next: SelectedFile[] = [];
    const problems: string[] = [];
    for (const file of selected) {
      const problem = validateDocumentFile(file, configuration);
      if (problem) problems.push(problem); else next.push({ file, progress: 0, error: null });
    }
    setFiles(next);
    setError(problems[0] ?? null);
  };

  const begin = async () => {
    if (!configuration || files.length === 0) return;
    setBusy(true); setError(null);
    try {
      const created = await createImportBatch(workspacePublicId, files.map((entry) => entry.file));
      setBatch(created.batch);
      await Promise.all(created.uploads.map(async (entry, index) => {
        try {
          await stageImportFile(workspacePublicId, created.batch.public_id, entry.item_public_id, files[index].file, entry.upload, (progress) => {
            setFiles((current) => current.map((candidate, candidateIndex) => candidateIndex === index ? { ...candidate, progress } : candidate));
          });
        } catch (cause) {
          setFiles((current) => current.map((candidate, candidateIndex) => candidateIndex === index ? { ...candidate, error: cause instanceof Error ? cause.message : "Upload failed." } : candidate));
        }
      }));
      const updated = await getImportBatch(workspacePublicId, created.batch.public_id);
      setBatch(updated);
      setBatches((current) => [updated, ...current.filter((item) => item.public_id !== updated.public_id)]);
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : "The import could not be started.");
    } finally { setBusy(false); }
  };

  const openReview = async (item: ImportItem) => {
    setBusy(true); setError(null);
    try { setReview({ item, matches: await importMatches(workspacePublicId, batch!.public_id, item.public_id) }); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Matching is not available yet."); }
    finally { setBusy(false); }
  };

  const submitReview = async (definition: ImportDefinition) => {
    if (!batch || !review) return;
    setBusy(true); setError(null);
    try {
      await decideImport(workspacePublicId, batch.public_id, review.item.public_id, definition);
      setReview(null);
      setBatch(await getImportBatch(workspacePublicId, batch.public_id));
    } catch (cause) { setError(cause instanceof Error ? cause.message : "The review could not be saved."); }
    finally { setBusy(false); }
  };

  const promote = async (item: ImportItem) => {
    if (!batch) return;
    setBusy(true); setError(null);
    try {
      await promoteImport(workspacePublicId, batch.public_id, item.public_id);
      setBatch(await getImportBatch(workspacePublicId, batch.public_id));
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Promotion could not start."); }
    finally { setBusy(false); }
  };

  if (!configuration) return <Card><CardContent className="flex min-h-48 items-center justify-center gap-3"><LoaderCircle className="animate-spin text-brand" />Loading import workspace…</CardContent></Card>;

  return <div className="grid gap-6">
    <ol aria-label="Import stages" className="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7">
      {stages.map(([label, Icon], index) => <li className={cn("flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-3 text-sm font-semibold", (batch ? Math.max(...batch.items.map(itemStage), 1) : files.length ? 1 : 0) >= index + 1 && "border-brand/40 bg-brand-soft text-brand-strong")} key={label}><span className="grid size-7 place-items-center rounded-full bg-surface-raised"><Icon className="size-4" /></span>{label}</li>)}
    </ol>

    {error ? <Notice tone="destructive"><div className="flex items-start gap-2"><CircleAlert className="mt-0.5 size-4 shrink-0" /><span>{error}</span></div></Notice> : null}

    {!batch ? <Card>
      <CardHeader><CardTitle>Add documents</CardTitle><CardDescription>Select one file or a whole working set. Nothing reaches the Library until each source has been verified, matched, reviewed and promoted.</CardDescription></CardHeader>
      <CardContent className="grid gap-5">
        <div className="grid min-h-56 place-items-center rounded-xl border border-dashed border-border bg-surface p-6 text-center" onDragOver={(event) => event.preventDefault()} onDrop={(event) => { event.preventDefault(); chooseFiles(Array.from(event.dataTransfer.files)); }}>
          <div className="grid justify-items-center gap-3"><span className="grid size-12 place-items-center rounded-full bg-brand-soft text-brand"><UploadCloud /></span><strong>Drop documents here</strong><span className="text-sm text-foreground-muted">or choose from your device</span><label className={cn(buttonVariants({ variant: "secondary" }), "cursor-pointer")} htmlFor="import-files">Choose files</label><input accept={accepted} className="sr-only" id="import-files" multiple onChange={(event) => { chooseFiles(Array.from(event.target.files ?? [])); event.target.value = ""; }} type="file" /><small className="text-foreground-muted">PDF, Word, RTF, text or Markdown · up to {Math.floor(configuration.max_upload_bytes / 1024 / 1024)} MB each</small></div>
        </div>
        {files.length ? <div className="grid gap-3"><div className="flex items-center justify-between"><strong>{files.length} file{files.length === 1 ? "" : "s"} selected</strong><Button onClick={() => setFiles([])} size="sm" variant="ghost"><X />Clear</Button></div>{files.map(({ file, progress, error: itemError }) => <div className="rounded-lg border border-border bg-surface p-4" key={`${file.name}-${file.size}`}><div className="flex justify-between gap-3"><span className="min-w-0 truncate font-medium">{file.name}</span><span className="text-sm text-foreground-muted">{(file.size / 1024).toFixed(1)} KB</span></div>{progress ? <progress className="mt-3 h-2 w-full accent-brand" max="100" value={progress}>{progress}%</progress> : null}{itemError ? <p className="mt-2 text-sm text-destructive">{itemError}</p> : null}</div>)}<Button disabled={busy} onClick={begin}>{busy ? <LoaderCircle className="animate-spin" /> : <ChevronRight />}Stage and verify {files.length === 1 ? "document" : `${files.length} documents`}</Button></div> : null}
      </CardContent>
    </Card> : <ActiveBatch batch={batch} busy={busy} onNew={() => { setBatch(null); setFiles([]); setReview(null); }} onPromote={promote} onReview={openReview} />}

    {review ? <ReviewPanel configuration={configuration} matches={review.matches} item={review.item} onCancel={() => setReview(null)} onSubmit={submitReview} /> : null}

    {batches.length ? <Card><CardHeader><CardTitle>Recent imports</CardTitle><CardDescription>Resume an unfinished batch or check what happened after promotion.</CardDescription></CardHeader><CardContent className="grid gap-2">{batches.slice(0, 6).map((candidate) => <button className="flex min-h-12 items-center justify-between gap-3 rounded-lg border border-border px-4 text-left hover:bg-surface-raised" key={candidate.public_id} onClick={() => { setBatch(candidate); setReview(null); }} type="button"><span><strong>{candidate.items.length} document{candidate.items.length === 1 ? "" : "s"}</strong><span className="ml-2 text-sm text-foreground-muted">{new Date(candidate.created_at).toLocaleDateString()}</span></span><StatusBadge status={candidate.items.every((item) => item.document?.status === "indexed") ? "success" : "pending"}>{candidate.items.every((item) => item.document?.status === "indexed") ? "Indexed" : "In progress"}</StatusBadge></button>)}</CardContent></Card> : null}
  </div>;
}

function ActiveBatch({ batch, busy, onNew, onPromote, onReview }: Readonly<{ batch: ImportBatch; busy: boolean; onNew: () => void; onPromote: (item: ImportItem) => void; onReview: (item: ImportItem) => void }>) {
  return <Card><CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between"><div><CardTitle>Import in progress</CardTitle><CardDescription>Each document advances independently. You can leave this page and resume the batch later.</CardDescription></div><Button onClick={onNew} size="sm" variant="outline"><FileUp />New import</Button></CardHeader><CardContent><ul className="grid gap-3">{batch.items.map((item) => { const status = itemStatus(item); return <li className="rounded-xl border border-border bg-surface p-4" key={item.public_id}><div className="flex flex-wrap items-start justify-between gap-3"><div className="min-w-0"><strong className="block truncate">{item.filename}</strong><p className="mt-1 text-sm text-foreground-muted">Stage {itemStage(item)} of 7 · {status.label}</p></div><StatusBadge status={status.tone}>{status.label}</StatusBadge></div><div className="mt-4 h-2 overflow-hidden rounded-full bg-surface-raised"><span className="block h-full bg-brand transition-[width]" style={{ width: `${Math.round(itemStage(item) / 7 * 100)}%` }} /></div>{item.preflight_status === "rejected" ? <Notice className="mt-4" tone="destructive">This source could not be verified: {item.preflight_rejection_reason?.replaceAll("_", " ")}.</Notice> : null}{item.preflight_status === "verified" && !item.decision_ready ? <Button className="mt-4" disabled={busy} onClick={() => onReview(item)} size="sm"><FileSearch />Review match and metadata</Button> : null}{item.decision_ready && !item.promotion ? <Button className="mt-4" disabled={busy} onClick={() => onPromote(item)} size="sm"><Rocket />Promote to Library</Button> : null}{item.promotion?.reason ? <Notice className="mt-4" tone="destructive">Promotion stopped: {item.promotion.reason.replaceAll("_", " ")}.</Notice> : null}{item.document ? <p className="mt-4 text-sm text-foreground-muted">Promoted to the ordinary ingestion pipeline. Status: <strong className="text-foreground">{item.document.status}</strong>. Finer ingestion sub-stages are not claimed here.</p> : null}</li>; })}</ul></CardContent></Card>;
}

function ReviewPanel({ configuration, item, matches, onCancel, onSubmit }: Readonly<{ configuration: ImportConfiguration; item: ImportItem; matches: ImportMatches; onCancel: () => void; onSubmit: (definition: ImportDefinition) => void }>) {
  const firstCandidate = matches.family_candidates[0];
  const [mode, setMode] = useState<"new" | "successor">(firstCandidate ? "successor" : "new");
  const [familyId, setFamilyId] = useState(firstCandidate?.family_id ?? "");
  const [title, setTitle] = useState(item.filename.replace(/\.[^.]+$/, ""));
  const [description, setDescription] = useState("");
  const [ownerId, setOwnerId] = useState(configuration.review_options.current_user_public_id);
  const [categoryId, setCategoryId] = useState("");
  const [effectiveFrom, setEffectiveFrom] = useState(new Date().toISOString().slice(0, 10));

  if (matches.exact_live_duplicates.length) return <Card><CardHeader><CardTitle>Duplicate found</CardTitle><CardDescription>{item.filename} has the same verified checksum as an existing live document. Promotion is blocked so the existing source can be reviewed instead.</CardDescription></CardHeader><CardContent><Notice tone="warning">Identical live document: {matches.exact_live_duplicates[0].document_id}. For an applicability-only change, use the existing document’s governance action.</Notice><Button className="mt-4" onClick={onCancel} variant="outline">Close review</Button></CardContent></Card>;

  const definition: ImportDefinition = {
    family: mode === "new" ? { mode: "new", title } : { mode: "successor", family_public_id: familyId },
    metadata: { category_public_id: categoryId || null, description: description || null, owner_user_public_id: ownerId, publisher_label: null, review_due_date: null, source_url: null, tag_public_ids: [] },
    applicability: { location_public_ids: [] }, effective_from: effectiveFrom,
  };
  return <Card><CardHeader><CardTitle>Review {item.filename}</CardTitle><CardDescription>Matching is advisory. Confirm the family and essential metadata before creating an immutable decision snapshot.</CardDescription></CardHeader><CardContent className="grid gap-5"><div className="grid gap-3 rounded-xl border border-border bg-surface p-4"><strong>Document family</strong>{matches.family_candidates.length ? <div className="grid gap-2">{matches.family_candidates.map((candidate) => <div className="flex items-start gap-3 rounded-lg border border-border p-3" key={candidate.family_id}><input aria-label={`Use existing family ${candidate.title}`} checked={mode === "successor" && familyId === candidate.family_id} name="family" onChange={() => { setMode("successor"); setFamilyId(candidate.family_id); }} type="radio" /><span><strong>{candidate.title}</strong><span className="block text-sm text-foreground-muted">Possible existing family · {Math.round(candidate.score_basis_points / 100)}% filename/title match</span></span></div>)}</div> : null}<div className="flex items-start gap-3 rounded-lg border border-border p-3"><input aria-label="Create a new document family" checked={mode === "new"} name="family" onChange={() => setMode("new")} type="radio" /><span><strong>Create a new family</strong><span className="block text-sm text-foreground-muted">Use when this is genuinely a new source, not a revision.</span></span></div></div>
    {mode === "new" ? <FormField id="import-family-title" label="Family title"><Input onChange={(event) => setTitle(event.target.value)} value={title} /></FormField> : null}
    <FormField help="Optional" id="import-description" label="Description"><Textarea onChange={(event) => setDescription(event.target.value)} value={description} /></FormField>
    <div className="grid gap-4 sm:grid-cols-3"><FormField id="import-owner" label="Owner"><select className="min-h-11 rounded-md border border-input bg-background px-3" onChange={(event) => setOwnerId(event.target.value)} value={ownerId}>{configuration.review_options.owners.map((option) => <option key={option.public_id} value={option.public_id}>{option.name}</option>)}</select></FormField><FormField help="Optional" id="import-category" label="Category"><select className="min-h-11 rounded-md border border-input bg-background px-3" onChange={(event) => setCategoryId(event.target.value)} value={categoryId}><option value="">Uncategorised</option>{configuration.review_options.categories.map((option) => <option key={option.public_id} value={option.public_id}>{option.name}</option>)}</select></FormField><FormField id="import-effective-from" label="Effective from"><Input onChange={(event) => setEffectiveFrom(event.target.value)} type="date" value={effectiveFrom} /></FormField></div>
    <details className="rounded-lg border border-border p-4"><summary className="cursor-pointer font-semibold">Advanced metadata and applicability</summary><p className="mt-3 text-sm text-foreground-muted">Optional publisher, source URL, review date, tags and location applicability remain empty unless deliberately supplied. They are never inferred from the filename.</p></details>
    <div className="flex flex-wrap gap-3"><Button disabled={!ownerId || (mode === "new" ? !title.trim() : !familyId)} onClick={() => onSubmit(definition)}><Check />Save review</Button><Button onClick={onCancel} variant="outline">Cancel</Button></div>
  </CardContent></Card>;
}
