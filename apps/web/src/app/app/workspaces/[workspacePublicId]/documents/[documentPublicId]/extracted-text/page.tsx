import { AlertTriangle, ArrowLeft, ArrowRight, FileText } from "lucide-react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Notice } from "@/components/ui/notice";
import { initialExtractedText, initialWorkspaceDocument, userWorkspace } from "@/lib/server-api";

export default async function ExtractedTextPage({ params, searchParams }: Readonly<{ params: Promise<{ documentPublicId: string; workspacePublicId: string }>; searchParams: Promise<{ cursor?: string }> }>) {
  const { documentPublicId, workspacePublicId } = await params;
  const { cursor } = await searchParams;
  const query = new URLSearchParams({ per_page: "25" });
  if (cursor) query.set("cursor", cursor);
  const [workspace, document, content] = await Promise.all([userWorkspace(workspacePublicId), initialWorkspaceDocument(workspacePublicId, documentPublicId), initialExtractedText(workspacePublicId, documentPublicId, query.toString())]);
  if (!workspace || !document || !content) notFound();
  const base = `/app/workspaces/${workspacePublicId}/documents/${documentPublicId}/extracted-text`;
  return <div className="mx-auto grid max-w-5xl gap-6">
    <header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">{content.label}</p><h1 className="mt-2 break-words text-3xl font-semibold">{document.source_filename}</h1><p className="mt-2 text-foreground-muted">{content.notice}</p><Button asChild className="mt-4" size="sm" variant="outline"><Link href={`/app/workspaces/${workspacePublicId}/documents/${documentPublicId}`}><FileText aria-hidden="true" />View source</Link></Button></header>
    {content.warnings.length ? <Notice tone="warning"><AlertTriangle aria-hidden="true" /><div><strong>Extraction warnings</strong><ul className="mt-2 list-disc pl-5">{content.warnings.map((warning, index) => <li key={`${warning.code}-${index}`}>{warning.message ?? warning.code ?? "Extraction warning"}</li>)}</ul>{content.warnings_truncated ? <p>Additional warnings are not shown.</p> : null}</div></Notice> : null}
    <section aria-label="Extracted document content" className="grid gap-3">{content.elements.length ? content.elements.map((element) => <article className="rounded-xl border border-border bg-surface p-5" key={element.id}>{element.kind === "heading" ? <h2 className="text-xl font-semibold">{element.text}</h2> : <><p className="whitespace-pre-wrap leading-7">{element.text}</p>{element.kind === "table" && element.rows ? <p className="mt-3 text-sm text-foreground-muted">Structured table content is available in the source.</p> : null}</>}</article>) : <div className="rounded-xl border border-dashed border-border p-8 text-center"><h2 className="font-semibold">No extracted text on this page</h2><p className="mt-2 text-foreground-muted">The projection contains no matching elements here.</p></div>}</section>
    <nav aria-label="Extracted text pages" className="flex justify-between">{content.pagination.previous_cursor ? <Button asChild variant="outline"><Link href={`${base}?cursor=${encodeURIComponent(content.pagination.previous_cursor)}`}><ArrowLeft />Previous</Link></Button> : <span />}{content.pagination.next_cursor ? <Button asChild variant="outline"><Link href={`${base}?cursor=${encodeURIComponent(content.pagination.next_cursor)}`}>Next<ArrowRight /></Link></Button> : null}</nav>
  </div>;
}
