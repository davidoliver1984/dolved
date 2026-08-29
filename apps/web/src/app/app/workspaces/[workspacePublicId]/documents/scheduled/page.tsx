import { CalendarClock } from "lucide-react";
import { notFound } from "next/navigation";
import { KnowledgeLibraryScaffold } from "@/components/KnowledgeLibraryScaffold";
import { userWorkspace } from "@/lib/server-api";

export default async function ScheduledDocumentsPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  if (!(await userWorkspace(workspacePublicId))) notFound();
  return <KnowledgeLibraryScaffold description="Approved changes that have not yet reached their effective date." emptyDescription="Scheduled document changes will appear here when they exist." emptyTitle="No scheduled changes" icon={CalendarClock} title="Scheduled" />;
}
