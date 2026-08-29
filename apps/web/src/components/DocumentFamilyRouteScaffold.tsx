import { FileStack } from "lucide-react";
import type { DocumentFamilyMetadata } from "@/lib/server-api";
import { EmptyState } from "@/components/ui/empty-state";

export function DocumentFamilyRouteScaffold({
  description,
  emptyDescription,
  family,
  title,
}: Readonly<{
  description: string;
  emptyDescription: string;
  family: DocumentFamilyMetadata;
  title: string;
}>) {
  return (
    <div className="grid gap-6">
      <header>
        <p className="text-sm font-bold uppercase tracking-[0.14em] text-brand">{family.name}</p>
        <h1 className="mt-2 text-3xl font-semibold">{title}</h1>
        <p className="mt-2 max-w-2xl text-foreground-muted">{description}</p>
      </header>
      <EmptyState description={emptyDescription} icon={FileStack} title="This view is ready for its library data" />
    </div>
  );
}
