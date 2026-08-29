import { Tags } from "lucide-react";
import { notFound } from "next/navigation";
import { KnowledgeLibraryScaffold } from "@/components/KnowledgeLibraryScaffold";
import { userWorkspace } from "@/lib/server-api";

export default async function DocumentCategoriesPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  return <KnowledgeLibraryScaffold description="Keep the library organised with workspace-owned document categories." emptyDescription="Category management will appear here when it is available." emptyTitle="No category controls yet" icon={Tags} title="Categories" />;
}
