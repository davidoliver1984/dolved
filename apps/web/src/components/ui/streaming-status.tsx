import { Sparkles } from "lucide-react";

export function StreamingStatus({ label }: Readonly<{ label: string }>) {
  return <div aria-live="polite" className="flex items-center gap-2 text-sm text-foreground-muted" role="status"><Sparkles aria-hidden="true" className="size-4 animate-pulse text-brand motion-reduce:animate-none" /><span>{label}</span></div>;
}
