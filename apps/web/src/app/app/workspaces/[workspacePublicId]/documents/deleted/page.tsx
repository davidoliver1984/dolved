import { Archive } from "lucide-react";
import { notFound } from "next/navigation";
import { KnowledgeLibraryScaffold } from "@/components/KnowledgeLibraryScaffold";
import { userWorkspace } from "@/lib/server-api";

export default async function DeletedDocumentsPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  return <KnowledgeLibraryScaffold description="An immutable history of document families removed from the active library." emptyDescription="Deleted document-family history will appear here when it exists." emptyTitle="No deleted document families" icon={Archive} title="Deleted history" />;
}
