"use client";

import { Save } from "lucide-react";
import { useRouter } from "next/navigation";
import { type FormEvent, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import { createOperationalPolicy, firstError, type OperationalPolicy } from "@/lib/api";

const DEFAULTS = {
  trace_sampling_percentage: 10,
  slow_operation_threshold_seconds: 5,
  log_retention_days: 30,
  trace_retention_days: 14,
  metric_retention_days: 90,
};

type SettingKey = keyof typeof DEFAULTS;

const SETTINGS: Record<SettingKey, {
  description: string;
  label: string;
  max: number;
  min: number;
  step: number;
  unit: string;
}> = {
  trace_sampling_percentage: {
    description: "Percentage of eligible traces retained by the Collector sampler.",
    label: "Trace sampling",
    min: 0,
    max: 100,
    step: 1,
    unit: "%",
  },
  slow_operation_threshold_seconds: {
    description: "Duration after which an application operation is classified as slow.",
    label: "Slow-operation threshold",
    min: 0.1,
    max: 3600,
    step: 0.1,
    unit: "seconds",
  },
  log_retention_days: {
    description: "Requested retention period for privacy-safe operational logs.",
    label: "Log retention",
    min: 1,
    max: 3650,
    step: 1,
    unit: "days",
  },
  trace_retention_days: {
    description: "Requested retention period for distributed trace data.",
    label: "Trace retention",
    min: 1,
    max: 3650,
    step: 1,
    unit: "days",
  },
  metric_retention_days: {
    description: "Requested retention period for aggregate operational metrics.",
    label: "Metric retention",
    min: 1,
    max: 3650,
    step: 1,
    unit: "days",
  },
};

function tone(status: "ACTIVE" | "PENDING" | "FAILED"): StatusTone {
  if (status === "ACTIVE") return "success";
  if (status === "FAILED") return "destructive";
  return "pending";
}

export function OperationalPolicyPanel({ policy }: Readonly<{ policy: OperationalPolicy | null }>) {
  const router = useRouter();
  const [message, setMessage] = useState<{ text: string; tone: "success" | "destructive" } | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const values = Object.fromEntries(
      Object.keys(DEFAULTS).map((key) => [key, Number(form.get(key))]),
    );
    try {
      const result = await createOperationalPolicy(values);
      if (result.status === "concealed") {
        setMessage(null);
        router.refresh();
        return;
      }
      setMessage({
        text: "Desired policy saved. It remains pending until authenticated target reconciliation completes.",
        tone: "success",
      });
    } catch (error) {
      setMessage({ text: firstError(error), tone: "destructive" });
    }
  }

  return (
    <section aria-labelledby="policy-heading" className="grid gap-5">
      <header className="grid gap-2">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Desired operational policy</p>
        <h2 className="text-2xl font-semibold" id="policy-heading">Infrastructure reconciliation</h2>
        <p className="max-w-3xl text-sm text-foreground-muted">
          Desired values are recorded centrally, then applied independently by authenticated infrastructure targets.
        </p>
        {policy ? (
          <p className="text-sm font-medium">
            Version {policy.version} · {policy.active_settings} of {policy.total_settings} settings active.
          </p>
        ) : <Notice tone="info">No desired policy has been recorded for this environment.</Notice>}
      </header>

      <form className="grid gap-5" onSubmit={submit}>
        <div className="grid gap-4 lg:grid-cols-2">
          {(Object.keys(DEFAULTS) as SettingKey[]).map((key) => {
            const definition = SETTINGS[key];
            const current = policy?.settings.find((setting) => setting.setting_key === key);
            const value = current?.desired_value ?? DEFAULTS[key];
            return (
              <Card className="min-w-0" key={key}>
                <CardHeader className="gap-3">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <CardTitle>{definition.label}</CardTitle>
                      <CardDescription className="mt-1">{definition.description}</CardDescription>
                    </div>
                    {current ? <StatusBadge status={tone(current.status)}>{current.status.toLowerCase()}</StatusBadge> : <StatusBadge status="unavailable">not recorded</StatusBadge>}
                  </div>
                </CardHeader>
                <CardContent className="grid gap-4">
                  <div className="rounded-lg border border-border bg-surface-raised p-3 text-sm">
                    <p className="text-xs font-bold uppercase tracking-wide text-foreground-faint">Currently requested</p>
                    <p className="mt-1 text-lg font-semibold">{value} {definition.unit}</p>
                    <p className="mt-1 break-words text-xs text-foreground-muted">
                      {current?.targets.length
                        ? current.targets.map((target) => `${target.target}: ${target.status}`).join(" · ")
                        : "No target reconciliation state available."}
                    </p>
                  </div>
                  <label className="grid gap-2 text-sm font-semibold" htmlFor={`policy-${key}`}>
                    Desired value ({definition.unit})
                    <Input
                      defaultValue={value}
                      id={`policy-${key}`}
                      max={definition.max}
                      min={definition.min}
                      name={key}
                      required
                      step={definition.step}
                      type="number"
                    />
                  </label>
                </CardContent>
              </Card>
            );
          })}
        </div>
        <div className="flex flex-col items-start">
          <Button type="submit"><Save aria-hidden="true" />Save desired policy</Button>
          <p className="mt-3 text-xs text-foreground-muted">Saving records desired state; it does not claim immediate infrastructure convergence.</p>
        </div>
        {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
      </form>
    </section>
  );
}
