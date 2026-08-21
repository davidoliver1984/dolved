import Link from "next/link";
import type { ButtonHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

export function CitationChip({ href, label, provisional = false }: Readonly<{ href?: string; label: string; provisional?: boolean }>) {
  const className = cn("inline-flex min-h-8 min-w-8 items-center justify-center rounded-md border px-2 text-xs font-bold outline-none focus-visible:ring-2 focus-visible:ring-ring", provisional ? "border-dashed border-foreground-faint text-foreground-muted" : "border-brand/50 bg-brand/10 text-brand");
  return href ? <Link aria-label={`${label}, opens source`} className={className} href={href}>{label}</Link> : <span aria-label={provisional ? `${label}, provisional citation` : label} className={className}>{label}</span>;
}

export function CitationButton({ className, ...props }: ButtonHTMLAttributes<HTMLButtonElement>) {
  return <button className={cn("inline-flex min-h-8 min-w-8 items-center justify-center rounded-md border border-brand/50 bg-brand/10 px-2 text-xs font-bold text-brand outline-none focus-visible:ring-2 focus-visible:ring-ring", className)} type="button" {...props} />;
}
