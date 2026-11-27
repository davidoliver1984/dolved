"use client";

import { useRouter } from "next/navigation";
import { type FormEvent, useMemo, useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import { HelpTip } from "@/components/ui/tooltip";
import { createApplicabilitySuccessor, type DocumentVersion } from "@/lib/api";

type Location = { public_id: string; name: string };

export function DocumentApplicabilityEditor({
  locations,
  version,
  workspacePublicId,
}: Readonly<{
  locations: Location[];
  version: DocumentVersion;
  workspacePublicId: string;
}>) {
  const router = useRouter();
  const initialLocationIds = useMemo(() => version.applicability.locations.map((location) => location.public_id).sort(), [version.applicability.locations]);
  const [mode, setMode] = useState<"universal" | "selected">(version.applicability.scope === "universal" ? "universal" : "selected");
  const [selectedLocationIds, setSelectedLocationIds] = useState<string[]>(initialLocationIds);
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();
  const resultingLocationIds = mode === "universal" ? [] : selectedLocationIds;
  const unchanged = JSON.stringify([...resultingLocationIds].sort()) === JSON.stringify(initialLocationIds);

  if (!version.capabilities.create_applicability_successor) return null;

  const toggleLocation = (publicId: string) => {
    setSelectedLocationIds((current) => current.includes(publicId) ? current.filter((value) => value !== publicId) : [...current, publicId]);
  };

  return (
    <details className="rounded-xl border border-border bg-surface p-4">
      <summary className="cursor-pointer font-semibold">Edit locations</summary>
      <div className="mt-3 grid gap-4">
        <p className="max-w-3xl text-sm text-foreground-muted">Choose where this document should be used. Saving records the change as a new version while keeping the same file.</p>
        <form className="grid gap-4" onSubmit={(event: FormEvent<HTMLFormElement>) => {
          event.preventDefault();
          const data = new FormData(event.currentTarget);
          setError(null);
          startTransition(async () => {
            try {
              await createApplicabilitySuccessor(workspacePublicId, version.public_id, data.get("effective_from")?.toString() ?? "", resultingLocationIds);
              router.refresh();
            } catch (caught) {
              setError(caught instanceof Error ? caught.message : "The document locations could not be updated.");
            }
          });
        }}>
          <div className="grid max-w-xs gap-1 text-sm font-semibold"><span className="flex items-center gap-1"><label htmlFor="applicability-effective-from">Effective from</label><HelpTip label="Effective from" text="The date this location change should start being used." /></span><input className="min-h-11 rounded-lg border border-input bg-background px-3 font-normal" id="applicability-effective-from" name="effective_from" required type="date" /></div>
          <fieldset className="grid gap-2 rounded-xl border border-border bg-surface-muted/60 p-3">
            <legend className="px-2 text-sm font-semibold">Where does this document apply?</legend>
            <label className="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-surface-hover"><input checked={mode === "universal"} className="size-4" name="applicability_mode" onChange={() => setMode("universal")} type="radio" value="universal" />Organisation-wide</label>
            <label className="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-surface-hover"><input checked={mode === "selected"} className="size-4" name="applicability_mode" onChange={() => setMode("selected")} type="radio" value="selected" />Selected registered locations</label>
            {mode === "selected" ? <div className="mt-1 grid gap-2 rounded-lg border border-border bg-surface p-3">
              {locations.length ? locations.map((location) => <label className="flex items-center gap-2 text-sm" key={location.public_id}><input checked={selectedLocationIds.includes(location.public_id)} onChange={() => toggleLocation(location.public_id)} type="checkbox" />{location.name}</label>) : <p className="text-sm text-foreground-muted">No registered locations are available.</p>}
            </div> : null}
          </fieldset>
          {mode === "selected" && selectedLocationIds.length === 0 ? <p className="text-sm text-destructive" role="alert">Select at least one location, or choose organisation-wide.</p> : null}
          {unchanged ? <p className="text-sm text-foreground-muted">These are the document’s current locations.</p> : null}
          {error ? <p className="text-sm text-destructive" role="alert">{error}</p> : null}
          <Button className="justify-self-start" disabled={pending || unchanged || (mode === "selected" && selectedLocationIds.length === 0)} type="submit">{pending ? "Saving…" : "Save locations"}</Button>
        </form>
      </div>
    </details>
  );
}
