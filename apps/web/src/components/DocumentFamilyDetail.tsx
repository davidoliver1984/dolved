import { CalendarDays, CircleAlert, ExternalLink, FileClock, GitCompareArrows, Tags, UserRound } from "lucide-react";
import Link from "next/link";
import { DocumentGovernanceActions } from "@/components/DocumentGovernanceActions";
import { DocumentFamilyMetadataEditor } from "@/components/DocumentFamilyMetadataEditor";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { StatusBadge, type StatusTone } from "@/components/ui/status-badge";
import type { DocumentVersion } from "@/lib/api";
import type { DocumentFamilyDetail as Detail } from "@/lib/server-api";
import { formatDate, formatDateTime } from "@/lib/date";

function bytes(value: number): string {
  if (value < 1024) return `${value} B`;
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${(value / 1024 / 1024).toFixed(1)} MB`;
}

function title(value: string): string {
  return value.replaceAll("_", " ").replace(/^./, (letter) => letter.toUpperCase());
}

function governanceTone(version: DocumentVersion): StatusTone {
  if (version.is_current_authority) return "success";
  if (version.governance_status === "withdrawn") return "unavailable";
  if (version.governance_status === "draft") return "info";
  return "pending";
}

function governanceLabel(version: DocumentVersion): string {
  if (version.is_current_authority) return "Current authority";
  if (version.governance_status === "withdrawn") return "Withdrawn";
  if (version.governance_status === "draft") return "Draft";
  if (new Date(version.effective_from).getTime() > Date.now()) return "Scheduled";
  return "Approved history";
}

function VersionCard({
  index,
  locations,
  version,
  workspacePublicId,
}: Readonly<{
  index: number;
  locations: Detail["history"]["meta"]["locations"];
  version: DocumentVersion;
  workspacePublicId: string;
}>) {
  const applicationPath = `/app/workspaces/${workspacePublicId}/documents/${version.public_id}`;
  return (
    <Card className={version.is_current_authority ? "border-brand ring-1 ring-brand" : undefined}>
      <CardHeader>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.12em] text-foreground-muted">Version {index + 1}</p>
            <CardTitle className="mt-1">{version.source_filename}</CardTitle>
            <CardDescription>{version.publisher_label ?? "Publisher not recorded"} · {version.media_type} · {bytes(version.size_bytes)}</CardDescription>
          </div>
          <div className="flex flex-wrap gap-2">
            <StatusBadge status={governanceTone(version)}>{governanceLabel(version)}</StatusBadge>
            <StatusBadge status={version.status === "failed" ? "destructive" : version.status === "indexed" ? "success" : "pending"}>{title(version.status)}</StatusBadge>
          </div>
        </div>
      </CardHeader>
      <CardContent className="grid gap-4">
        <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
          <div><dt className="text-foreground-muted">Effective from</dt><dd className="mt-1 font-medium">{formatDate(version.effective_from)}</dd></div>
          <div><dt className="text-foreground-muted">Approved</dt><dd className="mt-1 font-medium">{version.approved_at ? formatDateTime(version.approved_at) : "Not approved"}</dd></div>
          <div><dt className="text-foreground-muted">Withdrawn</dt><dd className="mt-1 font-medium">{version.withdrawn_at ? formatDateTime(version.withdrawn_at) : "Not withdrawn"}</dd></div>
          <div><dt className="text-foreground-muted">Applicability</dt><dd className="mt-1 font-medium">{version.applicability.scope === "universal" ? "All locations" : version.applicability.locations.map((location) => location.name).join(", ") || "No locations recorded"}</dd></div>
        </dl>
        {version.source_url ? <p className="text-sm"><a className="inline-flex items-center gap-1 text-brand underline-offset-4 hover:underline" href={version.source_url} rel="noreferrer" target="_blank">Publisher source <ExternalLink aria-hidden="true" className="size-4" /></a></p> : null}
        {version.extraction_warning_count > 0 ? <p className="flex items-center gap-2 text-sm text-warning"><CircleAlert aria-hidden="true" className="size-4" />{version.extraction_warning_count} extraction warning(s)</p> : null}
        <div className="flex flex-wrap gap-2">
          <Button asChild size="sm" variant="secondary"><Link href={applicationPath} target="_blank">Open version <ExternalLink aria-hidden="true" /></Link></Button>
          <Button asChild size="sm" variant="outline"><Link href={`/app/workspaces/${workspacePublicId}/documents/families/${version.family_public_id ?? ""}/compare?from=${version.public_id}`}>Compare</Link></Button>
        </div>
        <DocumentGovernanceActions locations={locations} version={version} workspacePublicId={workspacePublicId} />
      </CardContent>
    </Card>
  );
}

export function DocumentFamilyDetail({ detail, workspacePublicId }: Readonly<{ detail: Detail; workspacePublicId: string }>) {
  const { family, history } = detail;
  return (
    <div className="grid gap-6">
      <header>
        <div>
          <p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Document family</p>
          <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">{family.name}</h1>
          <p className="mt-2 max-w-3xl text-foreground-muted">{family.description ?? "No family description has been recorded."}</p>
        </div>
      </header>

      <DocumentFamilyMetadataEditor family={family} workspacePublicId={workspacePublicId} />

      <Card>
        <CardHeader><CardTitle>Family metadata</CardTitle><CardDescription>These facts belong to the family and do not change between versions.</CardDescription></CardHeader>
        <CardContent><dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
          <div><dt className="flex items-center gap-2 text-foreground-muted"><Tags className="size-4" />Category</dt><dd className="mt-1 font-medium">{family.category?.name ?? "Uncategorised"}</dd></div>
          <div><dt className="flex items-center gap-2 text-foreground-muted"><UserRound className="size-4" />Owner</dt><dd className="mt-1 font-medium">{family.owner?.name ?? "Needs reassignment"}</dd></div>
          <div><dt className="flex items-center gap-2 text-foreground-muted"><CalendarDays className="size-4" />Review due</dt><dd className="mt-1 font-medium">{family.review_due_date ? formatDate(`${family.review_due_date}T00:00:00Z`) : "Not set"}</dd></div>
          <div><dt className="text-foreground-muted">Tags</dt><dd className="mt-1 flex flex-wrap gap-1.5">{family.tags.length ? family.tags.map((tag) => <span className="rounded-full border border-border px-2 py-1 text-xs" key={tag.public_id}>{tag.name}</span>) : <span className="font-medium">No tags</span>}</dd></div>
        </dl></CardContent>
      </Card>

      <section aria-labelledby="version-history-heading" className="grid gap-4">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Authority timeline</p><h2 className="mt-1 text-2xl font-semibold" id="version-history-heading">Version history</h2><p className="mt-1 text-foreground-muted">Every version is shown with its recorded governance, technical and applicability state.</p></div>
          <div className="flex gap-2"><Button asChild variant="outline"><Link href={`/app/workspaces/${workspacePublicId}/documents/families/${family.public_id}/versions`}><FileClock aria-hidden="true" />Version history</Link></Button>{history.data.length > 1 ? <Button asChild variant="secondary"><Link href={`/app/workspaces/${workspacePublicId}/documents/families/${family.public_id}/compare`}><GitCompareArrows aria-hidden="true" />Compare versions</Link></Button> : null}</div>
        </div>
        {history.data.map((version, index) => <VersionCard index={index} key={version.public_id} locations={history.meta.locations} version={version} workspacePublicId={workspacePublicId} />)}
      </section>
    </div>
  );
}
