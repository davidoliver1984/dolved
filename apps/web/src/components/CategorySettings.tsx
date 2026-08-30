"use client";

import { Archive, FolderPlus, Pencil, Tags } from "lucide-react";
import { type FormEvent, useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/status-badge";
import {
  archiveDocumentCategory,
  createDocumentCategory,
  firstError,
  renameDocumentCategory,
  type DocumentCategory,
} from "@/lib/api";

export function CategorySettings({ canManage, initialCategories, workspacePublicId }: Readonly<{ canManage: boolean; initialCategories: DocumentCategory[]; workspacePublicId: string }>) {
  const [categories, setCategories] = useState(initialCategories);
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const name = new FormData(form).get("name")?.toString() ?? "";
    setError(null);
    startTransition(async () => {
      try {
        const response = await createDocumentCategory(workspacePublicId, name);
        setCategories((items) => [...items, response.data].sort((left, right) => left.name.localeCompare(right.name)));
        form.reset();
      } catch (caught) {
        setError(firstError(caught));
      }
    });
  }

  function rename(category: DocumentCategory, event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const name = new FormData(event.currentTarget).get("name")?.toString() ?? "";
    setError(null);
    startTransition(async () => {
      try {
        const response = await renameDocumentCategory(workspacePublicId, category.public_id, name);
        setCategories((items) => items.map((item) => item.public_id === category.public_id ? response.data : item));
      } catch (caught) {
        setError(firstError(caught));
      }
    });
  }

  function archive(category: DocumentCategory) {
    setError(null);
    startTransition(async () => {
      try {
        const response = await archiveDocumentCategory(workspacePublicId, category.public_id);
        setCategories((items) => items.map((item) => item.public_id === category.public_id ? response.data : item));
      } catch (caught) {
        setError(firstError(caught));
      }
    });
  }

  const active = categories.filter((category) => category.status === "active");
  const archived = categories.filter((category) => category.status === "archived");

  return <div className="grid gap-6">
    <header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Library settings</p><h1 className="mt-2 text-3xl font-semibold">Categories</h1><p className="mt-2 max-w-3xl text-foreground-muted">Create a stable structure for the knowledge library. Archived categories remain visible on existing document families but cannot be assigned to new ones.</p></header>
    <Card><CardHeader><div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-full bg-brand/10 text-brand"><FolderPlus aria-hidden="true" className="size-5" /></span><div><CardTitle>Create a category</CardTitle><CardDescription className="mt-1">Categories are workspace-owned. Tags stay freeform and do not have a separate settings page.</CardDescription></div></div></CardHeader><CardContent>{canManage ? <form className="flex flex-col gap-2 sm:flex-row" onSubmit={create}><label className="sr-only" htmlFor="new-category">Category name</label><Input className="sm:max-w-md" id="new-category" maxLength={100} name="name" placeholder="For example, Clinical governance" required /><Button disabled={pending} type="submit"><FolderPlus aria-hidden="true" />Create category</Button></form> : <p className="text-sm text-foreground-muted">Only workspace owners and administrators can change categories.</p>}{error ? <p className="mt-3 text-sm text-destructive" role="alert">{error}</p> : null}</CardContent></Card>
    <section aria-labelledby="active-categories" className="grid gap-3"><div><h2 className="text-xl font-semibold" id="active-categories">Active categories</h2><p className="mt-1 text-sm text-foreground-muted">Available in document-family assignment controls.</p></div>{active.length ? <div className="grid gap-3 lg:grid-cols-2">{active.map((category) => <Card key={category.public_id}><CardContent className="grid gap-3 pt-5"><div className="flex items-center justify-between gap-3"><div className="flex min-w-0 items-center gap-2"><Tags aria-hidden="true" className="size-4 shrink-0 text-brand" /><span className="truncate font-semibold">{category.name}</span></div><StatusBadge status="success">Active</StatusBadge></div>{canManage ? <div className="flex flex-col gap-2 sm:flex-row"><form className="flex min-w-0 flex-1 gap-2" onSubmit={(event) => rename(category, event)}><Input aria-label={`Rename ${category.name}`} defaultValue={category.name} maxLength={100} name="name" required /><Button aria-label={`Save name for ${category.name}`} disabled={pending} size="icon" type="submit" variant="secondary"><Pencil aria-hidden="true" /></Button></form><Button disabled={pending} onClick={() => archive(category)} type="button" variant="outline"><Archive aria-hidden="true" />Archive</Button></div> : null}</CardContent></Card>)}</div> : <p className="rounded-xl border border-dashed border-border p-6 text-sm text-foreground-muted">No active categories yet.</p>}</section>
    <section aria-labelledby="archived-categories" className="grid gap-3"><div><h2 className="text-xl font-semibold" id="archived-categories">Archived categories</h2><p className="mt-1 text-sm text-foreground-muted">Retained so existing document history remains truthful.</p></div>{archived.length ? <div className="flex flex-wrap gap-2">{archived.map((category) => <span className="inline-flex items-center gap-2 rounded-full border border-border bg-surface-muted px-3 py-2 text-sm" key={category.public_id}><Archive aria-hidden="true" className="size-4" />{category.name}</span>)}</div> : <p className="text-sm text-foreground-muted">No archived categories.</p>}</section>
  </div>;
}
