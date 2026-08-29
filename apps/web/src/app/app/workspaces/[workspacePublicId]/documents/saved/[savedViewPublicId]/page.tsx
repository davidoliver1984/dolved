import { notFound } from "next/navigation";
import { userWorkspace } from "@/lib/server-api";

export default async function SavedDocumentViewPage({ params }: Readonly<{ params: Promise<{ savedViewPublicId: string; workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  notFound();
}
