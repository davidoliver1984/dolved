"use client";

import { Bookmark, BookmarkPlus, Pencil, Trash2 } from "lucide-react";
import Link from "next/link";
import { type FormEvent, useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  createSavedView,
  deleteSavedView,
  firstError,
  renameSavedView,
  type SavedView,
  type SavedViewDefinition,
} from "@/lib/api";

export function SavedViewsPanel({ currentDefinition, initialViews, workspacePublicId }: Readonly<{ currentDefinition: SavedViewDefinition; initialViews: SavedView[]; workspacePublicId: string }>) {
  const [views, setViews] = useState(initialViews);
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const name = new FormData(form).get("name")?.toString() ?? "";
    setError(null);
    startTransition(async () => {
      try {
        const response = await createSavedView(workspacePublicId, name, currentDefinition);
        setViews((items) => [...items, response.data].sort((left, right) => left.name.localeCompare(right.name)));
        form.reset();
      } catch (caught) {
        setError(firstError(caught));
      }
    });
  }

  function rename(view: SavedView, event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const name = new FormData(event.currentTarget).get("name")?.toString() ?? "";
    setError(null);
    startTransition(async () => {
      try {
        const response = await renameSavedView(workspacePublicId, view.public_id, name);
        setViews((items) => items.map((item) => item.public_id === view.public_id ? response.data : item));
      } catch (caught) {
        setError(firstError(caught));
      }
    });
  }

  function remove(view: SavedView) {
    setError(null);
    startTransition(async () => {
      try {
        await deleteSavedView(workspacePublicId, view.public_id);
        setViews((items) => items.filter((item) => item.public_id !== view.public_id));
      } catch (caught) {
        setError(firstError(caught));
      }
    });
  }

  return <Card>
    <CardHeader>
      <div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-full bg-brand/10 text-brand"><Bookmark aria-hidden="true" className="size-5" /></span><div><CardTitle>Saved views</CardTitle><CardDescription className="mt-1">Keep your current search, filters and sort order. Results are always refreshed from the live library.</CardDescription></div></div>
    </CardHeader>
    <CardContent className="grid gap-4">
      <form className="flex flex-col gap-2 sm:flex-row" onSubmit={save}><label className="sr-only" htmlFor="saved-view-name">Saved view name</label><Input className="sm:max-w-sm" id="saved-view-name" maxLength={100} name="name" placeholder="Name this view" required /><Button disabled={pending} type="submit"><BookmarkPlus aria-hidden="true" />Save current view</Button></form>
      {error ? <p className="text-sm text-destructive" role="alert">{error}</p> : null}
      {views.length ? <ul className="grid gap-2 lg:grid-cols-2">{views.map((view) => <li className="rounded-lg border border-border bg-surface-muted p-3" key={view.public_id}>
        <div className="flex items-center justify-between gap-3"><Link className="min-w-0 truncate font-semibold underline-offset-4 hover:text-brand hover:underline" href={`/app/workspaces/${workspacePublicId}/documents/saved/${view.public_id}`}>{view.name}</Link><Button aria-label={`Delete ${view.name}`} disabled={pending} onClick={() => remove(view)} size="icon" type="button" variant="ghost"><Trash2 aria-hidden="true" /></Button></div>
        <details className="mt-2"><summary className="cursor-pointer text-xs font-semibold text-foreground-muted">Rename</summary><form className="mt-2 flex gap-2" onSubmit={(event) => rename(view, event)}><Input aria-label={`New name for ${view.name}`} defaultValue={view.name} maxLength={100} name="name" required /><Button aria-label={`Save name for ${view.name}`} disabled={pending} size="icon" type="submit" variant="secondary"><Pencil aria-hidden="true" /></Button></form></details>
      </li>)}</ul> : <p className="text-sm text-foreground-muted">No saved views yet. Apply useful filters, then save the view for next time.</p>}
    </CardContent>
  </Card>;
}
