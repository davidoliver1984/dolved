import { BellRing, ExternalLink } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { OperationsHeader, UnavailableOperations } from "@/app/app/platform/operations/_components";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatDateTime } from "@/lib/date";
import { platformOperations } from "@/lib/server-api";

const PATH = "/app/platform/operations/alerts";
export const metadata: Metadata = { title: "Alerts · Platform operations · Dolved", robots: { index: false, follow: false } };

export default async function PlatformAlertsPage() {
  const result = await platformOperations();
  if (result.status === "unauthorized") redirect(`/login?next=${PATH}`);
  if (result.status === "concealed") notFound();
  if (result.status === "unavailable") return <UnavailableOperations title="Alert state is unavailable." />;
  const alerts = result.data.alerts;
  return <section aria-label="Active platform alerts" className="grid gap-8"><OperationsHeader data={result.data} eyebrow="Actionable signals" title="Active alerts" description="Current platform alerts, their operational impact, and links to authoritative response guidance." /><div className="flex flex-wrap items-center justify-between gap-3"><div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-full bg-surface-raised text-brand"><BellRing aria-hidden="true" className="size-5" /></span><p className="font-semibold">{alerts.status === "available" ? `${alerts.values.length} active alert${alerts.values.length === 1 ? "" : "s"}` : "State unavailable"}</p></div>{result.data.alertmanager_url ? <Button asChild><Link href={result.data.alertmanager_url} rel="noreferrer" target="_blank"><ExternalLink aria-hidden="true" />Open alert console</Link></Button> : null}</div>{alerts.status === "unavailable" ? <Notice tone="warning">Alert state is unavailable. Do not infer that no alerts are active.</Notice> : alerts.values.length === 0 ? <Notice tone="success">No active operational alerts.</Notice> : <div className="grid gap-4 md:grid-cols-2">{alerts.values.map((alert, index) => <Card key={`${alert.name}-${index}`}><CardHeader><div className="flex flex-wrap items-center gap-2"><StatusBadge status={alert.severity === "urgent" ? "destructive" : "warning"}>{alert.severity}</StatusBadge><span className="text-xs text-foreground-muted">{alert.subsystem}</span></div><CardTitle>{alert.name}</CardTitle><CardDescription>{alert.impact}</CardDescription></CardHeader><CardContent className="grid gap-3">{alert.started_at ? <p className="text-sm text-foreground-muted">Active since <time dateTime={alert.started_at}>{formatDateTime(alert.started_at)}</time></p> : null}{alert.runbook_url ? <Button asChild className="w-fit" variant="outline"><Link href={alert.runbook_url} rel="noreferrer" target="_blank">Open runbook</Link></Button> : null}</CardContent></Card>)}</div>}</section>;
}
