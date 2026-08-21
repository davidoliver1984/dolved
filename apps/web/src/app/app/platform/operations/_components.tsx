import { Activity, BellRing, Gauge, SlidersHorizontal } from "lucide-react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import type { PlatformOperationsSnapshot } from "@/lib/api";
import { formatDateTime } from "@/lib/date";

export const METRIC_LABELS: Record<string, string> = {
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

export const SLO_LABELS: Record<string, string> = {
  authenticated_api_technical_availability: "Authenticated API technical availability",
  conversation_technical_success: "Conversation technical success",
};

export function metricValue(name: string, value: number | null): string {
  if (value === null) return "Unavailable";
  if (name.includes("seconds")) return `${value.toFixed(3)} s`;
  return value.toLocaleString("en-GB", { maximumFractionDigits: 3 });
}

function healthTone(status: PlatformOperationsSnapshot["health_status"]): StatusTone {
  if (status === "healthy") return "success";
  if (status === "degraded") return "warning";
  return "unavailable";
}

export function OperationsHeader({ data, eyebrow, title, description }: Readonly<{
  data: PlatformOperationsSnapshot;
  eyebrow: string;
  title: string;
  description: string;
}>) {
  return (
    <header className="grid gap-3 border-b border-border pb-7">
      <div className="flex flex-wrap items-center gap-3">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">{eyebrow}</p>
        <StatusBadge status={healthTone(data.health_status)}>{data.health_status}</StatusBadge>
      </div>
      <h1 className="text-4xl font-semibold tracking-tight sm:text-5xl">{title}</h1>
      <p className="max-w-3xl text-base text-foreground-muted">{description}</p>
      <p className="text-sm text-foreground-faint">
        Operational data is {data.status}, as of <time dateTime={data.as_of}>{formatDateTime(data.as_of)}</time>. Values are global and contain no tenant identifiers.
      </p>
    </header>
  );
}

export function UnavailableOperations({ title }: Readonly<{ title: string }>) {
  return (
    <section aria-labelledby="operations-unavailable" className="grid gap-5">
      <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Platform operations</p>
      <h1 className="text-4xl font-semibold tracking-tight" id="operations-unavailable">{title}</h1>
      <Notice tone="warning">The operational reader could not be reached. Ordinary Dolved use is unaffected.</Notice>
    </section>
  );
}

export function SloSummary({ data }: Readonly<{ data: PlatformOperationsSnapshot }>) {
  return (
    <section aria-labelledby="slo-heading" className="grid gap-4">
      <div className="flex items-center gap-3">
        <span className="grid size-10 place-items-center rounded-full bg-surface-raised text-brand"><Gauge aria-hidden="true" className="size-5" /></span>
        <div><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Provisional objectives</p><h2 className="text-2xl font-semibold" id="slo-heading">Service-level status</h2></div>
      </div>
      {data.slos.length === 0 ? <Notice tone="info">SLO evidence is unavailable. No compliance conclusion can be drawn.</Notice> : (
        <div className="grid gap-4 md:grid-cols-2">
          {data.slos.map((slo) => (
            <Card key={slo.id}>
              <CardHeader><CardDescription className="font-bold uppercase tracking-[0.1em]">{SLO_LABELS[slo.id] ?? slo.id}</CardDescription><CardTitle className="text-2xl">{slo.value === null ? "No representative data" : `${(slo.value * 100).toFixed(2)}%`}</CardTitle></CardHeader>
              <CardContent className="grid gap-1 text-sm"><p className="text-foreground-muted">Objective {(slo.objective * 100).toFixed(1)}% · {slo.window_days} days</p><p className="font-medium">{slo.compliant === null ? "Not assessed" : slo.compliant ? "Within objective" : "Outside objective"}</p></CardContent>
            </Card>
          ))}
        </div>
      )}
    </section>
  );
}

export function OverviewDestinations({ data }: Readonly<{ data: PlatformOperationsSnapshot }>) {
  const urgent = data.alerts.values.filter((alert) => alert.severity === "urgent").length;
  const policy = data.operational_policy;
  const cards = [
    { href: "/app/platform/operations/alerts", icon: BellRing, title: "Active alerts", value: data.alerts.status === "unavailable" ? "Unavailable" : `${data.alerts.values.length} active`, detail: data.alerts.status === "unavailable" ? "Alert state cannot currently be assessed." : urgent ? `${urgent} urgent signal${urgent === 1 ? "" : "s"}.` : "No urgent signals." },
    { href: "/app/platform/operations/telemetry", icon: Activity, title: "Global telemetry", value: `${Object.keys(data.metrics).length} signals`, detail: "Curated, tenant-free platform measurements." },
    { href: "/app/platform/operations/policy", icon: SlidersHorizontal, title: "Operational policy", value: policy ? `${policy.active_settings} of ${policy.total_settings} active` : "Not recorded", detail: policy ? `Desired policy version ${policy.version}.` : "No desired policy exists for this environment." },
  ];
  return <section aria-labelledby="areas-heading" className="grid gap-4"><h2 className="text-2xl font-semibold" id="areas-heading">Operational areas</h2><div className="grid gap-4 lg:grid-cols-3">{cards.map(({ href, icon: Icon, title, value, detail }) => <Card key={href}><CardHeader><span className="grid size-10 place-items-center rounded-full bg-surface-raised text-brand"><Icon aria-hidden="true" className="size-5" /></span><CardTitle className="mt-3">{title}</CardTitle><CardDescription>{detail}</CardDescription></CardHeader><CardContent className="grid gap-4"><p className="text-xl font-semibold">{value}</p><Button asChild variant="outline"><Link href={href}>View {title.toLowerCase()}</Link></Button></CardContent></Card>)}</div></section>;
}
