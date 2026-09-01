import { notFound } from "next/navigation";
import Link from "next/link";
import { CheckCircle2 } from "lucide-react";
import { BulkOperationDetail } from "@/components/BulkOperationDetail";
import { DocumentLibraryTable } from "@/components/DocumentLibraryTable";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import type { BulkOperationSnapshot, DocumentFamilyLibraryRow } from "@/lib/api";

const states = ["selection", "mixed-preflight", "applicability", "queued", "running", "partial", "cancelled", "retryable", "successful", "zero"] as const;
type State = typeof states[number];

function row(publicId: string, name: string, state: DocumentFamilyLibraryRow["state"]): DocumentFamilyLibraryRow {
  return {
    public_id: publicId, name, description: null, category: { public_id: `category-${publicId}`, name: "Clinical", status: "active" }, tags: [],
    owner: { public_id: "owner-1", name: "David Oliver", needs_reassignment: false }, review_due_date: "2026-11-30",
    last_meaningful_update: "2026-09-01T08:00:00Z", state, scheduled_effective_from: null, version_count: 2,
    historical: false, current_version: state === "current" ? { public_id: `version-${publicId}`, technical_status: "indexed", source_filename: `${name.toLocaleLowerCase().replaceAll(" ", "-")}.pdf`, media_type: "application/pdf", size_bytes: 428000, checksum_verification_status: "verified", governance_status: "approved", effective_from: "2026-01-01T00:00:00Z", approved_at: "2026-01-01T00:00:00Z", withdrawn_at: null, extraction_warning_count: 0 } : null,
  };
}

const libraryRows = [row("medication", "Medication administration", "current"), row("infection", "Infection prevention", "draft"), row("safeguarding", "Safeguarding procedure", "current")];

function snapshot(status: string, overrides: Partial<BulkOperationSnapshot["counts"]> = {}): BulkOperationSnapshot {
  const counts = { total: 5, eligible: 0, excluded: 1, open_attempts: 0, waiting_on_subordinate: 0, succeeded: 4, skipped: 0, failed_retryable: 0, failed_permanent: 0, cancelled: 0, ...overrides };
  return {
    public_id: `fixture-${status}`, operation_type: "bulk_approval", status, selection_mode: "all_filtered", payload: {}, filters: { search: "policy" }, membership_digest: "f".repeat(64), confirmed_at: "2026-09-01T08:10:00Z", cancellation_requested_at: status.includes("cancel") ? "2026-09-01T08:12:00Z" : null, counts,
    exclusions: { already_approved_or_current: counts.excluded },
    items: [
      { ordinal: 1, target_kind: "version", target_public_id: "version-1", target_display_label: "Medication procedure v2.pdf", eligibility_status: "eligible", exclusion_reason: null, execution_status: counts.failed_retryable ? "failed_retryable" : counts.cancelled ? "cancelled" : "succeeded", terminal_reason: counts.failed_retryable ? "lease_expired" : counts.cancelled ? "cancelled_before_claim" : "approved", result_identity: counts.failed_retryable || counts.cancelled ? null : "approval:version-1" },
      { ordinal: 2, target_kind: "version", target_public_id: "version-2", target_display_label: "Safeguarding procedure.pdf", eligibility_status: counts.excluded > 0 ? "excluded" : "eligible", exclusion_reason: counts.excluded > 0 ? "already_approved_or_current" : null, execution_status: counts.excluded > 0 ? "excluded" : "succeeded", terminal_reason: counts.excluded > 0 ? null : "approved", result_identity: counts.excluded > 0 ? null : "approval:version-2" },
    ],
  };
}

