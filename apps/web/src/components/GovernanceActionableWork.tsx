import { CalendarClock, CircleAlert, FileCheck2, FileClock, FileWarning, History } from "lucide-react";
import Link from "next/link";
import type { GovernanceActionableWork } from "@/lib/api";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

const cards = [
  { key: "awaiting_approval", title: "Awaiting approval", description: "Indexed document versions ready for governance review.", icon: FileCheck2, route: "documents/attention", accent: "border-l-status-pending" },
  { key: "imports_processing", title: "Imports processing", description: "Staged documents still being checked or matched.", icon: FileClock, route: "documents/imports", accent: "border-l-status-info" },
  { key: "imports_warning", title: "Imports needing attention", description: "Staged documents that could not pass preflight.", icon: FileWarning, route: "documents/imports", accent: "border-l-status-warning" },
  { key: "scheduled_changes", title: "Scheduled changes", description: "Approved versions waiting for their authority date.", icon: CalendarClock, route: "documents/scheduled", accent: "border-l-status-pending" },
  { key: "review_due_soon", title: "Review due soon", description: "Document families due for review in the next 30 days.", icon: History, route: "documents?review_status=due_soon", accent: "border-l-status-info" },
  { key: "review_overdue", title: "Reviews overdue", description: "Document families past their review date.", icon: CircleAlert, route: "documents?review_status=overdue", accent: "border-l-status-destructive" },
] as const;

export function GovernanceActionableWork({ data, workspacePublicId }: Readonly<{ data: GovernanceActionableWork; workspacePublicId: string }>) {
  return <section aria-labelledby="actionable-work-title" className="grid gap-5">
    <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-brand">Current governance work</p><h2 className="mt-2 text-2xl font-bold" id="actionable-work-title">What needs attention now</h2><p className="mt-2 max-w-2xl text-foreground-muted">Live workspace state, recalculated whenever this page loads. Dismissing a notification never removes work from these cards.</p></div>
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{cards.map(({ key, title, description, icon: Icon, route, accent }) => <Card className={cn("border-l-4 transition-colors hover:bg-surface-muted", data[key] > 0 ? accent : "border-l-border bg-surface-muted/70")} key={key}>
      <CardHeader><div className="flex items-center justify-between gap-3"><span className="grid size-10 place-items-center rounded-xl bg-brand/10 text-brand"><Icon aria-hidden="true" className="size-5" /></span><span className={cn("rounded-lg bg-surface-raised px-3 py-1 text-2xl font-bold tabular-nums", data[key] === 0 && "text-foreground-muted")}>{data[key]}</span></div><CardTitle>{title}</CardTitle><CardDescription>{description}</CardDescription></CardHeader>
      <CardContent><Link className="text-sm font-semibold text-brand underline-offset-4 hover:underline" href={`/app/workspaces/${workspacePublicId}/${route}`}>View details</Link></CardContent>
    </Card>)}</div>
  </section>;
}
