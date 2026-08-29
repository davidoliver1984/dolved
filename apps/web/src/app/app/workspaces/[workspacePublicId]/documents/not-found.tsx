import { SearchX } from "lucide-react";
import Link from "next/link";
import { EmptyState } from "@/components/ui/empty-state";
import { Button } from "@/components/ui/button";

export default function KnowledgeLibraryNotFound() {
  return <EmptyState action={<Button asChild><Link href="/app">Return to your workspaces</Link></Button>} description="This library item does not exist or is not available to your account." icon={SearchX} title="Library item not found" />;
}
