"use client";

import { Mail, ShieldCheck } from "lucide-react";
import { useEffect, useId, useMemo, useState } from "react";
import {
  governanceNotificationPreferences,
  type GovernanceNotificationPreferences as Preferences,
  updatePersonalGovernanceNotificationPreference,
  updateWorkspaceGovernanceNotificationPreferences,
} from "@/lib/api";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Notice } from "@/components/ui/notice";
import { Skeleton } from "@/components/ui/skeleton";

const categories = [
  { title: "Review reminders", description: "Upcoming and overdue document-family reviews.", groups: ["review_reminders"] },
  { title: "Import outcomes", description: "Completed imports and batches that finish with exceptions.", groups: ["import.batch.completed", "import.batch.completed_with_exceptions"] },
  { title: "Library and promotion", description: "Approvals, promotions and applicability successor outcomes.", groups: ["governance.version.approved", "promotion.completed", "promotion.failed", "applicability.successor.completed", "applicability.successor.failed"] },
  { title: "Governance attention", description: "Ownership gaps and blocked scheduled authority.", groups: ["governance.ownership.reassignment_required", "governance.authority.blocked"] },
  { title: "Bulk operations", description: "Completed, exceptional and pre-execution bulk-operation outcomes.", groups: ["bulk.operation.completed", "bulk.operation.completed_with_exceptions", "bulk.operation.failed_before_execution"] },
  { title: "Deletion attention", description: "Document deletion operations that are stuck or failed.", groups: ["deletion.operation.stuck_or_failed"] },
] as const;

export function GovernanceNotificationPreferences({ workspacePublicId, preview }: Readonly<{ workspacePublicId: string; preview?: Preferences }>) {
  const [data, setData] = useState<Preferences | null>(preview ?? null);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState<string | null>(null);

  useEffect(() => {
    if (preview) return;
    void governanceNotificationPreferences(workspacePublicId).then(setData).catch(() => setError("Notification preferences could not be loaded."));
  }, [preview, workspacePublicId]);

  const personal = useMemo(() => new Map(data?.personal.map((item) => [item.category_group, item.email_enabled])), [data]);
  if (!data && !error) return <div className="grid gap-4"><Skeleton className="h-28" /><Skeleton className="h-64" /></div>;
  if (!data) return <Notice tone="destructive">{error}</Notice>;

  const setWorkspace = async (key: "email_delivery_enabled" | "default_email_enabled", checked: boolean) => {
    const next = { ...data.workspace, [key]: checked };
    setSaving(key);
    setError(null);
    try {
      if (!preview) await updateWorkspaceGovernanceNotificationPreferences(workspacePublicId, next);
      setData({ ...data, workspace: next });
    } catch { setError("Workspace email settings could not be saved."); }
    finally { setSaving(null); }
  };

  const setCategory = async (groups: readonly string[], checked: boolean) => {
    const key = groups.join("|");
    setSaving(key);
    setError(null);
    try {
      if (!preview) await Promise.all(groups.map((group) => updatePersonalGovernanceNotificationPreference(workspacePublicId, group, checked)));
      const next = new Map(personal);
      groups.forEach((group) => next.set(group, checked));
      setData({ ...data, personal: [...next].map(([category_group, email_enabled]) => ({ category_group, email_enabled })) });
    } catch { setError("Your email preference could not be saved."); }
    finally { setSaving(null); }
  };

  return <div className="grid gap-6">
    <header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Notifications</p><h1 className="mt-2 text-3xl font-semibold">Choose what reaches your inbox</h1><p className="mt-2 max-w-2xl text-foreground-muted">In-product notifications remain available in Dolved. These settings control email delivery only.</p></header>
    {error ? <Notice tone="destructive">{error}</Notice> : null}
    {data.workspace.can_manage ? <Card><CardHeader><div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-full bg-brand/10 text-brand"><ShieldCheck aria-hidden="true" className="size-5" /></span><div><CardTitle>Workspace email policy</CardTitle><CardDescription className="mt-1">Applies to every member of this workspace. Personal opt-outs are respected.</CardDescription></div></div></CardHeader><CardContent className="grid gap-4"><PreferenceRow checked={data.workspace.email_delivery_enabled} description="Pause or resume all governance email delivery for this workspace." disabled={saving !== null} label="Governance emails enabled" onCheckedChange={(checked) => void setWorkspace("email_delivery_enabled", checked)} /><PreferenceRow checked={data.workspace.default_email_enabled} description="Used when a member has not made a personal choice for a category." disabled={saving !== null} label="Email new categories by default" onCheckedChange={(checked) => void setWorkspace("default_email_enabled", checked)} /></CardContent></Card> : null}
    <Card><CardHeader><div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-full bg-brand/10 text-brand"><Mail aria-hidden="true" className="size-5" /></span><div><CardTitle>Your email categories</CardTitle><CardDescription className="mt-1">Choose the governance updates that should also arrive by email.</CardDescription></div></div></CardHeader><CardContent className="divide-y divide-border">{categories.map((category) => { const key = category.groups.join("|"); const checked = category.groups.every((group) => personal.get(group) ?? data.workspace.default_email_enabled); return <PreferenceRow checked={checked} description={category.description} disabled={saving !== null} key={key} label={category.title} onCheckedChange={(value) => void setCategory(category.groups, value)} />; })}</CardContent></Card>
    <Notice tone="info">Email is a reminder, not an access grant. Every link still requires an authorised Dolved sign-in.</Notice>
  </div>;
}

function PreferenceRow({ checked, description, disabled, label, onCheckedChange }: Readonly<{ checked: boolean; description: string; disabled: boolean; label: string; onCheckedChange: (checked: boolean) => void }>) {
  const id = useId();
  return <div className="flex items-start gap-3 py-4 first:pt-0 last:pb-0"><Checkbox aria-describedby={`${id}-description`} aria-labelledby={`${id}-label`} checked={checked} disabled={disabled} onCheckedChange={(value) => onCheckedChange(value === true)} /><span><span className="block font-semibold" id={`${id}-label`}>{label}</span><span className="mt-1 block text-sm text-foreground-muted" id={`${id}-description`}>{description}</span></span></div>;
}
