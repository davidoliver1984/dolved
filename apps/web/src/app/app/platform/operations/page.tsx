import { Activity, BellRing, ExternalLink, Gauge } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { redirect } from "next/navigation";
import { OperationalPolicyPanel } from "@/components/OperationalPolicyPanel";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import { platformOperations } from "@/lib/server-api";
import { formatDateTime } from "@/lib/date";

export const metadata: Metadata = {
  title: "Platform health — Dolved",
  robots: { index: false, follow: false },
};

const LABELS: Record<string, string> = {
  api_request_rate: "API request rate",
  api_error_rate: "API technical error rate",
  api_latency_p95_seconds: "API p95 latency",
  operation_rate: "Application operation rate",
  operation_latency_p95_seconds: "Application p95 latency",
  dependency_availability: "Dependency availability",
  queue_depth: "Queue and outbox depth",
  queue_oldest_message_age_seconds: "Oldest queued item",
  stuck_operations: "Stuck operations",
};

const SLO_LABELS: Record<string, string> = {
  authenticated_api_technical_availability: "Authenticated API technical availability",
  conversation_technical_success: "Conversation technical success",
};

function valueLabel(name: string, value: number | null): string {
  if (value === null) return "Unavailable";
  if (name.includes("seconds")) return `${value.toFixed(3)} s`;
  return value.toLocaleString("en-GB", { maximumFractionDigits: 3 });
}

function healthTone(status: "healthy" | "degraded" | "unknown"): StatusTone {
  if (status === "healthy") return "success";
  if (status === "degraded") return "warning";
  return "unavailable";
}

