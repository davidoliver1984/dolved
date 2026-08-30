"use client";

import { useRouter } from "next/navigation";
import { type FormEvent, useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import type { DocumentFamilyMetadata } from "@/lib/server-api";
import { updateDocumentFamilyMetadata, updateDocumentFamilyTags } from "@/lib/api";

export function DocumentFamilyMetadataEditor({ family, workspacePublicId }: Readonly<{ family: DocumentFamilyMetadata; workspacePublicId: string }>) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  if (!family.capabilities.edit || !family.edit_options) return null;
  const options = family.edit_options;

  return (
    <details className="rounded-xl border border-border bg-surface p-4">
      <summary className="cursor-pointer font-semibold">Edit family details</summary>
      <form className="mt-4 grid gap-4 md:grid-cols-2" onSubmit={(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        setError(null);
        startTransition(async () => {
          try {
            await updateDocumentFamilyMetadata(workspacePublicId, family.public_id, {
              name: data.get("name")?.toString() ?? "",
              description: data.get("description")?.toString() || null,
              category_public_id: data.get("category_public_id")?.toString() || null,
              owner_public_id: data.get("owner_public_id")?.toString() ?? "",
              review_due_date: data.get("review_due_date")?.toString() || null,
            });
            await updateDocumentFamilyTags(workspacePublicId, family.public_id, data.getAll("tags").map(String));
            router.refresh();
          } catch (caught) {
            setError(caught instanceof Error ? caught.message : "The family details could not be updated.");
          }
        });
      }}>
        <label className="grid gap-1 text-sm font-semibold">Title<input className="min-h-11 rounded-lg border border-input bg-background px-3 font-normal" defaultValue={family.name} name="name" required /></label>
        <label className="grid gap-1 text-sm font-semibold">Review due<input className="min-h-11 rounded-lg border border-input bg-background px-3 font-normal" defaultValue={family.review_due_date ?? ""} name="review_due_date" type="date" /></label>
        <label className="grid gap-1 text-sm font-semibold">Category<select className="min-h-11 rounded-lg border border-input bg-background px-3 font-normal" defaultValue={family.category?.public_id ?? ""} name="category_public_id"><option value="">Uncategorised</option>{options.categories.map((category) => <option key={category.public_id} value={category.public_id}>{category.name}</option>)}</select></label>
        <label className="grid gap-1 text-sm font-semibold">Owner<select className="min-h-11 rounded-lg border border-input bg-background px-3 font-normal" defaultValue={family.owner?.public_id ?? ""} name="owner_public_id" required><option disabled value="">Choose an owner</option>{options.owners.map((owner) => <option key={owner.public_id} value={owner.public_id}>{owner.name}</option>)}</select></label>
        <label className="grid gap-1 text-sm font-semibold md:col-span-2">Description<textarea className="min-h-28 rounded-lg border border-input bg-background p-3 font-normal" defaultValue={family.description ?? ""} name="description" /></label>
        <fieldset className="grid gap-2 md:col-span-2"><legend className="text-sm font-semibold">Tags</legend><div className="flex flex-wrap gap-3">{options.tags.length ? options.tags.map((tag) => <label className="flex items-center gap-2 text-sm" key={tag.public_id}><input defaultChecked={family.tags.some((assigned) => assigned.public_id === tag.public_id)} name="tags" type="checkbox" value={tag.public_id} />{tag.name}</label>) : <p className="text-sm text-foreground-muted">No tags have been created.</p>}</div></fieldset>
        {error ? <p className="text-sm text-destructive md:col-span-2" role="alert">{error}</p> : null}
        <Button className="justify-self-start" disabled={pending} type="submit">{pending ? "Saving…" : "Save family details"}</Button>
      </form>
    </details>
  );
}
