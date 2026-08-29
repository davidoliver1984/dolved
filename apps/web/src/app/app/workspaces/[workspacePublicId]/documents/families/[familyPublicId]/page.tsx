import { notFound } from "next/navigation";
import { DocumentFamilyRouteScaffold } from "@/components/DocumentFamilyRouteScaffold";
import { initialDocumentFamilyMetadata, userWorkspace } from "@/lib/server-api";

export default async function DocumentFamilyPage({ params }: Readonly<{ params: Promise<{ familyPublicId: string; workspacePublicId: string }> }>) {
  const { familyPublicId, workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  const family = await initialDocumentFamilyMetadata(workspacePublicId, familyPublicId);
  if (!family) notFound();
  return <DocumentFamilyRouteScaffold description="Family metadata, current authority, and governance controls will be gathered here." emptyDescription="The family-detail surface will be connected to the reviewed library read model next." family={family} title="Family details" />;
}
