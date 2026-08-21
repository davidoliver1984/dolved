"use client";

import * as DialogPrimitive from "@radix-ui/react-dialog";
import {
  Activity,
  ChevronLeft,
  ChevronRight,
  FileText,
  Menu,
  MessageSquarePlus,
  Settings2,
  ShieldCheck,
  Users,
  X,
} from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import type { User, Workspace } from "@/lib/api";
import { listConversations, type Conversation } from "@/lib/conversations";
import { cn } from "@/lib/utils";
import { LogoutButton } from "@/components/LogoutButton";
import { ThemeToggle } from "@/components/ThemeToggle";
import { Button } from "@/components/ui/button";
import { Wordmark } from "@/components/Wordmark";

type AppShellProps = Readonly<{
  canOperatePlatform: boolean;
  children: React.ReactNode;
  user: User;
  workspaces: Workspace[];
}>;

function workspaceIdFromPath(pathname: string) {
  return pathname.match(/^\/app\/workspaces\/([^/]+)/)?.[1] ?? null;
}

function activeClass(active: boolean) {
  return cn(
    "flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold outline-none transition focus-visible:ring-2 focus-visible:ring-sidebar-ring",
    active
      ? "bg-sidebar-accent text-sidebar-accent-foreground"
      : "text-foreground-muted hover:bg-sidebar-accent hover:text-foreground",
  );
}