function Preflight({ applicability = false, zero = false }: Readonly<{ applicability?: boolean; zero?: boolean }>) {
  return <Card className="border-brand/40"><CardHeader><CardTitle>{applicability ? "Confirm Change applicability" : "Confirm Approve latest draft versions"}</CardTitle><CardDescription>Membership is now frozen at 5 items. Later filter or library changes cannot retarget it.</CardDescription></CardHeader><CardContent className="grid gap-4"><div className="grid gap-3 sm:grid-cols-3"><div className="rounded-lg bg-surface-muted p-3"><span className="text-sm text-foreground-muted">Total frozen</span><strong className="mt-1 block text-2xl">5</strong></div><div className="rounded-lg bg-success/10 p-3"><span className="text-sm text-foreground-muted">Eligible</span><strong className="mt-1 block text-2xl">{zero ? 0 : 4}</strong></div><div className="rounded-lg bg-warning/10 p-3"><span className="text-sm text-foreground-muted">Excluded</span><strong className="mt-1 block text-2xl">{zero ? 5 : 1}</strong></div></div><details open><summary className="cursor-pointer font-semibold">Review excluded items</summary><div className="mt-3 rounded-lg border border-border p-3"><strong>Medication procedure v1.pdf</strong><p className="text-sm text-foreground-muted">Already approved or current</p></div></details>{applicability ? <Notice tone="warning">This replaces applicability for every eligible family with universal scope. Applicability organises policy scope; it does not grant document access.</Notice> : <Notice tone="info">Approval is not import promotion. Searchability remains governed by the existing downstream lifecycle.</Notice>}{zero ? <Notice tone="warning">Nothing can be changed. Review the exclusions or start a new selection.</Notice> : null}<div className="flex justify-end gap-2"><Button variant="outline">Back</Button><Button disabled={zero}>Confirm and start</Button></div></CardContent></Card>;
}

function checkpoint(state: State) {
  if (state === "selection") return <DocumentLibraryTable canManage metadata={{ categories: [{ public_id: "clinical", name: "Clinical", status: "active" }], tags: [{ public_id: "urgent", name: "Urgent" }], owners: [{ public_id: "owner-1", name: "David Oliver" }], locations: [{ public_id: "willow-bank", name: "Willow Bank Community Service" }, { public_id: "midlands", name: "Midlands Region" }] }} page={{ data: libraryRows, meta: { current_page: 1, last_page: 1, per_page: 25, total: 42 } }} query={{ search: "policy" }} workspacePublicId="visual-workspace" />;
  if (state === "mixed-preflight") return <Preflight />;
  if (state === "applicability") return <Preflight applicability />;
  if (state === "zero") return <Preflight zero />;
  if (state === "queued") return <BulkOperationDetail initial={snapshot("queued", { eligible: 4, succeeded: 0 })} poll={false} workspacePublicId="visual-workspace" />;
  if (state === "running") return <BulkOperationDetail initial={snapshot("running", { eligible: 2, open_attempts: 1, waiting_on_subordinate: 1, succeeded: 0 })} poll={false} workspacePublicId="visual-workspace" />;
  if (state === "partial") return <BulkOperationDetail initial={snapshot("completed_with_exceptions", { succeeded: 2, skipped: 1, failed_permanent: 1 })} poll={false} workspacePublicId="visual-workspace" />;
  if (state === "cancelled") return <BulkOperationDetail initial={snapshot("cancelled_after_partial_execution", { succeeded: 2, cancelled: 2 })} poll={false} workspacePublicId="visual-workspace" />;
  if (state === "retryable") return <BulkOperationDetail initial={snapshot("completed_with_exceptions", { succeeded: 3, failed_retryable: 1 })} poll={false} workspacePublicId="visual-workspace" />;
  return <BulkOperationDetail initial={snapshot("completed", { excluded: 0, succeeded: 5 })} poll={false} workspacePublicId="visual-workspace" />;
}

export default async function BulkOperationsReference({ searchParams }: Readonly<{ searchParams: Promise<{ state?: string }> }>) {
  if (process.env.NODE_ENV === "production") notFound();
  const requested = (await searchParams).state;
  const state: State = states.includes(requested as State) ? requested as State : "selection";
  return <main className="mx-auto max-w-7xl p-6 sm:p-10"><p className="mb-5 rounded-lg bg-surface-muted p-3 text-sm text-foreground-muted">Development-only representative fixtures using production bulk-operation components. They do not represent workspace evidence or execute changes.</p><nav aria-label="Bulk operation visual checkpoints" className="mb-6 flex flex-wrap gap-2">{states.map((candidate) => <Button asChild key={candidate} size="sm" variant={candidate === state ? "default" : "outline"}><Link href={`/design-system/bulk-operations?state=${candidate}`}>{candidate.replaceAll("-", " ")}</Link></Button>)}</nav>{checkpoint(state)}<p className="mt-6 flex items-center gap-2 text-sm text-foreground-muted"><CheckCircle2 className="size-4 text-success" />Use the links above to review the required states in both themes and responsive widths.</p></main>;
}
