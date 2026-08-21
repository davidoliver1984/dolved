import { ExternalLink, LineChart } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { METRIC_LABELS, metricValue, OperationsHeader, UnavailableOperations } from "@/app/app/platform/operations/_components";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader } from "@/components/ui/card";
import { platformOperations } from "@/lib/server-api";

const PATH = "/app/platform/operations/telemetry";
export const metadata: Metadata = { title: "Telemetry · Platform operations · Dolved", robots: { index: false, follow: false } };

export default async function PlatformTelemetryPage() {
  const result = await platformOperations();
  if (result.status === "unauthorized") redirect(`/login?next=${PATH}`);
  if (result.status === "concealed") notFound();
  if (result.status === "unavailable") return <UnavailableOperations title="Telemetry is unavailable." />;
  return <section aria-label="Global platform telemetry" className="grid gap-8"><OperationsHeader data={result.data} eyebrow="Global telemetry" title="Operational metrics" description="Curated, tenant-free signals for platform throughput, latency, dependencies, queues and stuck work." /><div className="flex flex-wrap items-center justify-between gap-3"><div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-full bg-surface-raised text-brand"><LineChart aria-hidden="true" className="size-5" /></span><p className="font-semibold">{Object.keys(result.data.metrics).length} monitored signals</p></div>{result.data.grafana_url ? <Button asChild><Link href={result.data.grafana_url} rel="noreferrer" target="_blank"><ExternalLink aria-hidden="true" />Open specialist console</Link></Button> : null}</div><div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{Object.entries(result.data.metrics).map(([name, metric]) => <Card key={name}><CardHeader><CardDescription className="font-bold uppercase tracking-[0.1em]">{METRIC_LABELS[name] ?? name}</CardDescription></CardHeader><CardContent>{metric.status === "unavailable" ? <p className="text-xl font-semibold text-foreground-muted">Unavailable</p> : metric.values.length === 0 ? <p className="text-xl font-semibold">No observations</p> : <ul className="grid gap-3">{metric.values.map((entry, index) => <li className="grid gap-1" key={`${name}-${index}`}><strong className="text-xl">{metricValue(name, entry.value)}</strong>{Object.keys(entry.labels).length ? <span className="text-xs text-foreground-muted">{Object.values(entry.labels).join(" · ")}</span> : null}</li>)}</ul>}</CardContent></Card>)}</div></section>;
}
