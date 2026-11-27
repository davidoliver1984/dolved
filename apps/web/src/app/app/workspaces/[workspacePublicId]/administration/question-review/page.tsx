import { notFound } from "next/navigation";
import { QuestionReviewAdministration } from "@/components/QuestionReviewAdministration";
import { userWorkspace } from "@/lib/server-api";

export default async function QuestionReviewPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace || workspace.role === "member") notFound();
  return <div className="grid gap-6"><header><p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Administration</p><h1 className="mt-2 text-3xl font-semibold">Question review</h1><p className="mt-2 text-foreground-muted">Restricted, audited review for corpus improvement. Question content never enters ordinary telemetry.</p></header><QuestionReviewAdministration workspaceId={workspacePublicId} /></div>;
}
