"use client";

import * as TooltipPrimitive from "@radix-ui/react-tooltip";
import { CircleHelp } from "lucide-react";
import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

export const TooltipProvider = TooltipPrimitive.Provider;
export const Tooltip = TooltipPrimitive.Root;
export const TooltipTrigger = TooltipPrimitive.Trigger;
export function TooltipContent({ className, sideOffset = 6, ...props }: ComponentProps<typeof TooltipPrimitive.Content>) {
  return <TooltipPrimitive.Portal><TooltipPrimitive.Content className={cn("z-50 rounded-md bg-foreground px-3 py-1.5 text-xs text-background shadow-lg", className)} sideOffset={sideOffset} {...props} /></TooltipPrimitive.Portal>;
}

export function HelpTip({ label, text }: Readonly<{ label: string; text: string }>) {
  return (
    <TooltipProvider delayDuration={250}>
      <Tooltip>
        <TooltipTrigger asChild>
          <span
            aria-label={`Help: ${label}`}
            className="inline-grid size-6 shrink-0 place-items-center rounded-full text-foreground-muted hover:bg-surface-hover hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            role="img"
            tabIndex={0}
          >
            <CircleHelp aria-hidden="true" className="size-4" />
          </span>
        </TooltipTrigger>
        <TooltipContent className="max-w-72 text-pretty leading-5" side="top">{text}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}
