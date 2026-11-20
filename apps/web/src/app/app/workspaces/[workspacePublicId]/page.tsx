import { notFound } from "next/navigation";
import { RoutedChatWorkspace } from "@/components/RoutedChatWorkspace";
import { initialDocumentMetadata, userWorkspace } from "@/lib/server-api";

export default async function WorkspacePage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const workspace = await userWorkspace(workspacePublicId);
  if (!workspace) notFound();
  const metadata = await initialDocumentMetadata(workspacePublicId);
  return <RoutedChatWorkspace conversationId={null} locations={metadata.locations} workspaceId={workspace.public_id} workspaceName={workspace.name} />;
}