export default async function PlatformOperationsPage() {
  const result = await platformOperations();
  if (result.status === "unauthorized") redirect("/login?next=/app/platform/operations");
  if (result.status === "forbidden") redirect("/app");

  if (result.status === "unavailable") {
    return (
      <section aria-labelledby="operations-heading" className="grid gap-5">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Platform operations</p>
        <h1 className="text-4xl font-semibold tracking-tight" id="operations-heading">Health data is unavailable.</h1>
        <Notice tone="warning">The operational reader could not be reached. Ordinary Dolved use is unaffected.</Notice>
      </section>
    );
  }

  return (
    <section aria-labelledby="operations-heading" className="grid gap-8">
      <header className="grid gap-5 border-b border-border pb-8 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
        <div className="grid gap-3">
          <div className="flex flex-wrap items-center gap-3">
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Platform operations</p>
            <StatusBadge status={healthTone(result.data.health_status)}>{result.data.health_status}</StatusBadge>
          </div>
          <h1 className="text-4xl font-semibold tracking-tight sm:text-5xl" id="operations-heading">Platform health is {result.data.health_status}.</h1>
          <p className="max-w-3xl text-base text-foreground-muted">
            Rolling 28-day technical signals. These objectives are provisional and unmeasured until representative traffic exists.
          </p>
          <p className="text-sm text-foreground-faint">
            Operational data is {result.data.status}, as of <time dateTime={result.data.as_of}>{formatDateTime(result.data.as_of)}</time>. Values are global and contain no tenant identifiers.
          </p>
        </div>
        <Button asChild><Link href={result.data.grafana_url} rel="noreferrer" target="_blank"><ExternalLink aria-hidden="true" />Open specialist console</Link></Button>
      </header>

      <section aria-labelledby="slo-heading" className="grid gap-4">
        <div className="flex items-center gap-3">
          <span className="grid size-10 place-items-center rounded-full bg-surface-raised text-brand"><Gauge aria-hidden="true" className="size-5" /></span>
          <div><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Provisional objectives</p><h2 className="text-2xl font-semibold" id="slo-heading">Service-level status</h2></div>
        </div>
        {(result.data.slos?.length ?? 0) === 0 ? (
          <Notice tone="info">SLO evidence is unavailable. No compliance conclusion can be drawn.</Notice>
        ) : <div className="grid gap-4 md:grid-cols-2">
          {result.data.slos.map((slo) => (
            <Card key={slo.id}>
              <CardHeader>
                <CardDescription className="font-bold uppercase tracking-[0.1em]">{SLO_LABELS[slo.id] ?? slo.id}</CardDescription>
                <CardTitle className="text-2xl">{slo.value === null ? "No representative data" : `${(slo.value * 100).toFixed(2)}%`}</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-foreground-muted">Objective {(slo.objective * 100).toFixed(1)}% · {slo.window_days} days</p>
                <p className="mt-1 text-sm font-medium">{slo.compliant === null ? "not assessed" : slo.compliant ? "within objective" : "outside objective"}</p>
              </CardContent>
            </Card>
          ))}
        </div>}
      </section>

      <section aria-labelledby="alert-heading" className="grid gap-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div className="flex items-center gap-3">
            <span className="grid size-10 place-items-center rounded-full bg-surface-raised text-brand"><BellRing aria-hidden="true" className="size-5" /></span>
            <div><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Actionable signals</p><h2 className="text-2xl font-semibold" id="alert-heading">Active alerts</h2></div>
          </div>
          {result.data.alertmanager_url ? <Button asChild><Link href={result.data.alertmanager_url} rel="noreferrer" target="_blank"><ExternalLink aria-hidden="true" />Open alert console</Link></Button> : null}
        </div>
        {!result.data.alerts || result.data.alerts.status === "unavailable" ? (
          <Notice tone="warning">Alert state is unavailable. Do not infer that no alerts are active.</Notice>
        ) : (result.data.alerts?.values.length ?? 0) === 0 ? (
          <Notice tone="success">No active operational alerts.</Notice>
        ) : (
          <div className="grid gap-4 md:grid-cols-2">
            {result.data.alerts.values.map((alert, index) => (
              <Card key={`${alert.name}-${index}`}>
                <CardHeader><div className="flex flex-wrap items-center gap-2"><StatusBadge status={alert.severity === "urgent" ? "destructive" : "warning"}>{alert.severity}</StatusBadge><span className="text-xs text-foreground-muted">{alert.subsystem}</span></div><CardTitle>{alert.name}</CardTitle><CardDescription>{alert.impact}</CardDescription></CardHeader>
                <CardContent className="grid gap-3">{alert.started_at ? <p className="text-sm text-foreground-muted">Active since <time dateTime={alert.started_at}>{formatDateTime(alert.started_at)}</time></p> : null}{alert.runbook_url ? <Button asChild className="w-fit" variant="outline"><Link href={alert.runbook_url} rel="noreferrer" target="_blank">Open runbook</Link></Button> : null}</CardContent>
              </Card>
            ))}
          </div>
        )}
      </section>

      <section aria-labelledby="metric-heading" className="grid gap-4">
        <div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-full bg-surface-raised text-brand"><Activity aria-hidden="true" className="size-5" /></span><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Global telemetry</p><h2 className="text-2xl font-semibold" id="metric-heading">Operational metrics</h2></div></div>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {Object.entries(result.data.metrics).map(([name, metric]) => (
            <Card key={name}>
              <CardHeader><CardDescription className="font-bold uppercase tracking-[0.1em]">{LABELS[name] ?? name}</CardDescription></CardHeader>
              <CardContent>
                {metric.status === "unavailable" ? (
                  <p className="text-xl font-semibold text-foreground-muted">Unavailable</p>
                ) : metric.values.length === 0 ? (
                  <p className="text-xl font-semibold">No observations</p>
                ) : (
                  <ul className="grid gap-3">
                    {metric.values.map((entry, index) => (
                      <li className="grid gap-1" key={`${name}-${index}`}><strong className="text-xl">{valueLabel(name, entry.value)}</strong>{Object.keys(entry.labels).length > 0 ? <span className="text-xs text-foreground-muted">{Object.values(entry.labels).join(" · ")}</span> : null}</li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      </section>

      <OperationalPolicyPanel policy={result.data.operational_policy ?? null} />
    </section>
  );
}
