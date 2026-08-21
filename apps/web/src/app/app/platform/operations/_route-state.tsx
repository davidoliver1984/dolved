"use client";

import { RotateCcw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Notice } from "@/components/ui/notice";

export function PlatformRouteError({ reset }: Readonly<{ reset: () => void }>) {
  return <section className="grid max-w-2xl gap-5"><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Platform operations</p><h1 className="text-4xl font-semibold tracking-tight">This operational view could not be loaded.</h1><Notice tone="warning">The page failed safely. No operational state was changed.</Notice><Button className="w-fit" onClick={reset}><RotateCcw aria-hidden="true" />Try again</Button></section>;
}

export function PlatformRouteLoading() {
  return <section aria-label="Loading platform operations" className="grid gap-5"><div className="h-4 w-40 animate-pulse rounded bg-surface-raised" /><div className="h-12 w-full max-w-xl animate-pulse rounded bg-surface-raised" /><div className="grid gap-4 md:grid-cols-2"><div className="h-48 animate-pulse rounded-xl bg-surface-raised" /><div className="h-48 animate-pulse rounded-xl bg-surface-raised" /></div></section>;
}
