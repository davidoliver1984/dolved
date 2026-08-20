"use client";

import { useState } from "react";
import { firstError, workspaceUsage, type WorkspaceUsageSnapshot } from "@/lib/api";

type Props = { initialSnapshot: WorkspaceUsageSnapshot; workspaceId: string };

const integer = new Intl.NumberFormat("en-GB");
const usd = new Intl.NumberFormat("en-GB", { style: "currency", currency: "USD", minimumFractionDigits: 4, maximumFractionDigits: 8 });

function value(number: number | string | null): number | null {
  if (number === null) return null;
  const parsed = Number(number);
  return Number.isFinite(parsed) ? parsed : null;
}

function costLabel(basis: WorkspaceUsageSnapshot["historical"]["usage"][number]["cost_basis"], cost: number | null): string {
  if (basis === "unavailable" || cost === null) return "Cost unavailable";
  if (basis === "zero_cost_local") return `${usd.format(cost)} local execution`;
  if (basis === "provider_reported") return `${usd.format(cost)} provider reported`;
  return `${usd.format(cost)} estimated`;
}

function tokenLabel(label: string, count: number | string | null): string {
  const parsed = value(count);
  return parsed === null ? `${label} unavailable` : `${integer.format(parsed)} ${label.toLowerCase()}`;
}

export function WorkspaceUsage({ initialSnapshot, workspaceId }: Props) {
  const [snapshot, setSnapshot] = useState(initialSnapshot);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const selectRange = async (range: "7d" | "30d" | "month") => {
    setBusy(true);
    setError(null);
    try {
      setSnapshot((await workspaceUsage(workspaceId, range)).data);
    } catch (caught) {
      setError(firstError(caught));
    } finally {
      setBusy(false);
    }
  };

  return (
    <details className="document-tools">
      <summary>Workspace usage</summary>
      <section aria-labelledby="workspace-usage-heading">
        <h2 id="workspace-usage-heading">Usage visibility</h2>
        <p className="scope-note">Current gauges and historical activity are shown separately. Updated {new Date(snapshot.as_of).toLocaleString("en-GB")}.</p>
        <div aria-label="Usage interval">
          {(["7d", "30d", "month"] as const).map((range) => (
            <button className="secondary-button compact" disabled={busy || snapshot.range.key === range} key={range} onClick={() => void selectRange(range)} type="button">{range === "month" ? "Current month" : range}</button>
          ))}
        </div>
        {error ? <p className="form-error" role="alert">{error}</p> : null}
        <h3>Current gauges</h3>
        <dl className="usage-grid">
          <div><dt>Active documents</dt><dd>{integer.format(snapshot.gauges.active_documents)}</dd></div>
          <div><dt>Logical source bytes</dt><dd>{integer.format(snapshot.gauges.logical_source_bytes)}</dd></div>
          <div><dt>Indexed chunks</dt><dd>{integer.format(snapshot.gauges.indexed_chunks)}</dd></div>
        </dl>
        <p className="scope-note">{snapshot.labels.logical_source_bytes}</p>
        <h3>Historical interval</h3>
        <p>{new Date(snapshot.range.start).toLocaleString("en-GB")} to {new Date(snapshot.range.end).toLocaleString("en-GB")} · {snapshot.range.semantics}</p>
        <p><strong>Ingestion failures:</strong> {integer.format(snapshot.historical.ingestion_failures)}</p>
        <ul className="administration-list">
          {snapshot.historical.activity.map((item) => <li key={`${item.event_kind}-${item.outcome ?? "none"}`}><span>{item.event_kind.replaceAll("_", " ")}{item.outcome ? ` · ${item.outcome.replaceAll("_", " ")}` : ""}</span><strong>{integer.format(Number(item.aggregate_count))}</strong></li>)}
        </ul>
        <h3>Provider and local usage</h3>
        {snapshot.historical.usage.length === 0 ? <p>No usage observations are available for this interval.</p> : (
          <ul className="administration-list">
            {snapshot.historical.usage.map((item) => {
              const cost = value(item.cost_usd);
              return <li key={`${item.operation_kind}-${item.provider}-${item.model}-${item.cost_basis}-${item.pricing_snapshot ?? "none"}`}>
                <div><strong>{item.operation_kind.replaceAll("_", " ")}</strong><br /><small>{item.provider} · {item.model} · {item.cost_basis.replaceAll("_", " ")}</small></div>
                <div>
                  <span>{integer.format(Number(item.request_count))} requests · {integer.format(Number(item.retry_count))} retries</span><br />
                  <span>{tokenLabel("Input tokens", item.input_tokens)}</span><br />
                  <span>{tokenLabel("Cached input tokens", item.cached_input_tokens)}</span><br />
                  <span>{tokenLabel("Output tokens", item.output_tokens)}</span><br />
                  <span>{value(item.latency_ms) === null ? "Latency unavailable" : `${integer.format(Math.round(value(item.latency_ms) ?? 0))} ms latency`}</span><br />
                  <span>{costLabel(item.cost_basis, cost)}</span>
                </div>
              </li>;
            })}
          </ul>
        )}
        <p className="scope-note">{snapshot.labels.cost}</p>
      </section>
    </details>
  );
}
