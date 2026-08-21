import { LoaderCircle } from "lucide-react";
import { cn } from "@/lib/utils";

export function Spinner({ className, label = "Loading" }: Readonly<{ className?: string; label?: string }>) {
  return (
    <span className={cn("inline-flex items-center gap-2 text-sm text-foreground-muted", className)} role="status">
      <LoaderCircle aria-hidden="true" className="size-4 animate-spin motion-reduce:animate-none" />
      <span>{label}</span>
    </span>
  );
}
