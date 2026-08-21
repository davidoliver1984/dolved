"use client";

import Link from "next/link";
import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";

export default function AuthenticatedApplicationError({
  reset,
}: Readonly<{ error: Error & { digest?: string }; reset: () => void }>) {
  return (
    <div role="alert"><EmptyState action={<div className="flex flex-wrap justify-center gap-3"><Button onClick={reset} type="button">Try again</Button><Button asChild variant="secondary"><Link href="/app">Return to workspaces</Link></Button></div>} description="Your account is still secure. Try loading this page again, or return to your workspace list." icon={AlertTriangle} title="We could not load this workspace." /></div>
  );
}
