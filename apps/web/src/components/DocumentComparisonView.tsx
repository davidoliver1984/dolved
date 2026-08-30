"use client";

import { ArrowLeft, ArrowRight, ArrowRightLeft, Columns2, List } from "lucide-react";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import { formatDate } from "@/lib/date";
import type { ComparisonSide, ComparisonStatus, DocumentComparison } from "@/lib/server-api";
import { cn } from "@/lib/utils";

type ViewMode = "side-by-side" | "inline";
type Filter = "changes" | ComparisonStatus;
type Difference = NonNullable<DocumentComparison["differences"]>[number];

const statusTone: Record<ComparisonStatus, StatusTone> = {
  added: "success", removed: "destructive", modified: "warning", moved: "info", unchanged: "unavailable",
};

export function DocumentComparisonView({ comparison, familyName }: Readonly<{ comparison: DocumentComparison; familyName: string }>) {
  const [filter, setFilter] = useState<Filter>("changes");
  const [mode, setMode] = useState<ViewMode>("side-by-side");
  const [expandedUnchanged, setExpandedUnchanged] = useState(false);
  const [activeChange, setActiveChange] = useState(0);
  const differences = useMemo(
    () => comparison.differences ?? [],
    [comparison.differences],
  );
  const changeIds = useMemo(() => differences.filter((item) => item.status !== "unchanged").map((item) => item.id), [differences]);

  if (!comparison.available || !comparison.from || !comparison.to) {
    return <div className="grid gap-6"><ComparisonHeader familyName={familyName} /><Notice tone="info">{comparison.reason ?? "Comparison is unavailable."}</Notice></div>;
  }

  const visible = differences.filter((item) => {
    if (item.status === "unchanged" && !expandedUnchanged && filter !== "unchanged") return false;
    return filter === "changes"
      ? item.status !== "unchanged" || expandedUnchanged
      : item.status === filter;
  });
  const counts = comparison.change_counts ?? countDifferences(differences);
  const navigate = (direction: -1 | 1) => {
    if (!changeIds.length) return;
    const next = (activeChange + direction + changeIds.length) % changeIds.length;
    setActiveChange(next);
    document.getElementById(`difference-${changeIds[next]}`)?.scrollIntoView({ behavior: "smooth", block: "center" });
  };

  return <div className="grid gap-6">
    <ComparisonHeader familyName={familyName} />
    <div className="grid items-stretch gap-3 md:grid-cols-[1fr_auto_1fr]"><Version side={comparison.from} title="From" /><ArrowRight className="mx-auto self-center text-foreground-muted" /><Version side={comparison.to} title="To" /></div>
    <AlignmentNotices comparison={comparison} />
    <section aria-labelledby="changes-heading" className="grid gap-4">
      <div className="flex flex-wrap items-end justify-between gap-3"><div><h2 className="text-2xl font-semibold" id="changes-heading">Content changes</h2><p className="mt-1 text-sm text-foreground-muted">Structured extracted content aligned by section. Formatting-only changes are not available.</p></div><div className="flex gap-2" role="group" aria-label="Comparison layout"><Button aria-pressed={mode === "side-by-side"} onClick={() => setMode("side-by-side")} size="sm" variant={mode === "side-by-side" ? "default" : "outline"}><Columns2 />Side by side</Button><Button aria-pressed={mode === "inline"} onClick={() => setMode("inline")} size="sm" variant={mode === "inline" ? "default" : "outline"}><List />Inline</Button></div></div>
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filter changes">{(["changes", "added", "removed", "modified", "moved", "unchanged"] as const).map((value) => <Button aria-pressed={filter === value} key={value} onClick={() => setFilter(value)} size="sm" variant={filter === value ? "default" : "outline"}>{label(value)} {value === "changes" ? changeIds.length : counts[value]}</Button>)}</div>
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-surface px-3 py-2"><Button disabled={!changeIds.length} onClick={() => navigate(-1)} size="sm" variant="ghost"><ArrowLeft />Previous change</Button><span className="text-sm text-foreground-muted">{changeIds.length ? `${activeChange + 1} of ${changeIds.length} changes` : "No changes"}</span><Button disabled={!changeIds.length} onClick={() => navigate(1)} size="sm" variant="ghost">Next change<ArrowRight /></Button></div>
      {counts.unchanged > 0 && filter !== "unchanged" ? <button className="justify-self-start text-sm font-semibold text-brand underline-offset-4 hover:underline" onClick={() => setExpandedUnchanged((value) => !value)} type="button">{expandedUnchanged ? "Collapse" : "Show"} {counts.unchanged} unchanged {counts.unchanged === 1 ? "section" : "sections"}</button> : null}
      <div className="grid gap-3">{visible.length ? visible.map((difference) => <DifferenceCard difference={difference} key={difference.id} mode={mode} />) : <p className="rounded-xl border border-dashed border-border p-8 text-center text-foreground-muted">No content matches this filter.</p>}</div>
    </section>
  </div>;
}

