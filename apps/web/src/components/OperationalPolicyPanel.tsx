"use client";

import { useState } from "react";
import type { FormEvent } from "react";
import { createOperationalPolicy, firstError } from "@/lib/api";
import type { OperationalPolicy } from "@/lib/api";

const DEFAULTS = {
  trace_sampling_percentage: 10,
  slow_operation_threshold_seconds: 5,
  log_retention_days: 30,
  trace_retention_days: 14,
  metric_retention_days: 90,
};

const INPUT_LIMITS: Record<keyof typeof DEFAULTS, { min: number; max: number; step: number }> = {
  trace_sampling_percentage: { min: 0, max: 100, step: 1 },
  slow_operation_threshold_seconds: { min: 0.1, max: 3600, step: 0.1 },
  log_retention_days: { min: 1, max: 3650, step: 1 },
  trace_retention_days: { min: 1, max: 3650, step: 1 },
  metric_retention_days: { min: 1, max: 3650, step: 1 },
};

export function OperationalPolicyPanel({ policy }: { policy: OperationalPolicy | null }) {
  const [message, setMessage] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const values = Object.fromEntries(
      Object.keys(DEFAULTS).map((key) => [key, Number(form.get(key))]),
    );
    try {
      await createOperationalPolicy(values);
      setMessage("Desired policy saved. It remains pending until authenticated target reconciliation completes.");
    } catch (error) {
      setMessage(firstError(error));
    }
  }

  return (
    <section className="operations-policy" aria-labelledby="policy-heading">
      <div>
        <p className="eyebrow">Desired operational policy</p>
        <h2 id="policy-heading">Infrastructure reconciliation</h2>
        {policy ? (
          <>
            <p>Version {policy.version} · {policy.active_settings} of {policy.total_settings} settings active.</p>
            <ul>
              {policy.settings.map((setting) => (
                <li key={setting.setting_key}>
                  <strong>{setting.setting_key}</strong>: {String(setting.desired_value)} · {setting.status}
                  <small>{setting.targets.map((target) => `${target.target}: ${target.status}`).join(" · ")}</small>
                </li>
              ))}
            </ul>
          </>
        ) : <p>No desired policy has been recorded for this environment.</p>}
      </div>
      <form onSubmit={submit}>
        {Object.entries(DEFAULTS).map(([key, value]) => (
          <label key={key}>{key.replaceAll("_", " ")}
            <input
              name={key}
              type="number"
              min={INPUT_LIMITS[key as keyof typeof DEFAULTS].min}
              max={INPUT_LIMITS[key as keyof typeof DEFAULTS].max}
              step={INPUT_LIMITS[key as keyof typeof DEFAULTS].step}
              defaultValue={value}
              required
            />
          </label>
        ))}
        <button type="submit" className="secondary-action">Save desired policy</button>
        {message ? <p role="status">{message}</p> : null}
      </form>
    </section>
  );
}
