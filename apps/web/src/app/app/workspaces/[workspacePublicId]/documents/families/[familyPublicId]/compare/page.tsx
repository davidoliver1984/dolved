import { notFound } from "next/navigation";
import { DocumentComparisonView } from "@/components/DocumentComparisonView";
import { initialDocumentComparison, initialDocumentFamilyMetadata, userWorkspace } from "@/lib/server-api";

export default async function DocumentFamilyComparePage({ params, searchParams }: Readonly<{ params: Promise<{ familyPublicId: string; workspacePublicId: string }>; searchParams: Promise<{ from?: string; to?: string }> }>) {
  const { familyPublicId, workspacePublicId } = await params;
  const selection = await searchParams;
  const query = new URLSearchParams();
  if (selection.from) query.set("from", selection.from);
  if (selection.to) query.set("to", selection.to);
  const [workspace, family, comparison] = await Promise.all([userWorkspace(workspacePublicId), initialDocumentFamilyMetadata(workspacePublicId, familyPublicId), initialDocumentComparison(workspacePublicId, familyPublicId, query.toString())]);
  if (!workspace || !family || !comparison) notFound();
  return <DocumentComparisonView comparison={comparison} familyName={family.name} />;
}
