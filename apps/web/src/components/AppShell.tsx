"use client";

import * as DialogPrimitive from "@radix-ui/react-dialog";
import {
  Activity,
  Archive,
  CalendarClock,
  ChevronLeft,
  ChevronRight,
  CircleAlert,
  FileText,
  FileUp,
  FolderOpen,
  Gauge,
  BellRing,
  LineChart,
  ListChecks,
  MailPlus,
  Menu,
  MessageSquarePlus,
  Settings2,
  ShieldCheck,
  SlidersHorizontal,
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
import { GovernanceInbox } from "@/components/GovernanceInbox";
import { ThemeToggle } from "@/components/ThemeToggle";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
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

function workspaceSection(pathname: string, workspaceId: string | null, section: string) {
  if (!workspaceId) return false;
  const base = `/app/workspaces/${workspaceId}/${section}`;
  return pathname === base || pathname.startsWith(`${base}/`);
}

type DocumentsDestination = "attention" | "bulk" | "categories" | "deleted" | "imports" | "library" | "saved" | "scheduled";

function documentsDestination(pathname: string, workspaceId: string | null): DocumentsDestination | null {
  if (!workspaceSection(pathname, workspaceId, "documents")) return null;
  const base = `/app/workspaces/${workspaceId}/documents`;
  const remainder = pathname.slice(base.length).split("/").filter(Boolean);
  if (remainder[0] === "scheduled") return "scheduled";
  if (remainder[0] === "attention") return "attention";
  if (remainder[0] === "deleted") return "deleted";
  if (remainder[0] === "imports") return "imports";
  if (remainder[0] === "bulk") return "bulk";
  if (remainder[0] === "saved") return "saved";
  if (remainder[0] === "settings" && remainder[1] === "categories") return "categories";
  return "library";
}

function activeClass(active: boolean) {
  return cn(
    "flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold outline-none transition focus-visible:ring-2 focus-visible:ring-sidebar-ring",
    active
      ? "bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90 [&_span]:text-sidebar-primary-foreground [&_svg]:text-sidebar-primary-foreground"
      : "text-foreground-muted hover:bg-sidebar-accent hover:text-foreground",
  );
}

export function AppShell({ canOperatePlatform, children, user, workspaces }: AppShellProps) {
  const pathname = usePathname();
  const router = useRouter();
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const workspaceId = workspaceIdFromPath(pathname);
  const workspace = useMemo(
    () => workspaces.find((item) => item.public_id === workspaceId) ?? null,
    [workspaceId, workspaces],
  );
  const isAdministration = workspaceSection(pathname, workspaceId, "administration");
  const isPlatformOperations = pathname.startsWith("/app/platform/operations");
  const activeDocumentsDestination = documentsDestination(pathname, workspaceId);
  const isDocuments = activeDocumentsDestination !== null;
  const workspaceHome = `/app/workspaces/${workspaceId}`;
  const closeMobileNavigation = () => setMobileOpen(false);
  const sidebarLabel = (label: string) => (
    <span className={cn("min-w-0 truncate", collapsed && "lg:hidden")}>{label}</span>
  );

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
      <Link aria-current={pathname === workspaceHome ? "page" : undefined} className={activeClass(pathname === workspaceHome)} href={workspaceHome} onClick={closeMobileNavigation}>
        <MessageSquarePlus aria-hidden="true" className="size-5 shrink-0 text-current" />
        {sidebarLabel("New conversation")}
      </Link>
      <Link aria-current={isDocuments ? "page" : undefined} className={activeClass(isDocuments)} href={`/app/workspaces/${workspaceId}/documents`} onClick={closeMobileNavigation}>
        <FileText aria-hidden="true" className="size-5 shrink-0" />
        {sidebarLabel("Documents")}
      </Link>
      {workspace?.role !== "member" ? (
        <Link className={activeClass(false)} href={`/app/workspaces/${workspaceId}/administration`} onClick={closeMobileNavigation}>
          <Settings2 aria-hidden="true" className="size-5 shrink-0" />
          {sidebarLabel("Administration")}
        </Link>
      ) : null}
    </>
  ) : null;

  const contextual = isPlatformOperations ? (
    <div className={cn("min-h-0 flex-1 overflow-y-auto border-t border-sidebar-border pt-4", collapsed && "lg:hidden")}>
      <p className="px-3 text-xs font-bold uppercase tracking-[0.14em] text-foreground-faint">Platform operations</p>
      <nav aria-label="Platform operations" className="mt-2 grid gap-1">
        <Link aria-current={pathname === "/app/platform/operations" ? "page" : undefined} className={activeClass(pathname === "/app/platform/operations")} href="/app/platform/operations" onClick={closeMobileNavigation}><Gauge aria-hidden="true" className="size-4" /><span>Overview</span></Link>
        <Link aria-current={pathname.endsWith("/alerts") ? "page" : undefined} className={activeClass(pathname.endsWith("/alerts"))} href="/app/platform/operations/alerts" onClick={closeMobileNavigation}><BellRing aria-hidden="true" className="size-4" /><span>Active alerts</span></Link>
        <Link aria-current={pathname.endsWith("/telemetry") ? "page" : undefined} className={activeClass(pathname.endsWith("/telemetry"))} href="/app/platform/operations/telemetry" onClick={closeMobileNavigation}><LineChart aria-hidden="true" className="size-4" /><span>Global telemetry</span></Link>
        <Link aria-current={pathname.endsWith("/policy") ? "page" : undefined} className={activeClass(pathname.endsWith("/policy"))} href="/app/platform/operations/policy" onClick={closeMobileNavigation}><SlidersHorizontal aria-hidden="true" className="size-4" /><span>Operational policy</span></Link>
        <Link className="mt-2 px-3 py-2 text-sm text-foreground-muted underline-offset-4 hover:text-foreground hover:underline" href="/app" onClick={closeMobileNavigation}>Back to chat</Link>
      </nav>
    </div>
  ) : isDocuments && workspaceId ? (
    <div className={cn("min-h-0 flex-1 overflow-y-auto border-t border-sidebar-border pt-4", collapsed && "lg:hidden")}>
      <p className="px-3 text-xs font-bold uppercase tracking-[0.14em] text-foreground-faint">Knowledge library</p>
      <nav aria-label="Knowledge library" className="mt-2 grid gap-1">
        <Link aria-current={activeDocumentsDestination === "library" ? "page" : undefined} className={activeClass(activeDocumentsDestination === "library")} href={`/app/workspaces/${workspaceId}/documents`} onClick={closeMobileNavigation}><FolderOpen aria-hidden="true" className="size-4" /><span>Library</span></Link>
        <Link aria-current={activeDocumentsDestination === "imports" ? "page" : undefined} className={activeClass(activeDocumentsDestination === "imports")} href={`/app/workspaces/${workspaceId}/documents/imports`} onClick={closeMobileNavigation}><FileUp aria-hidden="true" className="size-4" /><span>Import documents</span></Link>
        <Link aria-current={activeDocumentsDestination === "scheduled" ? "page" : undefined} className={activeClass(activeDocumentsDestination === "scheduled")} href={`/app/workspaces/${workspaceId}/documents/scheduled`} onClick={closeMobileNavigation}><CalendarClock aria-hidden="true" className="size-4" /><span>Scheduled</span></Link>
        <Link aria-current={activeDocumentsDestination === "attention" ? "page" : undefined} className={activeClass(activeDocumentsDestination === "attention")} href={`/app/workspaces/${workspaceId}/documents/attention`} onClick={closeMobileNavigation}><CircleAlert aria-hidden="true" className="size-4" /><span>Needs attention</span></Link>
        {workspace?.role !== "member" ? <Link aria-current={activeDocumentsDestination === "bulk" ? "page" : undefined} className={activeClass(activeDocumentsDestination === "bulk")} href={`/app/workspaces/${workspaceId}/documents/bulk`} onClick={closeMobileNavigation}><ListChecks aria-hidden="true" className="size-4" /><span>Bulk history</span></Link> : null}
        {workspace?.role !== "member" ? <Link aria-current={activeDocumentsDestination === "deleted" ? "page" : undefined} className={activeClass(activeDocumentsDestination === "deleted")} href={`/app/workspaces/${workspaceId}/documents/deleted`} onClick={closeMobileNavigation}><Archive aria-hidden="true" className="size-4" /><span>Deleted history</span></Link> : null}
        <Link aria-current={activeDocumentsDestination === "categories" ? "page" : undefined} className={activeClass(activeDocumentsDestination === "categories")} href={`/app/workspaces/${workspaceId}/documents/settings/categories`} onClick={closeMobileNavigation}><Settings2 aria-hidden="true" className="size-4" /><span>Categories</span></Link>
        <Link className="mt-2 px-3 py-2 text-sm text-foreground-muted underline-offset-4 hover:text-foreground hover:underline" href={`/app/workspaces/${workspaceId}`} onClick={closeMobileNavigation}>Back to chat</Link>
      </nav>
    </div>
  ) : workspaceId ? (
    <div className={cn("min-h-0 flex-1 overflow-y-auto border-t border-sidebar-border pt-4", collapsed && "lg:hidden")}>
      <p className="px-3 text-xs font-bold uppercase tracking-[0.14em] text-foreground-faint">
        {isAdministration ? "Administration" : "Recent"}
      </p>
      <nav aria-label={isAdministration ? "Workspace administration" : "Recent conversations"} className="mt-2 grid gap-1">
        {isAdministration ? (
          <>
            <Link aria-current={pathname.endsWith("/administration") ? "page" : undefined} className={activeClass(pathname.endsWith("/administration"))} href={`/app/workspaces/${workspaceId}/administration`} onClick={closeMobileNavigation}><ShieldCheck aria-hidden="true" className="size-4" /><span>Overview</span></Link>
            <Link aria-current={pathname.endsWith("/people") ? "page" : undefined} className={activeClass(pathname.endsWith("/people"))} href={`/app/workspaces/${workspaceId}/administration/people`} onClick={closeMobileNavigation}><Users aria-hidden="true" className="size-4" /><span>People &amp; roles</span></Link>
            <Link aria-current={pathname.endsWith("/invitations") ? "page" : undefined} className={activeClass(pathname.endsWith("/invitations"))} href={`/app/workspaces/${workspaceId}/administration/invitations`} onClick={closeMobileNavigation}><MailPlus aria-hidden="true" className="size-4" /><span>Invitations</span></Link>
            <Link aria-current={pathname.endsWith("/usage") ? "page" : undefined} className={activeClass(pathname.endsWith("/usage"))} href={`/app/workspaces/${workspaceId}/administration/usage`} onClick={closeMobileNavigation}><Activity aria-hidden="true" className="size-4" /><span>Usage</span></Link>
            <Link className="mt-2 px-3 py-2 text-sm text-foreground-muted underline-offset-4 hover:text-foreground hover:underline" href={`/app/workspaces/${workspaceId}`} onClick={closeMobileNavigation}>Back to chat</Link>
          </>
        ) : conversations.length ? conversations.slice(0, 12).map((conversation) => {
          const href = `/app/workspaces/${workspaceId}/conversations/${conversation.id}`;
          const active = pathname === href;
          return <Link aria-current={active ? "page" : undefined} className={cn(activeClass(active), "py-2.5 font-normal leading-5")} href={href} key={conversation.id} onClick={closeMobileNavigation}><span className="min-w-0 truncate">{conversation.title || "Untitled conversation"}</span></Link>;
        }) : <p className="px-3 py-3 text-sm text-foreground-faint">No conversations yet.</p>}
      </nav>
    </div>
  ) : <div className="flex-1" />;

  const sidebar = (
    <div className="flex h-full min-h-0 flex-col gap-4">
      <div aria-hidden="true" className="h-11 shrink-0 lg:hidden" />
      <div className="hidden items-center justify-between gap-2 px-1 lg:flex">
        {!collapsed ? <Wordmark className="text-[1.7rem]" href="/app" /> : null}
        {!collapsed ? <Button aria-label="Collapse sidebar" onClick={() => setCollapsed(true)} size="icon" variant="ghost"><ChevronLeft aria-hidden="true" /></Button> : null}
      </div>
      {workspaceId && workspaces.length ? (
        <label className={cn("grid gap-1.5", collapsed && "lg:hidden")}>
          <span className="text-xs font-bold uppercase tracking-[0.14em] text-foreground-faint">Workspace</span>
          <select className="min-h-11 rounded-lg border border-sidebar-border bg-sidebar px-3 text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring" onChange={(event) => router.push(`/app/workspaces/${event.target.value}`)} value={workspaceId}>
            {workspaces.map((item) => <option key={item.public_id} value={item.public_id}>{item.name}</option>)}
          </select>
        </label>
      ) : null}
      <nav aria-label="Primary" className="grid gap-1">{navigation}{canOperatePlatform ? <Link aria-current={pathname.startsWith("/app/platform/operations") ? "page" : undefined} className={cn(activeClass(pathname.startsWith("/app/platform/operations")), "mt-2 border border-sidebar-border")} href="/app/platform/operations" onClick={closeMobileNavigation}><Activity aria-hidden="true" className="size-5 shrink-0" />{sidebarLabel("Platform operations")}</Link> : null}</nav>
      {contextual}
      <div className="border-t border-sidebar-border pt-3">
        <div className={cn("flex items-center gap-2", collapsed && "lg:justify-center")}>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button aria-label={`Account menu for ${user.name}`} className={cn("flex min-w-0 flex-1 items-center gap-3 rounded-lg p-1 text-left outline-none hover:bg-sidebar-accent focus-visible:ring-2 focus-visible:ring-sidebar-ring", collapsed && "lg:flex-none")} type="button">
                <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-full bg-surface-raised text-sm font-bold">{user.name.slice(0, 1).toUpperCase()}</span>
                <span className={cn("min-w-0 flex-1", collapsed && "lg:hidden")}><span className="block truncate text-sm font-semibold">{user.name}</span><span className="block truncate text-xs text-foreground-muted">{user.email}</span></span>
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" side="top">
              <DropdownMenuLabel>Account</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem asChild><LogoutButton className="w-full text-left" /></DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
          <GovernanceInbox workspacePublicId={workspaceId} />
          <ThemeToggle />
        </div>
      </div>
    </div>
  );

  return (
    <div className="min-h-dvh bg-background text-foreground">
      <aside className={cn("fixed inset-y-0 left-0 z-30 hidden border-r border-sidebar-border bg-sidebar p-4 lg:block", collapsed ? "w-20" : "w-72")}>{sidebar}{collapsed ? <Button aria-label="Expand sidebar" className="absolute -right-5 top-5 border border-border bg-surface-raised" onClick={() => setCollapsed(false)} size="icon" variant="ghost"><ChevronRight aria-hidden="true" /></Button> : null}</aside>
      <DialogPrimitive.Root onOpenChange={setMobileOpen} open={mobileOpen}>
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-border bg-background/90 px-4 backdrop-blur lg:hidden"><Wordmark href="/app" /><DialogPrimitive.Trigger asChild><Button aria-label="Open navigation" size="icon" variant="outline"><Menu aria-hidden="true" /></Button></DialogPrimitive.Trigger></header>
        <DialogPrimitive.Portal><DialogPrimitive.Overlay className="fixed inset-0 z-40 bg-overlay lg:hidden" /><DialogPrimitive.Content className="fixed inset-y-0 left-0 z-50 w-[min(88vw,20rem)] border-r border-sidebar-border bg-sidebar p-4 outline-none lg:hidden"><DialogPrimitive.Title className="sr-only">Application navigation</DialogPrimitive.Title>{sidebar}<DialogPrimitive.Close aria-label="Close navigation" className="absolute right-3 top-3 grid size-11 place-items-center rounded-md hover:bg-sidebar-accent focus-visible:ring-2 focus-visible:ring-sidebar-ring"><X aria-hidden="true" /></DialogPrimitive.Close></DialogPrimitive.Content></DialogPrimitive.Portal>
      </DialogPrimitive.Root>
      <main className={cn("min-h-[calc(100dvh-4rem)] transition-[padding] lg:min-h-dvh", collapsed ? "lg:pl-20" : "lg:pl-72")}><div className="mx-auto w-full max-w-[96rem] p-4 sm:p-6 lg:p-8">{children}</div></main>
    </div>
  );
}
