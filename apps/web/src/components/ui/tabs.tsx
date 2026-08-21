"use client";

import * as TabsPrimitive from "@radix-ui/react-tabs";
import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

export const Tabs = TabsPrimitive.Root;
export function TabsList({ className, ...props }: ComponentProps<typeof TabsPrimitive.List>) { return <TabsPrimitive.List className={cn("inline-flex min-h-11 items-center rounded-lg bg-muted p-1", className)} {...props} />; }
export function TabsTrigger({ className, ...props }: ComponentProps<typeof TabsPrimitive.Trigger>) { return <TabsPrimitive.Trigger className={cn("min-h-9 rounded-md px-3 text-sm font-medium text-foreground-muted outline-none focus-visible:ring-2 focus-visible:ring-ring data-[state=active]:bg-surface-raised data-[state=active]:text-foreground", className)} {...props} />; }
export function TabsContent({ className, ...props }: ComponentProps<typeof TabsPrimitive.Content>) { return <TabsPrimitive.Content className={cn("mt-4 outline-none focus-visible:ring-2 focus-visible:ring-ring", className)} {...props} />; }
