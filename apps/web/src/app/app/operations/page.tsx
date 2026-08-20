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

const SLO_LABELS: Record<string, string> = {
  authenticated_api_technical_availability: "Authenticated API technical availability",
  conversation_technical_success: "Conversation technical success",
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

      <section aria-labelledby="slo-heading">
        <div className="operations-section-heading">
          <div>
            <p className="eyebrow">Provisional objectives</p>
            <h2 id="slo-heading">Service-level status</h2>
          </div>
          <p>Rolling 28-day technical signals. These objectives are provisional and unmeasured until representative traffic exists.</p>
        </div>
        {(result.data.slos?.length ?? 0) === 0 ? (
          <p className="operations-notice">SLO evidence is unavailable. No compliance conclusion can be drawn.</p>
        ) : <div className="operations-grid">
          {result.data.slos.map((slo) => (
            <article className="operations-card" key={slo.id}>
              <p className="operations-card-label">{SLO_LABELS[slo.id] ?? slo.id}</p>
              <strong>{slo.value === null ? "No representative data" : `${(slo.value * 100).toFixed(2)}%`}</strong>
              <small>Objective {(slo.objective * 100).toFixed(1)}% · {slo.window_days} days · {slo.compliant === null ? "not assessed" : slo.compliant ? "within objective" : "outside objective"}</small>
            </article>
          ))}
        </div>}
      </section>

      <section aria-labelledby="alert-heading">
        <div className="operations-section-heading">
          <div>
            <p className="eyebrow">Actionable signals</p>
            <h2 id="alert-heading">Active alerts</h2>
          </div>
          {result.data.alertmanager_url ? <Link href={result.data.alertmanager_url} target="_blank" rel="noreferrer" className="secondary-action">Open alert console</Link> : null}
        </div>
        {!result.data.alerts || result.data.alerts.status === "unavailable" ? (
          <p className="operations-notice">Alert state is unavailable. Do not infer that no alerts are active.</p>
        ) : (result.data.alerts?.values.length ?? 0) === 0 ? (
          <p className="operations-notice">No active operational alerts.</p>
        ) : (
          <div className="operations-grid">
            {result.data.alerts.values.map((alert, index) => (
              <article className="operations-card" key={`${alert.name}-${index}`}>
                <p className="operations-card-label">{alert.severity} · {alert.subsystem}</p>
                <strong>{alert.name}</strong>
                <p>{alert.impact}</p>
                {alert.started_at ? <small>Active since <time dateTime={alert.started_at}>{new Date(alert.started_at).toLocaleString()}</time></small> : null}
                {alert.runbook_url ? <Link href={alert.runbook_url} target="_blank" rel="noreferrer">Open runbook</Link> : null}
              </article>
            ))}
          </div>
        )}
      </section>

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
