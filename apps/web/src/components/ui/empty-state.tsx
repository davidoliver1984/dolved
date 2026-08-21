import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

export function EmptyState({ action, className, description, icon: Icon, title }: Readonly<{ action?: ReactNode; className?: string; description: string; icon: LucideIcon; title: string }>) {
  return (
    <section className={cn("flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-surface px-6 py-10 text-center", className)} data-slot="empty-state">
      <span className="mb-4 grid size-12 place-items-center rounded-full bg-surface-raised text-foreground-muted"><Icon aria-hidden="true" /></span>
      <h2 className="text-lg font-semibold">{title}</h2>
      <p className="mt-2 max-w-md text-sm text-foreground-muted">{description}</p>
      {action ? <div className="mt-5">{action}</div> : null}
    </section>
  );
}