function ComparisonHeader({ familyName }: Readonly<{ familyName: string }>) {
  return <header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Version comparison</p><h1 className="mt-2 text-3xl font-semibold">{familyName}</h1><p className="mt-2 text-foreground-muted">Family-owned metadata is shared. Version-owned source, authority and extracted content are compared.</p></header>;
}

function AlignmentNotices({ comparison }: Readonly<{ comparison: DocumentComparison }>) {
  return <div className="grid gap-2">{comparison.alignment_status === "partial" ? <Notice tone="warning">{comparison.alignment_reason ?? "This comparison is partial."}</Notice> : null}{comparison.alignment_status === "unavailable" ? <Notice tone="warning">{comparison.alignment_reason ?? "Extracted content cannot be aligned reliably."}</Notice> : null}{(comparison.from?.truncated || comparison.to?.truncated) ? <Notice tone="info">This large comparison is bounded to the first 500 structured elements on each side.</Notice> : null}{comparison.formatting_comparison === "unavailable" ? <Notice tone="info">{comparison.formatting_reason ?? "Formatting-only comparison is unavailable."}</Notice> : null}</div>;
}

function Version({ side, title }: Readonly<{ side: ComparisonSide; title: string }>) {
  return <Card><CardHeader><p className="text-xs font-bold uppercase tracking-[0.12em] text-brand">{title}</p><CardTitle>{side.document.source_filename}</CardTitle></CardHeader><CardContent><dl className="grid gap-2 text-sm"><div><dt className="text-foreground-muted">Publisher</dt><dd>{side.document.publisher_label ?? "Not recorded"}</dd></div><div><dt className="text-foreground-muted">Effective from</dt><dd>{side.document.effective_from ? formatDate(side.document.effective_from) : "Not recorded"}</dd></div><div><dt className="text-foreground-muted">Governance</dt><dd>{side.document.governance_status}</dd></div></dl></CardContent></Card>;
}

function DifferenceCard({ difference, mode }: Readonly<{ difference: Difference; mode: ViewMode }>) {
  return <Card id={`difference-${difference.id}`}><CardHeader className="flex-row flex-wrap items-center justify-between gap-2"><div><p className="text-xs font-bold uppercase tracking-[0.12em] text-foreground-muted">{difference.section}</p><p className="mt-1 text-sm text-foreground-muted">Position {difference.position}</p></div><StatusBadge status={statusTone[difference.status]}>{label(difference.status)}</StatusBadge></CardHeader><CardContent className={cn("grid gap-3", mode === "side-by-side" && "md:grid-cols-2")}>{difference.status === "modified" && difference.before && difference.after ? <WordDifference before={difference.before.text} mode={mode} after={difference.after.text} /> : <><DiffText label="Before" text={difference.before?.text} /><DiffText label="After" text={difference.after?.text} /></>}{difference.status === "moved" ? <p className="flex items-center gap-2 text-sm text-foreground-muted md:col-span-2"><ArrowRightLeft aria-hidden="true" className="size-4" />This extracted block is unchanged but appears at a different position.</p> : null}</CardContent></Card>;
}

function DiffText({ label: textLabel, text }: Readonly<{ label: string; text?: string }>) {
  return <div><h3 className="text-sm font-semibold text-foreground-muted">{textLabel}</h3><p className="mt-2 whitespace-pre-wrap leading-7">{text ?? "No content on this side."}</p></div>;
}

function WordDifference({ after, before, mode }: Readonly<{ after: string; before: string; mode: ViewMode }>) {
  const beforeWords = before.split(/(\s+)/); const afterWords = after.split(/(\s+)/); const common = new Set(beforeWords.filter((word) => word.trim() && afterWords.includes(word)));
  const content = <><DiffWords label="Before" words={beforeWords} common={common} tone="removed" /><DiffWords label="After" words={afterWords} common={common} tone="added" /></>;
  return mode === "inline" ? <div className="grid gap-4">{content}</div> : content;
}

function DiffWords({ common, label: textLabel, tone, words }: Readonly<{ common: Set<string>; label: string; tone: "added" | "removed"; words: string[] }>) {
  const highlighted = tone === "added" ? "bg-status-success/30" : "bg-status-destructive/30";
  return <div><h3 className="text-sm font-semibold text-foreground-muted">{textLabel}</h3><p className="mt-2 whitespace-pre-wrap leading-7">{words.map((word, index) => <span className={word.trim() && !common.has(word) ? highlighted : undefined} key={`${index}-${word}`}>{word}</span>)}</p></div>;
}

function countDifferences(differences: Difference[]): Record<ComparisonStatus, number> {
  const counts: Record<ComparisonStatus, number> = { added: 0, removed: 0, modified: 0, moved: 0, unchanged: 0 };
  for (const difference of differences) counts[difference.status] += 1;
  return counts;
}

function label(value: Filter): string { return value === "changes" ? "All changes" : value.slice(0, 1).toUpperCase() + value.slice(1); }
