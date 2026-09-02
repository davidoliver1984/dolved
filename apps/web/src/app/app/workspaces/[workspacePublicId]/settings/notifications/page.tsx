import { notFound } from "next/navigation";
import { GovernanceNotificationPreferences } from "@/components/GovernanceNotificationPreferences";
import { userWorkspace } from "@/lib/server-api";

export default async function NotificationPreferencesPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  return <GovernanceNotificationPreferences workspacePublicId={workspacePublicId} />;
}
