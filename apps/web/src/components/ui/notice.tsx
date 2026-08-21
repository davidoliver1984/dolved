import { AlertTriangle, CheckCircle2, Info, XCircle } from "lucide-react";
import type { HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

type NoticeTone = "info" | "success" | "warning" | "destructive";
const styles: Record<NoticeTone, string> = {
  info: "border-status-info/50 bg-status-info/10 text-foreground",
  success: "border-status-success/50 bg-status-success/10 text-foreground",
  warning: "border-status-warning/50 bg-status-warning/10 text-foreground",
  destructive: "border-status-destructive/50 bg-status-destructive/10 text-foreground",
};
const icons = { info: Info, success: CheckCircle2, warning: AlertTriangle, destructive: XCircle };

export function Notice({ className, tone = "info", ...props }: HTMLAttributes<HTMLDivElement> & { tone?: NoticeTone }) {
  const Icon = icons[tone];
  return (
    <div className={cn("flex gap-3 rounded-lg border p-4 text-sm", styles[tone], className)} data-slot="notice" role={tone === "destructive" ? "alert" : "status"} {...props}>
      <Icon aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
      <div>{props.children}</div>
    </div>
  );
}
