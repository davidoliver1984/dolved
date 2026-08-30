import { Download, FileSearch } from "lucide-react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Button } from "@/components/ui/button";
import { initialWorkspaceDocument, userWorkspace } from "@/lib/server-api";

function bytes(value: number): string {
  if (value < 1024) return `${value} B`;
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${(value / 1024 / 1024).toFixed(1)} MB`;
}

export default async function DocumentSourcePage({ params }: Readonly<{ params: Promise<{ documentPublicId: string; workspacePublicId: string }> }>) {
  const { documentPublicId, workspacePublicId } = await params;
  const [workspace, document] = await Promise.all([userWorkspace(workspacePublicId), initialWorkspaceDocument(workspacePublicId, documentPublicId)]);
  if (!workspace || !document || document.status !== "indexed") notFound();
  const contentPath = `/app/workspaces/${workspacePublicId}/documents/${documentPublicId}/content`;
  const inline = ["application/pdf", "text/plain", "text/markdown"].includes(document.media_type);

  return (
    <div className="grid min-h-[calc(100vh-8rem)] gap-5">
      <header className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Authorised source</p><h1 className="mt-2 break-words text-3xl font-semibold">{document.source_filename}</h1><p className="mt-2 text-foreground-muted">{document.media_type} · {bytes(document.size_bytes)}</p><p className="mt-1 text-sm text-foreground-muted">Source retained by {workspace.name}.</p></div><div className="flex flex-wrap gap-2"><Button asChild variant="outline"><Link href={`${contentPath}?download=1`}><Download aria-hidden="true" />Download</Link></Button><Button asChild variant="secondary"><Link href={`/app/workspaces/${workspacePublicId}/documents/${documentPublicId}/extracted-text`}><FileSearch aria-hidden="true" />Extracted text</Link></Button></div></header>
      {inline ? <iframe className="min-h-[70vh] w-full rounded-xl border border-border bg-white" src={contentPath} title={`Source: ${document.source_filename}`} /> : <section className="grid min-h-80 place-items-center rounded-xl border border-dashed border-border bg-surface p-8 text-center"><div><h2 className="text-xl font-semibold">Preview unavailable</h2><p className="mt-2 max-w-lg text-foreground-muted">This source format cannot be previewed safely in the browser. Download the authorised original or inspect the extracted text.</p></div></section>}
    </div>
  );
}
