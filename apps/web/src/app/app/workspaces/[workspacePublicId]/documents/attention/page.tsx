import { CircleCheck } from "lucide-react";
import { notFound } from "next/navigation";
import { KnowledgeLibraryScaffold } from "@/components/KnowledgeLibraryScaffold";
import { userWorkspace } from "@/lib/server-api";

export default async function DocumentsAttentionPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  return <KnowledgeLibraryScaffold description="Document failures, extraction warnings, and versions awaiting approval." emptyDescription="Warnings and approval work will appear here when action is required." emptyTitle="Nothing needs attention" icon={CircleCheck} title="Needs attention" />;
}
