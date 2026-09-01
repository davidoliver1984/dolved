import { CheckCircle2, Clock3, ListChecks } from "lucide-react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import type { BulkOperationPage } from "@/lib/api";
import { formatDateTime } from "@/lib/date";

const labels: Record<string, string> = {
  awaiting_confirmation: "Awaiting confirmation",
  queued: "Queued",
  running: "Running",
  completed: "Completed",
  completed_with_exclusions: "Completed with exclusions",
  completed_with_exceptions: "Completed with exceptions",
  cancelled: "Cancelled",
  cancelled_after_partial_execution: "Cancelled after partial execution",
  failed_before_execution: "Failed before execution",
};

function label(value: string): string {
  return labels[value] ?? value.replaceAll("_", " ").replace(/^./, (letter) => letter.toUpperCase());
}

function tone(value: string): StatusTone {
  if (value === "completed") return "success";
  if (value.includes("failed")) return "destructive";
  if (value.includes("cancel") || value.includes("exception") || value.includes("exclusion")) return "warning";
  if (["queued", "running", "awaiting_confirmation"].includes(value)) return "pending";
  return "unavailable";
}

export function BulkOperationHistory({ page, workspacePublicId }: Readonly<{ page: BulkOperationPage; workspacePublicId: string }>) {
  const base = `/app/workspaces/${workspacePublicId}/documents/bulk`;

  if (page.data.length === 0) {
    return <EmptyState description="Prepared and confirmed bulk operations will appear here with their durable results." icon={ListChecks} title="No bulk operations yet" />;
  }

  return <div className="grid gap-5">
    <div className="grid gap-4 lg:grid-cols-2">
      {page.data.map((operation) => <Card key={operation.public_id}>
        <CardHeader>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div><CardTitle>{label(operation.operation_type)}</CardTitle><CardDescription className="mt-1">{operation.created_at ? formatDateTime(operation.created_at) : "Creation time unavailable"} · {operation.selection_mode === "all_filtered" ? "All filtered results" : "Selected page items"}</CardDescription></div>
            <StatusBadge status={tone(operation.status)}>{label(operation.status)}</StatusBadge>
          </div>
        </CardHeader>
        <CardContent className="grid gap-4">
          <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><span className="text-foreground-muted">Frozen</span><strong className="block text-lg">{operation.counts.total}</strong></div>
            <div><span className="text-foreground-muted">Succeeded</span><strong className="block text-lg">{operation.counts.succeeded}</strong></div>
            <div><span className="text-foreground-muted">Excluded</span><strong className="block text-lg">{operation.counts.excluded}</strong></div>
            <div><span className="text-foreground-muted">Exceptions</span><strong className="block text-lg">{operation.counts.failed_retryable + operation.counts.failed_permanent + operation.counts.skipped}</strong></div>
          </div>
          <Button asChild className="w-fit" size="sm" variant="outline"><Link href={`${base}/${operation.public_id}`}>{operation.status === "awaiting_confirmation" ? <Clock3 /> : <CheckCircle2 />}View operation</Link></Button>
        </CardContent>
      </Card>)}
    </div>
    {page.meta.last_page > 1 ? <nav aria-label="Bulk operation history pages" className="flex items-center justify-between gap-3"><Button asChild={page.meta.current_page > 1} disabled={page.meta.current_page <= 1} variant="outline">{page.meta.current_page > 1 ? <Link href={`${base}?page=${page.meta.current_page - 1}`}>Previous</Link> : <span>Previous</span>}</Button><span className="text-sm text-foreground-muted">Page {page.meta.current_page} of {page.meta.last_page}</span><Button asChild={page.meta.current_page < page.meta.last_page} disabled={page.meta.current_page >= page.meta.last_page} variant="outline">{page.meta.current_page < page.meta.last_page ? <Link href={`${base}?page=${page.meta.current_page + 1}`}>Next</Link> : <span>Next</span>}</Button></nav> : null}
  </div>;
}