export function AppShell({ canOperatePlatform, children, user, workspaces }: AppShellProps) {
  const pathname = usePathname();
  const router = useRouter();
  const [collapsed, setCollapsed] = useState(false);
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const workspaceId = workspaceIdFromPath(pathname);
  const workspace = useMemo(
    () => workspaces.find((item) => item.public_id === workspaceId) ?? null,
    [workspaceId, workspaces],
  );
  const isAdministration = pathname.includes("/administration");

  useEffect(() => {
    let active = true;
    if (!workspaceId) {
      return;
    }
    void listConversations(workspaceId)
      .then((items) => active && setConversations(items))
      .catch(() => active && setConversations([]));
    return () => { active = false; };
  }, [workspaceId]);

  const navigation = workspaceId ? (
    <>
      <Link className="flex min-h-11 items-center gap-3 rounded-lg bg-sidebar-primary px-3 text-sm font-bold text-sidebar-primary-foreground outline-none hover:bg-sidebar-primary/90 focus-visible:ring-2 focus-visible:ring-sidebar-ring" href={`/app/workspaces/${workspaceId}`}>
        <MessageSquarePlus aria-hidden="true" className="size-5 shrink-0" />
        {!collapsed ? <span>New conversation</span> : null}
      </Link>
      <Link aria-current={pathname.includes("/documents") ? "page" : undefined} className={activeClass(pathname.includes("/documents"))} href={`/app/workspaces/${workspaceId}/documents`}>
        <FileText aria-hidden="true" className="size-5 shrink-0" />
        {!collapsed ? <span>Documents</span> : null}
      </Link>
      {workspace?.role !== "member" ? (
        <Link aria-current={isAdministration ? "page" : undefined} className={activeClass(isAdministration)} href={`/app/workspaces/${workspaceId}/administration`}>
          <Settings2 aria-hidden="true" className="size-5 shrink-0" />
          {!collapsed ? <span>Administration</span> : null}
        </Link>
      ) : null}
    </>
  ) : null;

  const contextual = workspaceId && !collapsed ? (
    <div className="min-h-0 flex-1 overflow-y-auto border-t border-sidebar-border pt-4">
      <p className="px-3 text-xs font-bold uppercase tracking-[0.14em] text-foreground-faint">
        {isAdministration ? "Administration" : "Recent"}
      </p>
      <nav aria-label={isAdministration ? "Workspace administration" : "Recent conversations"} className="mt-2 grid gap-1">
        {isAdministration ? (
          <>
            <Link className={activeClass(pathname.endsWith("/administration"))} href={`/app/workspaces/${workspaceId}/administration`}><ShieldCheck aria-hidden="true" className="size-4" />Overview</Link>
            <Link className={activeClass(pathname.endsWith("/people"))} href={`/app/workspaces/${workspaceId}/administration/people`}><Users aria-hidden="true" className="size-4" />People &amp; roles</Link>
            <Link className={activeClass(pathname.endsWith("/invitations"))} href={`/app/workspaces/${workspaceId}/administration/invitations`}>Invitations</Link>
            <Link className={activeClass(pathname.endsWith("/usage"))} href={`/app/workspaces/${workspaceId}/administration/usage`}><Activity aria-hidden="true" className="size-4" />Usage</Link>
            <Link className="mt-2 px-3 py-2 text-sm text-foreground-muted underline-offset-4 hover:text-foreground hover:underline" href={`/app/workspaces/${workspaceId}`}>Back to chat</Link>
          </>
        ) : conversations.length ? conversations.slice(0, 12).map((conversation) => {
          const href = `/app/workspaces/${workspaceId}/conversations/${conversation.id}`;
          const active = pathname === href;
          return <Link aria-current={active ? "page" : undefined} className={cn(activeClass(active), "block truncate font-normal")} href={href} key={conversation.id}>{conversation.title || "Untitled conversation"}</Link>;
        }) : <p className="px-3 py-3 text-sm text-foreground-faint">No conversations yet.</p>}
      </nav>
    </div>
  ) : <div className="flex-1" />;

  const sidebar = (
    <div className="flex h-full min-h-0 flex-col gap-4">
      <div className="flex items-center justify-between gap-2 px-1">
        <Wordmark href="/app" />
        {!collapsed ? <Button aria-label="Collapse sidebar" onClick={() => setCollapsed(true)} size="icon" variant="ghost"><ChevronLeft aria-hidden="true" /></Button> : null}
      </div>
      {workspaceId && workspaces.length ? (
        <label className={cn("grid gap-1.5", collapsed && "sr-only")}>
          <span className="text-xs font-bold uppercase tracking-[0.14em] text-foreground-faint">Workspace</span>
          <select className="min-h-11 rounded-lg border border-sidebar-border bg-sidebar px-3 text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring" onChange={(event) => router.push(`/app/workspaces/${event.target.value}`)} value={workspaceId}>
            {workspaces.map((item) => <option key={item.public_id} value={item.public_id}>{item.name}</option>)}
          </select>
        </label>
      ) : null}
      <nav aria-label="Primary" className="grid gap-1">{navigation}{canOperatePlatform ? <Link aria-current={pathname.startsWith("/app/platform/operations") ? "page" : undefined} className={cn(activeClass(pathname.startsWith("/app/platform/operations")), "mt-2 border border-sidebar-border")} href="/app/platform/operations"><Activity aria-hidden="true" className="size-5 shrink-0" />{!collapsed ? <span>Platform operations</span> : null}</Link> : null}</nav>
      {contextual}
      <div className="border-t border-sidebar-border pt-3">
        <div className={cn("flex items-center gap-3", collapsed && "justify-center")}> <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-full bg-surface-raised text-sm font-bold">{user.name.slice(0, 1).toUpperCase()}</span>{!collapsed ? <div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold">{user.name}</p><p className="truncate text-xs text-foreground-muted">{user.email}</p></div> : null}<ThemeToggle /></div>
        {!collapsed ? <div className="mt-2"><LogoutButton /></div> : null}
      </div>
    </div>
  );

  return (
    <div className="min-h-dvh bg-background text-foreground">
      <aside className={cn("fixed inset-y-0 left-0 z-30 hidden border-r border-sidebar-border bg-sidebar p-4 lg:block", collapsed ? "w-20" : "w-72")}>{sidebar}{collapsed ? <Button aria-label="Expand sidebar" className="absolute -right-5 top-5 border border-border bg-surface-raised" onClick={() => setCollapsed(false)} size="icon" variant="ghost"><ChevronRight aria-hidden="true" /></Button> : null}</aside>
      <DialogPrimitive.Root>
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-border bg-background/90 px-4 backdrop-blur lg:hidden"><Wordmark href="/app" /><DialogPrimitive.Trigger asChild><Button aria-label="Open navigation" size="icon" variant="outline"><Menu aria-hidden="true" /></Button></DialogPrimitive.Trigger></header>
        <DialogPrimitive.Portal><DialogPrimitive.Overlay className="fixed inset-0 z-40 bg-overlay lg:hidden" /><DialogPrimitive.Content className="fixed inset-y-0 left-0 z-50 w-[min(88vw,20rem)] border-r border-sidebar-border bg-sidebar p-4 outline-none lg:hidden"><DialogPrimitive.Title className="sr-only">Application navigation</DialogPrimitive.Title>{sidebar}<DialogPrimitive.Close aria-label="Close navigation" className="absolute right-3 top-3 grid size-11 place-items-center rounded-md hover:bg-sidebar-accent focus-visible:ring-2 focus-visible:ring-sidebar-ring"><X aria-hidden="true" /></DialogPrimitive.Close></DialogPrimitive.Content></DialogPrimitive.Portal>
      </DialogPrimitive.Root>
      <main className={cn("min-h-dvh transition-[padding]", collapsed ? "lg:pl-20" : "lg:pl-72")}><div className="mx-auto w-full max-w-[96rem] p-4 sm:p-6 lg:p-8">{children}</div></main>
    </div>
  );
}
