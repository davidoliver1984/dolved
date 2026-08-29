import { notFound } from "next/navigation";
import { DocumentFamilyRouteScaffold } from "@/components/DocumentFamilyRouteScaffold";
import { initialDocumentFamilyMetadata, userWorkspace } from "@/lib/server-api";

export default async function DocumentFamilyVersionsPage({ params }: Readonly<{ params: Promise<{ familyPublicId: string; workspacePublicId: string }> }>) {
  const { familyPublicId, workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  const family = await initialDocumentFamilyMetadata(workspacePublicId, familyPublicId);
  if (!family) notFound();
  return <DocumentFamilyRouteScaffold description="A chronological, authority-aware history of every version in this family." emptyDescription="Version history will appear here after the family-detail surface is connected." family={family} title="Version history" />;
}
