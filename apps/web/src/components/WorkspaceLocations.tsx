"use client";

import { Archive, Building2, Plus } from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import { StatusBadge } from "@/components/ui/status-badge";
import { createWorkspaceLocation, firstError, retireWorkspaceLocation, workspaceLocations, type WorkspaceLocation } from "@/lib/api";

export function WorkspaceLocations({ workspaceId }: Readonly<{ workspaceId: string }>) {
  const [locations, setLocations] = useState<WorkspaceLocation[]>([]);
  const [name, setName] = useState("");
  const [kind, setKind] = useState<WorkspaceLocation["kind"]>("site");
  const [parentId, setParentId] = useState("");
  const [busy, setBusy] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const refresh = async () => setLocations(await workspaceLocations(workspaceId));

  useEffect(() => {
    let active = true;
    void workspaceLocations(workspaceId)
      .then((nextLocations) => { if (active) setLocations(nextLocations); })
      .catch((caught) => { if (active) setError(firstError(caught)); })
      .finally(() => { if (active) setBusy(false); });
    return () => { active = false; };
  }, [workspaceId]);

  const create = async (event: FormEvent) => {
    event.preventDefault(); setBusy(true); setError(null);
    try { await createWorkspaceLocation(workspaceId, name.trim(), kind, parentId || null); setName(""); setParentId(""); await refresh(); }
    catch (caught) { setError(firstError(caught)); }
    finally { setBusy(false); }
  };

  const retire = async (location: WorkspaceLocation) => {
    setBusy(true); setError(null);
    try { await retireWorkspaceLocation(workspaceId, location.public_id); await refresh(); }
    catch (caught) { setError(firstError(caught)); }
    finally { setBusy(false); }
  };

  const activeLocations = locations.filter((location) => location.status === "active");

  return <div className="grid gap-6 xl:grid-cols-[minmax(18rem,0.75fr)_minmax(28rem,1.25fr)]">
    <Card><CardHeader><CardTitle className="flex items-center gap-2"><Plus className="size-5 text-brand" />Add a registered location</CardTitle><CardDescription>Locations become explicit choices during document review and when starting a conversation.</CardDescription></CardHeader><CardContent>{error ? <Notice className="mb-4" tone="destructive">{error}</Notice> : null}<form className="grid gap-4" onSubmit={create}><label className="grid gap-2 text-sm font-semibold" htmlFor="location-name">Name<Input id="location-name" minLength={2} onChange={(event) => setName(event.target.value)} required value={name} /></label><label className="grid gap-2 text-sm font-semibold" htmlFor="location-kind">Type<select className="min-h-11 rounded-md border border-input bg-background px-3" id="location-kind" onChange={(event) => setKind(event.target.value as WorkspaceLocation["kind"])} value={kind}><option value="site">Site</option><option value="service">Service</option><option value="region">Region</option></select></label><label className="grid gap-2 text-sm font-semibold" htmlFor="location-parent">Parent location <span className="font-normal text-foreground-muted">Optional</span><select className="min-h-11 rounded-md border border-input bg-background px-3" id="location-parent" onChange={(event) => setParentId(event.target.value)} value={parentId}><option value="">None</option>{activeLocations.map((location) => <option key={location.public_id} value={location.public_id}>{location.name}</option>)}</select></label><Button disabled={busy || !name.trim()} type="submit">Add location</Button></form></CardContent></Card>
    <Card><CardHeader><CardTitle className="flex items-center gap-2"><Building2 className="size-5 text-brand" />Registered locations</CardTitle><CardDescription>Retiring a location removes it from future choices while preserving existing document history and applicability.</CardDescription></CardHeader><CardContent className="grid gap-3">{locations.length ? locations.map((location) => <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-4" key={location.public_id}><div><div className="flex flex-wrap items-center gap-2"><strong>{location.name}</strong>{location.status === "retired" ? <StatusBadge status="unavailable">Retired</StatusBadge> : null}</div>{location.parent ? <p className="text-sm text-foreground-muted">Within {location.parent.name}</p> : null}{location.usage.active_children > 0 ? <p className="text-xs text-foreground-muted">Retire {location.usage.active_children} active child location{location.usage.active_children === 1 ? "" : "s"} first.</p> : null}</div><div className="flex items-center gap-2"><StatusBadge status="info">{location.kind}</StatusBadge>{location.capabilities.retire ? <AlertDialog><AlertDialogTrigger asChild><Button disabled={busy} size="sm" type="button" variant="outline"><Archive />Retire</Button></AlertDialogTrigger><AlertDialogContent><AlertDialogTitle>Retire {location.name}?</AlertDialogTitle><AlertDialogDescription>This location will no longer be available for new conversations, imports or applicability changes. Existing document references and audit history will remain intact.</AlertDialogDescription><AlertDialogFooter><AlertDialogCancel>Cancel</AlertDialogCancel><AlertDialogAction onClick={() => void retire(location)}>Retire location</AlertDialogAction></AlertDialogFooter></AlertDialogContent></AlertDialog> : null}</div></div>) : <p className="text-sm text-foreground-muted">No locations have been registered yet.</p>}</CardContent></Card>
  </div>;
}
