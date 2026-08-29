import { notFound } from "next/navigation";
import { DocumentFamilyRouteScaffold } from "@/components/DocumentFamilyRouteScaffold";
import { initialDocumentFamilyMetadata, userWorkspace } from "@/lib/server-api";

export default async function DocumentFamilyComparePage({ params }: Readonly<{ params: Promise<{ familyPublicId: string; workspacePublicId: string }> }>) {
  const { familyPublicId, workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  const family = await initialDocumentFamilyMetadata(workspacePublicId, familyPublicId);
  if (!family) notFound();
  return <DocumentFamilyRouteScaffold description="Compare two authorised versions from the same document family." emptyDescription="Choose-version controls and comparison content will be added in the dedicated comparison stage." family={family} title="Compare versions" />;
}
