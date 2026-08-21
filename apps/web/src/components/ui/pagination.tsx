import { ChevronLeft, ChevronRight } from "lucide-react";
import Link from "next/link";
import { cn } from "@/lib/utils";

export function Pagination({ nextHref, page, previousHref }: Readonly<{ nextHref?: string; page: number; previousHref?: string }>) {
  const linkClass = "inline-flex min-h-11 items-center gap-2 rounded-md border border-border px-3 text-sm font-semibold hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring";
  return <nav aria-label="Pagination" className="flex items-center justify-between gap-4"><span className={cn(linkClass, !previousHref && "pointer-events-none opacity-50")}>{previousHref ? <Link className="flex items-center gap-2" href={previousHref}><ChevronLeft aria-hidden="true" className="size-4" />Previous</Link> : <><ChevronLeft aria-hidden="true" className="size-4" />Previous</>}</span><span className="text-sm text-foreground-muted">Page {page}</span><span className={cn(linkClass, !nextHref && "pointer-events-none opacity-50")}>{nextHref ? <Link className="flex items-center gap-2" href={nextHref}>Next<ChevronRight aria-hidden="true" className="size-4" /></Link> : <>Next<ChevronRight aria-hidden="true" className="size-4" /></>}</span></nav>;
}
