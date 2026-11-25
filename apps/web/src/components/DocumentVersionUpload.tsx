"use client";

import { CircleAlert, FileUp, LoaderCircle } from "lucide-react";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import {
  createImportBatch,
  decideImport,
  getImportBatch,
  importConfiguration,
  importMatches,
  promoteImport,
  stageImportFile,
  validateDocumentFile,
  type ImportBatch,
  type ImportDefinition,
} from "@/lib/import-workflow";
import type { DocumentFamilyDetail } from "@/lib/server-api";

const accepted = ".pdf,.doc,.docx,.rtf,.txt,.md";

function nextEffectiveDate(value: string): string {
  const previous = new Date(`${value.slice(0, 10)}T00:00:00Z`);
  previous.setUTCDate(previous.getUTCDate() + 1);
  const successor = previous.toISOString().slice(0, 10);
  const today = new Date().toISOString().slice(0, 10);
  return successor > today ? successor : today;
}

function delay(milliseconds: number): Promise<void> {
  return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

async function waitFor(
  workspacePublicId: string,
  batchPublicId: string,
  predicate: (batch: ImportBatch) => boolean,
): Promise<ImportBatch> {
  for (let attempt = 0; attempt < 45; attempt += 1) {
    const batch = await getImportBatch(workspacePublicId, batchPublicId);
    if (predicate(batch)) return batch;
    await delay(2000);
  }
  throw new Error("This is taking longer than expected. The import remains available in Recent imports.");
}

export function DocumentVersionUpload({
  detail,
  workspacePublicId,
}: Readonly<{
  detail: DocumentFamilyDetail;
  workspacePublicId: string;
}>) {
  const router = useRouter();
  const latest = detail.history.data.at(-1);
  const owner = detail.family.owner;
  const [open, setOpen] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [effectiveFrom, setEffectiveFrom] = useState(() => latest ? nextEffectiveDate(latest.effective_from) : "");
  const [progress, setProgress] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [complete, setComplete] = useState(false);

  const inheritedScope = useMemo(() => {
    if (!latest || latest.applicability.scope === "universal") return "Organisation-wide";
    return latest.applicability.locations.map((location) => location.name).join(", ") || "No locations recorded";
  }, [latest]);

  if (!latest || !owner) return null;

  const reset = () => {
    setFile(null);
    setEffectiveFrom(nextEffectiveDate(latest.effective_from));
    setProgress(0);
    setError(null);
    setComplete(false);
  };

  const submit = async () => {
    if (!file) return;
    setBusy(true);
    setError(null);
    setComplete(false);
    try {
      const configuration = await importConfiguration(workspacePublicId);
      const validation = validateDocumentFile(file, configuration);
      if (validation) throw new Error(validation);

      const created = await createImportBatch(workspacePublicId, [file]);
      const upload = created.uploads[0];
      await stageImportFile(workspacePublicId, created.batch.public_id, upload.item_public_id, file, upload.upload, setProgress);
      const verified = await waitFor(
        workspacePublicId,
        created.batch.public_id,
        (batch) => batch.items[0]?.preflight_status !== "pending",
      );
      const item = verified.items[0];
      if (!item || item.preflight_status === "rejected") {
        throw new Error("The new source could not be verified. Review the import for more detail.");
      }
      const matches = await importMatches(workspacePublicId, created.batch.public_id, item.public_id);
      if (matches.exact_live_duplicates.length) {
        throw new Error("This file is identical to an existing live version, so no new version was created.");
      }

      const definition: ImportDefinition = {
        family: { mode: "successor", family_public_id: detail.family.public_id },
        metadata: {
          category_public_id: detail.family.category?.public_id ?? null,
          description: detail.family.description,
          owner_user_public_id: owner.public_id,
          publisher_label: latest.publisher_label,
          review_due_date: detail.family.review_due_date,
          source_url: latest.source_url,
          tag_public_ids: detail.family.tags.map((tag) => tag.public_id),
        },
        applicability: {
          location_public_ids: latest.applicability.scope === "universal"
            ? []
            : latest.applicability.locations.map((location) => location.public_id),
        },
        effective_from: effectiveFrom,
      };
      await decideImport(workspacePublicId, created.batch.public_id, item.public_id, definition);
      await promoteImport(workspacePublicId, created.batch.public_id, item.public_id);
      await waitFor(
        workspacePublicId,
        created.batch.public_id,
        (batch) => Boolean(batch.items[0]?.document)
          || ["conflict", "failed"].includes(batch.items[0]?.promotion?.status ?? ""),
      ).then((batch) => {
        const result = batch.items[0];
        if (!result?.document) throw new Error("The new version could not be created. Review the import for more detail.");
      });
      setComplete(true);
      router.refresh();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "The new version could not be uploaded.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog onOpenChange={(next) => {
      if (busy) return;
      setOpen(next);
      if (next) reset();
    }} open={open}>
      <DialogTrigger asChild>
        <Button><FileUp aria-hidden="true" />Upload new version</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Upload a new version</DialogTitle>
          <DialogDescription>
            The file will be added to {detail.family.name}, verified, indexed and retained as a draft until approved.
          </DialogDescription>
        </DialogHeader>
        {error ? <Notice tone="destructive"><span className="flex items-start gap-2"><CircleAlert className="mt-0.5 size-4 shrink-0" />{error}</span></Notice> : null}
        {complete ? <Notice tone="success">The new version has been created and indexing has started.</Notice> : (
          <div className="grid gap-4">
            <FormField help="PDF, Word, RTF, text or Markdown · up to 25 MB" id="new-version-file" label="Revised document">
              <Input accept={accepted} disabled={busy} id="new-version-file" onChange={(event) => setFile(event.target.files?.[0] ?? null)} type="file" />
            </FormField>
            <FormField help={`Must be later than ${latest.effective_from.slice(0, 10)}.`} id="new-version-effective-from" label="Effective from">
              <Input disabled={busy} id="new-version-effective-from" min={nextEffectiveDate(latest.effective_from)} onChange={(event) => setEffectiveFrom(event.target.value)} type="date" value={effectiveFrom} />
            </FormField>
            <div className="rounded-lg border border-border bg-surface-muted p-3 text-sm">
              <strong>Inherited from this family</strong>
              <p className="mt-1 text-foreground-muted">Owner: {owner.name} · Applicability: {inheritedScope}</p>
            </div>
            {busy ? <div className="grid gap-2"><div className="flex items-center gap-2 text-sm"><LoaderCircle className="size-4 animate-spin" />{progress < 100 ? "Uploading revised source…" : "Verifying and creating the version…"}</div><progress className="h-2 w-full accent-brand" max="100" value={progress}>{progress}%</progress></div> : null}
          </div>
        )}
        <DialogFooter>
          {complete ? <Button onClick={() => setOpen(false)}>Done</Button> : <><Button disabled={busy} onClick={() => setOpen(false)} variant="outline">Cancel</Button><Button disabled={busy || !file || !effectiveFrom} onClick={submit}>{busy ? <LoaderCircle className="animate-spin" /> : <FileUp />}Upload and index</Button></>}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
