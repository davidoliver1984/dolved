import { CheckCircle2 } from "lucide-react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { GovernanceActionableWork } from "@/components/GovernanceActionableWork";
import { GovernanceInbox } from "@/components/GovernanceInbox";
import { GovernanceNotificationPreferences } from "@/components/GovernanceNotificationPreferences";
import { ThemeToggle } from "@/components/ThemeToggle";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import type { GovernanceActionableWork as ActionableWork, GovernanceNotification } from "@/lib/api";

const checkpoints = [
  "bell", "inbox", "empty", "actionable-work", "awaiting-approval", "review-reminders", "processing", "scheduled", "bulk-outcome", "preferences", "mobile", "themes", "keyboard", "removed-target", "partial-failure",
] as const;
type Checkpoint = typeof checkpoints[number];

const baseNotification: GovernanceNotification = {
  public_id: "notification-1", title: "Import needs attention", message: "Your import finished with items that need attention.", severity: "action_required", target_label: "Import batch", target_route: "/app/workspaces/visual-workspace/documents/imports", read_at: null, dismissed_at: null, created_at: "2026-09-02T09:30:00Z",
};

const examples: GovernanceNotification[] = [
  baseNotification,
  { ...baseNotification, public_id: "notification-2", title: "Review due soon", message: "A document family is approaching its review date.", severity: "warning", target_route: "/app/workspaces/visual-workspace/documents?review_status=due_soon", created_at: "2026-09-02T08:15:00Z" },
  { ...baseNotification, public_id: "notification-3", title: "Document version approved", message: "A document version was approved.", severity: "info", read_at: "2026-09-02T08:00:00Z", target_route: "/app/workspaces/visual-workspace/documents/version-1", created_at: "2026-09-01T16:45:00Z" },
];

const actionable: ActionableWork = { awaiting_approval: 4, imports_processing: 2, imports_warning: 1, scheduled_changes: 3, review_due_soon: 5, review_overdue: 2 };

function openInbox(items: GovernanceNotification[], state: "ready" | "error" = "ready") {
  return <GovernanceInbox preview={{ items, unread: items.filter((item) => !item.read_at).length, initiallyOpen: true, state }} workspacePublicId="visual-workspace" />;
}

function checkpoint(state: Checkpoint) {
  if (state === "bell") return <Card className="max-w-lg"><CardHeader><CardTitle>Notification bell</CardTitle><CardDescription>The badge caps visually at 99+, while its accessible name preserves the exact count.</CardDescription></CardHeader><CardContent className="flex items-center gap-5"><GovernanceInbox preview={{ items: examples, unread: 147, state: "ready" }} workspacePublicId="visual-workspace" /><span className="text-sm text-foreground-muted">Inspect the bell beside the account controls in the real shell.</span></CardContent></Card>;
  if (state === "inbox") return openInbox(examples);
  if (state === "empty") return openInbox([]);
  if (state === "actionable-work") return <GovernanceActionableWork data={actionable} workspacePublicId="visual-workspace" />;
  if (state === "awaiting-approval") return <GovernanceActionableWork data={{ ...actionable, awaiting_approval: 12 }} workspacePublicId="visual-workspace" />;
  if (state === "review-reminders") return openInbox(examples.filter((item) => item.title.startsWith("Review")));
  if (state === "processing") return openInbox([{ ...baseNotification, title: "Import complete", message: "Your document import finished.", severity: "info" }, baseNotification]);
  if (state === "scheduled") return openInbox([{ ...baseNotification, title: "Scheduled change active", message: "A document version has attained authority.", severity: "info", target_route: "/app/workspaces/visual-workspace/documents/scheduled" }, { ...baseNotification, public_id: "blocked", title: "Scheduled authority blocked", message: "A scheduled document version cannot attain authority.", severity: "action_required", target_route: "/app/workspaces/visual-workspace/documents/scheduled" }]);
  if (state === "bulk-outcome") return openInbox([{ ...baseNotification, title: "Bulk operation completed with exceptions", message: "Review the items that could not be changed.", target_route: "/app/workspaces/visual-workspace/documents/bulk/operation-1" }]);
  if (state === "preferences") return <GovernanceNotificationPreferences preview={{ workspace: { email_delivery_enabled: true, default_email_enabled: true, can_manage: true }, personal: [{ category_group: "review_reminders", email_enabled: true }, { category_group: "governance.authority.blocked", email_enabled: false }] }} workspacePublicId="visual-workspace" />;
  if (state === "mobile") return openInbox(examples);
  if (state === "themes") return <div className="grid max-w-2xl gap-5"><div className="flex items-center justify-between"><p className="text-foreground-muted">Switch theme to verify the shared product tokens.</p><ThemeToggle /></div><Card><CardHeader><CardTitle>Governance notifications</CardTitle><CardDescription>Bell, panels, cards and preferences inherit the current theme without a notification-only palette.</CardDescription></CardHeader><CardContent><GovernanceInbox preview={{ items: examples, unread: 2, state: "ready" }} workspacePublicId="visual-workspace" /></CardContent></Card></div>;
  if (state === "keyboard") return openInbox(examples);
  if (state === "removed-target") return openInbox([{ ...baseNotification, title: "Document version approved", message: "This update remains in your history, but its destination is no longer available.", severity: "info", target_route: null }]);
  return openInbox(examples.slice(0, 2), "error");
}

export default async function GovernanceNotificationsReference({ searchParams }: Readonly<{ searchParams: Promise<{ state?: string }> }>) {
  if (process.env.NODE_ENV === "production") notFound();
  const requested = (await searchParams).state;
  const state: Checkpoint = checkpoints.includes(requested as Checkpoint) ? requested as Checkpoint : "bell";
  return <main className="mx-auto max-w-7xl p-6 sm:p-10"><p className="mb-5 rounded-lg bg-surface-muted p-3 text-sm text-foreground-muted">Development-only representative fixtures using the production governance components. They contain no workspace evidence and perform no mutations.</p><nav aria-label="Governance notification visual checkpoints" className="mb-6 flex flex-wrap gap-2">{checkpoints.map((candidate, index) => <Button asChild key={candidate} size="sm" variant={candidate === state ? "default" : "outline"}><Link href={`/design-system/governance-notifications?state=${candidate}`}>{index + 1}. {candidate.replaceAll("-", " ")}</Link></Button>)}</nav>{checkpoint(state)}<p className="mt-6 flex items-center gap-2 text-sm text-foreground-muted"><CheckCircle2 aria-hidden="true" className="size-4 text-success" />Review the numbered states in order. Mobile, theme and keyboard checkpoints use the same live components.</p></main>;
}
