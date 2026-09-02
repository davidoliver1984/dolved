"use client";

import * as DialogPrimitive from "@radix-ui/react-dialog";
import { Bell, BellRing, CircleAlert, Info, X } from "lucide-react";
import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  dismissGovernanceNotification,
  governanceNotifications,
  markGovernanceNotificationRead,
  type GovernanceNotification,
} from "@/lib/api";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

export function GovernanceInbox({ workspacePublicId }: Readonly<{ workspacePublicId: string | null }>) {
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<GovernanceNotification[]>([]);
  const [unread, setUnread] = useState(0);
  const [state, setState] = useState<"idle" | "loading" | "ready" | "error">("idle");
  const itemRefs = useRef<Array<HTMLAnchorElement | HTMLButtonElement | null>>([]);

  const load = useCallback(async () => {
    if (!workspacePublicId) return;
    setState("loading");
    try {
      const page = await governanceNotifications(workspacePublicId);
      setItems(page.data);
      setUnread(page.meta.unread_count);
      setState("ready");
    } catch {
      setState("error");
    }
  }, [workspacePublicId]);

  useEffect(() => {
    if (!workspacePublicId) return;
    let active = true;
    void governanceNotifications(workspacePublicId)
      .then((page) => {
        if (!active) return;
        setItems(page.data);
        setUnread(page.meta.unread_count);
        setState("ready");
      })
      .catch(() => { if (active) setState("error"); });
    return () => { active = false; };
  }, [workspacePublicId]);

  const read = async (notification: GovernanceNotification) => {
    if (!workspacePublicId || notification.read_at) return;
    await markGovernanceNotificationRead(workspacePublicId, notification.public_id);
    setItems((current) => current.map((item) => item.public_id === notification.public_id ? { ...item, read_at: new Date().toISOString() } : item));
    setUnread((current) => Math.max(0, current - 1));
  };

  const dismiss = async (notification: GovernanceNotification) => {
    if (!workspacePublicId) return;
    await dismissGovernanceNotification(workspacePublicId, notification.public_id);
    setItems((current) => current.filter((item) => item.public_id !== notification.public_id));
    if (!notification.read_at) setUnread((current) => Math.max(0, current - 1));
  };

  const moveFocus = (event: React.KeyboardEvent, index: number) => {
    if (event.key !== "ArrowDown" && event.key !== "ArrowUp") return;
    event.preventDefault();
    const next = event.key === "ArrowDown" ? Math.min(items.length - 1, index + 1) : Math.max(0, index - 1);
    itemRefs.current[next]?.focus();
  };

  if (!workspacePublicId) return null;

  return (
    <DialogPrimitive.Root onOpenChange={(value) => { setOpen(value); if (value) void load(); }} open={open}>
      <DialogPrimitive.Trigger asChild>
        <Button aria-label={unread === 1 ? "1 unread notification" : `${unread} unread notifications`} className="relative" size="icon" variant="ghost">
          <Bell aria-hidden="true" />
          {unread > 0 ? <span aria-hidden="true" className="absolute -right-1 -top-1 min-w-5 rounded-full bg-brand px-1 text-center text-[0.65rem] font-bold leading-5 text-brand-foreground">{unread > 99 ? "99+" : unread}</span> : null}
        </Button>
      </DialogPrimitive.Trigger>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-40 bg-overlay" />
        <DialogPrimitive.Content aria-describedby="governance-inbox-description" className="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l border-border bg-background p-5 shadow-xl outline-none sm:p-6">
          <div className="flex items-start justify-between gap-4 border-b border-border pb-4">
            <div>
              <DialogPrimitive.Title className="text-xl font-bold">Notifications</DialogPrimitive.Title>
              <DialogPrimitive.Description className="mt-1 text-sm text-foreground-muted" id="governance-inbox-description">Document updates and governance reminders for this workspace.</DialogPrimitive.Description>
            </div>
            <DialogPrimitive.Close asChild><Button aria-label="Close notifications" size="icon" variant="ghost"><X aria-hidden="true" /></Button></DialogPrimitive.Close>
          </div>
          <div aria-live="polite" className="sr-only">{state === "ready" ? `${items.length} notifications loaded` : ""}</div>
          <div className="min-h-0 flex-1 overflow-y-auto py-4" role="log">
            {state === "loading" ? <div className="grid gap-3" data-testid="governance-inbox-loading"><Skeleton className="h-24" /><Skeleton className="h-24" /><Skeleton className="h-24" /></div> : null}
            {state === "error" ? <div className="rounded-xl border border-warning/40 bg-warning/10 p-4"><p className="font-semibold">Some notifications could not be loaded.</p><Button className="mt-3" onClick={() => void load()} size="sm" variant="outline">Try again</Button></div> : null}
            {state === "ready" && items.length === 0 ? <EmptyState description="Document updates and reminders will appear here." icon={BellRing} title="You’re all caught up" /> : null}
            {state === "ready" && items.length > 0 ? <ul className="grid gap-3">{items.map((notification, index) => {
              const Icon = notification.severity === "action_required" ? CircleAlert : notification.severity === "warning" ? CircleAlert : Info;
              const content = <><span className={cn("mt-0.5 grid size-9 shrink-0 place-items-center rounded-full", notification.severity === "action_required" ? "bg-brand text-brand-foreground" : notification.severity === "warning" ? "bg-warning/15 text-warning" : "bg-surface-raised text-foreground-muted")}><Icon aria-hidden="true" className="size-4" /></span><span className="min-w-0 flex-1"><span className="block font-semibold">{notification.title}</span><span className="mt-1 block text-sm leading-5 text-foreground-muted">{notification.message}</span>{notification.created_at ? <time className="mt-2 block text-xs text-foreground-faint" dateTime={notification.created_at}>{new Intl.DateTimeFormat("en-GB", { dateStyle: "medium", timeStyle: "short" }).format(new Date(notification.created_at))}</time> : null}</span></>;
              return <li className={cn("rounded-xl border p-3", notification.read_at ? "border-border bg-surface" : "border-brand/40 bg-brand/5")} key={notification.public_id}>
                {notification.target_route ? <Link className="flex gap-3 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring" href={notification.target_route} onClick={() => void read(notification)} onKeyDown={(event) => moveFocus(event, index)} ref={(node) => { itemRefs.current[index] = node; }}>{content}</Link> : <button className="flex w-full gap-3 rounded-md text-left outline-none focus-visible:ring-2 focus-visible:ring-ring" onClick={() => void read(notification)} onKeyDown={(event) => moveFocus(event, index)} ref={(node) => { itemRefs.current[index] = node; }} type="button">{content}</button>}
                <div className="mt-3 flex justify-end"><Button onClick={() => void dismiss(notification)} size="sm" variant="ghost">Dismiss</Button></div>
              </li>;
            })}</ul> : null}
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  );
}
