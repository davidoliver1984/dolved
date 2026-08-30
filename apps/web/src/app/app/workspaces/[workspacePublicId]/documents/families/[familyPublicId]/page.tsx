import { notFound } from "next/navigation";
import { DocumentFamilyDetail } from "@/components/DocumentFamilyDetail";
import { initialDocumentFamilyDetail, userWorkspace } from "@/lib/server-api";

export default async function DocumentFamilyPage({ params }: Readonly<{ params: Promise<{ familyPublicId: string; workspacePublicId: string }> }>) {
  const { familyPublicId, workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  const detail = await initialDocumentFamilyDetail(workspacePublicId, familyPublicId);
  if (!detail) notFound();
  return <DocumentFamilyDetail detail={detail} workspacePublicId={workspacePublicId} />;
}
