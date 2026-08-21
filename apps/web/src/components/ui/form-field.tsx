import { cloneElement, isValidElement, type HTMLAttributes, type ReactElement, type ReactNode } from "react";
import { cn } from "@/lib/utils";

export function FormField({ children, className, error, help, id, label }: Readonly<{ children: ReactNode; className?: string; error?: string; help?: string; id: string; label: string }>) {
  const descriptionId = error ? `${id}-error` : help ? `${id}-help` : undefined;
  const control = isValidElement(children)
    ? cloneElement(children as ReactElement<Record<string, unknown>>, {
        "aria-describedby": descriptionId,
        "aria-invalid": Boolean(error),
        id,
      })
    : children;
  return <div className={cn("grid gap-2", className)} data-slot="form-field"><label className="text-sm font-semibold" htmlFor={id}>{label}</label>{control}{error ? <p className="text-sm text-status-destructive" id={`${id}-error`} role="alert">{error}</p> : help ? <p className="text-sm text-foreground-muted" id={`${id}-help`}>{help}</p> : null}</div>;
}

export function FieldGroup({ className, ...props }: HTMLAttributes<HTMLDivElement>) { return <div className={cn("grid gap-4", className)} {...props} />; }
