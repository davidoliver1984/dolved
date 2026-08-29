"use client";

import { AlertTriangle } from "lucide-react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { EmptyState } from "@/components/ui/empty-state";
import { Button } from "@/components/ui/button";

export default function KnowledgeLibraryError({ reset }: Readonly<{ error: Error & { digest?: string }; reset: () => void }>) {
  const { workspacePublicId } = useParams<{ workspacePublicId: string }>();
  return <div role="alert"><EmptyState action={<div className="flex flex-wrap justify-center gap-3"><Button onClick={reset} type="button">Try again</Button><Button asChild variant="secondary"><Link href={`/app/workspaces/${workspacePublicId}`}>Return to chat</Link></Button></div>} description="Your workspace remains secure. Try loading this page again or return to chat." icon={AlertTriangle} title="We could not load the knowledge library" /></div>;
}
