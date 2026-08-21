import Link from "next/link";
import { SearchX } from "lucide-react";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";

export default function WorkspaceNotFound() {
  return (
    <EmptyState action={<Button asChild><Link href="/app">Return to your workspaces</Link></Button>} description="This workspace does not exist or is not available to your account." icon={SearchX} title="Workspace not found." />
  );
}
