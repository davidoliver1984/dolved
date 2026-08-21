import Link from "next/link";
import { Button } from "@/components/ui/button";

export default function PlatformOperationsNotFound() {
  return <section className="grid max-w-2xl gap-5"><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Not found</p><h1 className="text-4xl font-semibold tracking-tight">This page is unavailable.</h1><p className="text-foreground-muted">The requested destination could not be found.</p><Button asChild className="w-fit" variant="outline"><Link href="/app">Back to Dolved</Link></Button></section>;
}
