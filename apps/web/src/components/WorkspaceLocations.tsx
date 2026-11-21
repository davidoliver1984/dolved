"use client";

import { Building2, Plus } from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import { StatusBadge } from "@/components/ui/status-badge";
import { createWorkspaceLocation, firstError, workspaceLocations, type WorkspaceLocation } from "@/lib/api";

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
    void workspaceLocations(workspaceId).then((nextLocations) => { if (active) setLocations(nextLocations); }).catch((caught) => { if (active) setError(firstError(caught)); }).finally(() => { if (active) setBusy(false); });
    return () => { active = false; };
  }, [workspaceId]);
  const create = async (event: FormEvent) => {
    event.preventDefault(); setBusy(true); setError(null);
    try { await createWorkspaceLocation(workspaceId, name.trim(), kind, parentId || null); setName(""); setParentId(""); await refresh(); }
    catch (caught) { setError(firstError(caught)); }
    finally { setBusy(false); }
  };
  return <div className="grid gap-6 xl:grid-cols-[minmax(18rem,0.75fr)_minmax(28rem,1.25fr)]"><Card><CardHeader><CardTitle className="flex items-center gap-2"><Plus className="size-5 text-brand" />Add a registered location</CardTitle><CardDescription>Locations become explicit choices during document review and when starting a conversation.</CardDescription></CardHeader><CardContent>{error ? <Notice className="mb-4" tone="destructive">{error}</Notice> : null}<form className="grid gap-4" onSubmit={create}><label className="grid gap-2 text-sm font-semibold" htmlFor="location-name">Name<Input id="location-name" minLength={2} onChange={(event) => setName(event.target.value)} required value={name} /></label><label className="grid gap-2 text-sm font-semibold" htmlFor="location-kind">Type<select className="min-h-11 rounded-md border border-input bg-background px-3" id="location-kind" onChange={(event) => setKind(event.target.value as WorkspaceLocation["kind"])} value={kind}><option value="site">Site</option><option value="service">Service</option><option value="region">Region</option></select></label><label className="grid gap-2 text-sm font-semibold" htmlFor="location-parent">Parent location <span className="font-normal text-foreground-muted">Optional</span><select className="min-h-11 rounded-md border border-input bg-background px-3" id="location-parent" onChange={(event) => setParentId(event.target.value)} value={parentId}><option value="">None</option>{locations.map((location) => <option key={location.public_id} value={location.public_id}>{location.name}</option>)}</select></label><Button disabled={busy || !name.trim()} type="submit">Add location</Button></form></CardContent></Card><Card><CardHeader><CardTitle className="flex items-center gap-2"><Building2 className="size-5 text-brand" />Registered locations</CardTitle><CardDescription>Document applicability may include multiple locations. Organisation-wide remains a separate explicit choice.</CardDescription></CardHeader><CardContent className="grid gap-3">{locations.length ? locations.map((location) => <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-4" key={location.public_id}><div><strong>{location.name}</strong>{location.parent ? <p className="text-sm text-foreground-muted">Within {location.parent.name}</p> : null}</div><StatusBadge status="info">{location.kind}</StatusBadge></div>) : <p className="text-sm text-foreground-muted">No locations have been registered yet.</p>}</CardContent></Card></div>;
}
