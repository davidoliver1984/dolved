import { ArrowRight, CircleAlert } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatDate } from "@/lib/date";
import type { ComparisonSide, DocumentComparison } from "@/lib/server-api";

export function DocumentComparisonView({ comparison, familyName }: Readonly<{ comparison: DocumentComparison; familyName: string }>) {
  if (!comparison.available || !comparison.from || !comparison.to) return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Version comparison</p><h1 className="mt-2 text-3xl font-semibold">{familyName}</h1></header><Notice tone="info"><CircleAlert />{comparison.reason ?? "Comparison is unavailable."}</Notice></div>;
  const visible = comparison.differences?.filter((difference) => difference.status !== "unchanged") ?? [];
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Version comparison</p><h1 className="mt-2 text-3xl font-semibold">{familyName}</h1><p className="mt-2 text-foreground-muted">Family-owned metadata is shared. Only version-owned source, authority and extracted content are compared.</p></header>
    <div className="grid items-stretch gap-3 md:grid-cols-[1fr_auto_1fr]"><Version side={comparison.from} title="From" /><ArrowRight className="mx-auto self-center text-foreground-muted" /><Version side={comparison.to} title="To" /></div>
    {(!comparison.from.content_available || !comparison.to.content_available) ? <Notice tone="warning"><CircleAlert />Extracted content is unavailable for one side. Recorded version metadata remains visible.</Notice> : null}
    {(comparison.from.truncated || comparison.to.truncated) ? <Notice tone="info"><CircleAlert />This large comparison is bounded to the first 500 structured elements on each side.</Notice> : null}
    <section className="grid gap-3" aria-labelledby="changes-heading"><h2 className="text-2xl font-semibold" id="changes-heading">Content changes</h2>{visible.length ? visible.map((difference) => <Card key={difference.ordinal}><CardHeader><StatusBadge status={difference.status === "added" ? "success" : difference.status === "removed" ? "destructive" : "pending"}>{difference.status}</StatusBadge></CardHeader><CardContent className="grid gap-3 md:grid-cols-2"><DiffText label="Before" text={difference.before?.text} /><DiffText label="After" text={difference.after?.text} /></CardContent></Card>) : <p className="rounded-xl border border-dashed border-border p-8 text-center text-foreground-muted">No extracted-content changes were found in the bounded comparison.</p>}</section>
  </div>;
}

function Version({ side, title }: Readonly<{ side: ComparisonSide; title: string }>) {
  return <Card><CardHeader><p className="text-xs font-bold uppercase tracking-[0.12em] text-brand">{title}</p><CardTitle>{side.document.source_filename}</CardTitle></CardHeader><CardContent><dl className="grid gap-2 text-sm"><div><dt className="text-foreground-muted">Publisher</dt><dd>{side.document.publisher_label ?? "Not recorded"}</dd></div><div><dt className="text-foreground-muted">Effective from</dt><dd>{side.document.effective_from ? formatDate(side.document.effective_from) : "Not recorded"}</dd></div><div><dt className="text-foreground-muted">Governance</dt><dd>{side.document.governance_status}</dd></div></dl></CardContent></Card>;
}

function DiffText({ label, text }: Readonly<{ label: string; text?: string }>) {
  return <div><h3 className="text-sm font-semibold text-foreground-muted">{label}</h3><p className="mt-2 whitespace-pre-wrap leading-7">{text ?? "No content at this position."}</p></div>;
}
