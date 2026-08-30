import { notFound } from "next/navigation";
import { DeletedDocumentFamilyHistory } from "@/components/DeletedDocumentFamilyHistory";
import type { DeletedDocumentFamilyPage } from "@/lib/server-api";

const fixture: DeletedDocumentFamilyPage = {
  data: [
    { family: { public_id: "family-one", name: "Archived lone-working procedure" }, operation_public_id: "operation-one", deleted_at: "2026-08-28T14:22:00Z", reason: "Replaced by the consolidated community safety procedure.", audit_reference: "audit-8f40c6", requested_by: { public_id: "user-one", name: "David Oliver" }, versions_removed: 3 },
    { family: { public_id: "family-two", name: "Retired visitor guidance" }, operation_public_id: "operation-two", deleted_at: "2026-08-20T09:05:00Z", reason: null, audit_reference: "audit-7e39a2", requested_by: null, versions_removed: 1 },
  ], meta: { current_page: 1, last_page: 1, per_page: 25, total: 2 },
};

export default function DeletedHistoryReference() {
  if (process.env.NODE_ENV === "production") notFound();
  return <main className="mx-auto max-w-6xl p-6 sm:p-10"><p className="mb-6 rounded-lg bg-surface-muted p-3 text-sm text-foreground-muted">Development-only representative fixture. It uses the production deleted-history component and does not represent workspace evidence.</p><header className="mb-6"><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Knowledge library</p><h1 className="mt-2 text-3xl font-semibold">Deleted history</h1><p className="mt-2 max-w-2xl text-foreground-muted">An immutable, owner-visible history of document families removed from the active library.</p></header><DeletedDocumentFamilyHistory page={fixture} workspacePublicId="fixture-workspace" /></main>;
}
