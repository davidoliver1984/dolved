import type { Metadata } from "next";
import Link from "next/link";
import { redirect } from "next/navigation";
import { platformOperations } from "@/lib/server-api";
import { OperationalPolicyPanel } from "@/components/OperationalPolicyPanel";

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

function valueLabel(name: string, value: number | null): string {
  if (value === null) return "Unavailable";
  if (name.includes("seconds")) return `${value.toFixed(3)} s`;
  return value.toLocaleString(undefined, { maximumFractionDigits: 3 });
}

export default async function PlatformOperationsPage() {
  const result = await platformOperations();
  if (result.status === "unauthorized") redirect("/login");
  if (result.status === "forbidden") redirect("/app");

  if (result.status === "unavailable") {
    return (
      <section className="operations-page" aria-labelledby="operations-heading">
        <p className="eyebrow">Platform operations</p>
        <h1 id="operations-heading">Health data is unavailable.</h1>
        <p>The operational reader could not be reached. Ordinary Dolved use is unaffected.</p>
      </section>
    );
  }

  return (
    <section className="operations-page" aria-labelledby="operations-heading">
      <header className="operations-header">
        <div>
          <p className="eyebrow">Platform operations</p>
          <h1 id="operations-heading">Platform health is {result.data.health_status}.</h1>
          <p>Operational data is {result.data.status}, as of <time dateTime={result.data.as_of}>{new Date(result.data.as_of).toLocaleString()}</time>. Values are global and contain no tenant identifiers.</p>
        </div>
        <Link href={result.data.grafana_url} target="_blank" rel="noreferrer" className="secondary-action">Open specialist console</Link>
      </header>

      <div className="operations-grid">
        {Object.entries(result.data.metrics).map(([name, metric]) => (
          <article className="operations-card" key={name}>
            <p className="operations-card-label">{LABELS[name] ?? name}</p>
            {metric.status === "unavailable" ? (
              <strong className="unavailable">Unavailable</strong>
            ) : metric.values.length === 0 ? (
              <strong>No observations</strong>
            ) : (
              <ul>
                {metric.values.map((entry, index) => (
                  <li key={`${name}-${index}`}>
                    <strong>{valueLabel(name, entry.value)}</strong>
                    {Object.keys(entry.labels).length > 0 ? <small>{Object.values(entry.labels).join(" · ")}</small> : null}
                  </li>
                ))}
              </ul>
            )}
          </article>
        ))}
      </div>
      <OperationalPolicyPanel policy={result.data.operational_policy ?? null} />
    </section>
  );
}
