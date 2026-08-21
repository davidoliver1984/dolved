import { FileText } from "lucide-react";
import Link from "next/link";
import type { ReactNode } from "react";

export function EvidenceItem({ excerpt, metadata, provenance, sourceHref, sourceUnavailableLabel = "Source no longer available", title }: Readonly<{ excerpt?: ReactNode; metadata: string; provenance?: string; sourceHref?: string; sourceUnavailableLabel?: string; title: string }>) {
  return <article className="rounded-xl border border-border bg-surface-raised p-4"><div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-lg bg-background text-brand"><FileText aria-hidden="true" className="size-5" /></span><div className="min-w-0"><h3 className="font-semibold">{title}</h3><p className="mt-1 text-sm text-foreground-muted">{metadata}</p>{provenance ? <p className="mt-1 text-xs text-foreground-faint">{provenance}</p> : null}{excerpt ? <blockquote className="mt-3 border-l-2 border-brand pl-3 text-sm leading-6 text-foreground-muted">{excerpt}</blockquote> : null}{sourceHref ? <Link className="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-brand underline-offset-4 hover:underline" href={sourceHref}>View source</Link> : <p className="mt-3 text-sm text-foreground-faint">{sourceUnavailableLabel}</p>}</div></div></article>;
}
