import { notFound } from "next/navigation";
import { CategorySettings } from "@/components/CategorySettings";
import { initialDocumentMetadata, userWorkspace } from "@/lib/server-api";

export default async function DocumentCategoriesPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const metadata = await initialDocumentMetadata(workspacePublicId);

  return <CategorySettings canManage={workspace.role !== "member"} initialCategories={metadata.categories} workspacePublicId={workspacePublicId} />;
}
