import { HelpCircle } from "lucide-react";
import type { ReactNode } from "react";

export function UnavailableState({ action, description, title = "Data unavailable" }: Readonly<{ action?: ReactNode; description: string; title?: string }>) {
  return <section className="rounded-xl border border-status-unavailable/50 bg-status-unavailable/10 p-5"><div className="flex gap-3"><HelpCircle aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-foreground-muted" /><div><h3 className="font-semibold">{title}</h3><p className="mt-1 text-sm text-foreground-muted">{description}</p>{action ? <div className="mt-4">{action}</div> : null}</div></div></section>;
}
