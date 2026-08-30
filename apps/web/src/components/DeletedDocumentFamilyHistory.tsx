import { Archive, ChevronLeft, ChevronRight } from "lucide-react";
import Link from "next/link";
import { EmptyState } from "@/components/ui/empty-state";
import { buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatDateTime } from "@/lib/date";
import type { DeletedDocumentFamilyPage } from "@/lib/server-api";
import { cn } from "@/lib/utils";

export function DeletedDocumentFamilyHistory({ page, workspacePublicId }: Readonly<{ page: DeletedDocumentFamilyPage; workspacePublicId: string }>) {
  if (!page.data.length) {
    return <EmptyState description="Completed document-family deletions will appear here with their retained audit lineage." icon={Archive} title="No deleted document families" />;
  }

  const pageHref = (value: number) => `/app/workspaces/${workspacePublicId}/documents/deleted?page=${value}`;

  return (
    <div className="grid gap-4">
      <p className="text-sm text-foreground-muted">{page.meta.total} completed deletion {page.meta.total === 1 ? "record" : "records"}</p>
      <div className="grid gap-4 lg:grid-cols-2">
        {page.data.map((item) => (
          <Card key={item.operation_public_id}>
            <CardHeader>
              <p className="text-xs font-bold uppercase tracking-[0.12em] text-brand">Deleted family</p>
              <CardTitle>{item.family.name}</CardTitle>
            </CardHeader>
            <CardContent>
              <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <HistoryField label="Deleted" value={formatDateTime(item.deleted_at)} />
                <HistoryField label="Requested by" value={item.requested_by?.name ?? "Account no longer available"} />
                <HistoryField label="Versions removed" value={String(item.versions_removed)} />
                <HistoryField label="Audit reference" value={item.audit_reference ?? "Not available"} />
                <HistoryField className="sm:col-span-2" label="Reason" value={item.reason ?? "No reason was recorded."} />
              </dl>
            </CardContent>
          </Card>
        ))}
      </div>
      {page.meta.last_page > 1 ? (
        <nav aria-label="Deleted history pages" className="flex items-center justify-between gap-3">
          <Link aria-disabled={page.meta.current_page <= 1} className={cn(buttonVariants({ variant: "outline" }), page.meta.current_page <= 1 && "pointer-events-none opacity-50")} href={pageHref(Math.max(1, page.meta.current_page - 1))}><ChevronLeft aria-hidden="true" />Previous</Link>
          <span className="text-sm text-foreground-muted">Page {page.meta.current_page} of {page.meta.last_page}</span>
          <Link aria-disabled={page.meta.current_page >= page.meta.last_page} className={cn(buttonVariants({ variant: "outline" }), page.meta.current_page >= page.meta.last_page && "pointer-events-none opacity-50")} href={pageHref(Math.min(page.meta.last_page, page.meta.current_page + 1))}>Next<ChevronRight aria-hidden="true" /></Link>
        </nav>
      ) : null}
    </div>
  );
}

function HistoryField({ className, label, value }: Readonly<{ className?: string; label: string; value: string }>) {
  return <div className={className}><dt className="text-foreground-muted">{label}</dt><dd className="mt-1 break-words font-medium">{value}</dd></div>;
}
