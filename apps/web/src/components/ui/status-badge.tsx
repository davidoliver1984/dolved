import {
  AlertTriangle,
  CheckCircle2,
  Clock3,
  HelpCircle,
  Info,
  XCircle,
} from "lucide-react";
import type { HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

export type StatusTone = "success" | "warning" | "destructive" | "info" | "pending" | "unavailable";

const styles: Record<StatusTone, string> = {
  success: "bg-status-success text-status-success-foreground",
  warning: "bg-status-warning text-status-warning-foreground",
  destructive: "bg-status-destructive text-status-destructive-foreground",
  info: "bg-status-info text-status-info-foreground",
  pending: "bg-status-pending text-status-pending-foreground",
  unavailable: "bg-status-unavailable text-status-unavailable-foreground",
};

const icons = {
  success: CheckCircle2,
  warning: AlertTriangle,
  destructive: XCircle,
  info: Info,
  pending: Clock3,
  unavailable: HelpCircle,
};

export function StatusBadge({ className, status, ...props }: HTMLAttributes<HTMLSpanElement> & { status: StatusTone }) {
  const Icon = icons[status];
  return (
    <span className={cn("inline-flex min-h-6 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold", styles[status], className)} data-slot="status-badge" {...props}>
      <Icon aria-hidden="true" className="size-3.5" />
      {props.children}
    </span>
  );
}
