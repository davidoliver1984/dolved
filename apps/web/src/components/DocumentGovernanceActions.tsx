"use client";

import { useRouter } from "next/navigation";
import { type FormEvent, useState, useTransition } from "react";
import {
  approveDocumentVersion,
  correctDocumentVersionTimestamps,
  rescheduleDocumentVersion,
  withdrawDocumentVersion,
  type DocumentVersion,
} from "@/lib/api";
import { Button } from "@/components/ui/button";

export function DocumentGovernanceActions({
  version,
  workspacePublicId,
}: Readonly<{
  version: DocumentVersion;
  workspacePublicId: string;
}>) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();
  const capabilities = version.capabilities;

  const execute = (operation: () => Promise<unknown>) => {
    setError(null);
    startTransition(async () => {
      try {
        await operation();
        router.refresh();
      } catch (caught) {
        setError(caught instanceof Error ? caught.message : "The version could not be updated.");
      }
    });
  };

  if (!Object.values(capabilities).some(Boolean)) return null;

  return (
    <div className="grid gap-3 border-t border-border pt-4">
      <div className="flex flex-wrap gap-2">
        {capabilities.approve ? <Button disabled={pending} onClick={() => execute(() => approveDocumentVersion(workspacePublicId, version.public_id))} size="sm">Approve version</Button> : null}
        {capabilities.withdraw ? <Button disabled={pending} onClick={() => { if (window.confirm("Withdraw this approved version?")) execute(() => withdrawDocumentVersion(workspacePublicId, version.public_id)); }} size="sm" variant="destructive">Withdraw</Button> : null}
      </div>

      {capabilities.reschedule ? (
        <details className="rounded-lg border border-border p-3">
          <summary className="cursor-pointer text-sm font-semibold">Change start date</summary>
          <form className="mt-3 flex flex-wrap items-end gap-2" onSubmit={(event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            const value = new FormData(event.currentTarget).get("effective_from")?.toString() ?? "";
            execute(() => rescheduleDocumentVersion(workspacePublicId, version.public_id, value));
          }}>
            <label className="grid gap-1 text-sm">Effective date<input className="min-h-11 rounded-lg border border-input bg-background px-3" defaultValue={version.effective_from.slice(0, 10)} name="effective_from" required type="date" /></label>
            <Button disabled={pending} size="sm" type="submit" variant="secondary">Save schedule</Button>
          </form>
        </details>
      ) : null}

      {capabilities.correct_timestamps ? (
        <details className="rounded-lg border border-border p-3">
          <summary className="cursor-pointer text-sm font-semibold">Correct historical timestamps</summary>
          <form className="mt-3 grid gap-3 sm:grid-cols-2" onSubmit={(event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            const data = new FormData(event.currentTarget);
            const withdrawn = data.get("withdrawn_at")?.toString() ?? "";
            execute(() => correctDocumentVersionTimestamps(workspacePublicId, version.public_id, data.get("approved_at")?.toString() ?? "", withdrawn || null, data.get("reason")?.toString() ?? ""));
          }}>
            <label className="grid gap-1 text-sm">Approved at<input className="min-h-11 rounded-lg border border-input bg-background px-3" defaultValue={version.approved_at?.slice(0, 16)} name="approved_at" required type="datetime-local" /></label>
            <label className="grid gap-1 text-sm">Withdrawn at<input className="min-h-11 rounded-lg border border-input bg-background px-3" defaultValue={version.withdrawn_at?.slice(0, 16)} name="withdrawn_at" type="datetime-local" /></label>
            <label className="grid gap-1 text-sm sm:col-span-2">Reason<textarea className="min-h-24 rounded-lg border border-input bg-background p-3" name="reason" required /></label>
            <Button className="justify-self-start" disabled={pending} size="sm" type="submit" variant="secondary">Record correction</Button>
          </form>
        </details>
      ) : null}

      {error ? <p className="text-sm text-destructive" role="alert">{error}</p> : null}
    </div>
  );
}
