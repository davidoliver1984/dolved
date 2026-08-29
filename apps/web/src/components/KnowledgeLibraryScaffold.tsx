import type { LucideIcon } from "lucide-react";
import { EmptyState } from "@/components/ui/empty-state";

export function KnowledgeLibraryScaffold({
  description,
  emptyDescription,
  emptyTitle,
  icon,
  title,
}: Readonly<{
  description: string;
  emptyDescription: string;
  emptyTitle: string;
  icon: LucideIcon;
  title: string;
}>) {
  return (
    <div className="grid gap-6">
      <header>
        <p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">Knowledge library</p>
        <h1 className="mt-2 text-3xl font-semibold">{title}</h1>
        <p className="mt-2 max-w-2xl text-foreground-muted">{description}</p>
      </header>
      <EmptyState description={emptyDescription} icon={icon} title={emptyTitle} />
    </div>
  );
}
