import { FileText } from "lucide-react";
import { notFound } from "next/navigation";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatDateTime } from "@/lib/date";
import { initialWorkspaceDocument, userWorkspace } from "@/lib/server-api";

function bytes(value: number): string {
  if (value < 1024) return `${value} B`;
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

export default async function DocumentSourcePage({ params }: Readonly<{ params: Promise<{ documentPublicId: string; workspacePublicId: string }> }>) {
  const { documentPublicId, workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const document = await initialWorkspaceDocument(workspacePublicId, documentPublicId);
  if (!document) notFound();

  return (
    <div className="grid max-w-3xl gap-6">
      <header>
        <p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Document source</p>
        <h1 className="mt-2 text-3xl font-semibold">{document.source_filename}</h1>
        <p className="mt-2 text-foreground-muted">Authorised source details for {workspace.name}.</p>
      </header>
      <Card>
        <CardHeader className="flex-row items-start gap-4">
          <span className="grid size-11 shrink-0 place-items-center rounded-lg bg-surface-raised text-brand">
            <FileText aria-hidden="true" className="size-5" />
          </span>
          <div className="min-w-0 flex-1">
            <CardTitle>{document.source_filename}</CardTitle>
            <p className="mt-1 text-sm text-foreground-muted">{document.media_type} · {bytes(document.size_bytes)}</p>
          </div>
          <StatusBadge status={document.status === "indexed" ? "success" : "pending"}>{document.status}</StatusBadge>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt className="text-foreground-muted">Governance</dt><dd className="mt-1 font-medium">{document.governance_status}</dd></div>
            <div><dt className="text-foreground-muted">Added</dt><dd className="mt-1 font-medium">{formatDateTime(document.created_at)}</dd></div>
            <div><dt className="text-foreground-muted">Added by</dt><dd className="mt-1 font-medium">{document.created_by?.name ?? "Unavailable"}</dd></div>
            <div><dt className="text-foreground-muted">Source status</dt><dd className="mt-1 font-medium">{document.status}</dd></div>
          </dl>
        </CardContent>
      </Card>
    </div>
  );
}
