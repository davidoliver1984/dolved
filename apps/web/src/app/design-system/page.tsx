import { BookOpenText, FileText, Inbox, Send, Sparkles } from "lucide-react";
import { notFound } from "next/navigation";
import { ThemeToggle } from "@/components/ThemeToggle";
import { Wordmark } from "@/components/Wordmark";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { EmptyState } from "@/components/ui/empty-state";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { StatusBadge } from "@/components/ui/status-badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";

export const metadata = { title: "Design system reference", robots: { index: false, follow: false } };

export default function DesignSystemReferencePage() {
  if (process.env.NODE_ENV === "production") notFound();

  return (
    <main className="min-h-dvh bg-background px-4 py-8 text-foreground sm:px-8 lg:px-12">
      <div className="mx-auto max-w-7xl">
        <header className="flex flex-wrap items-start justify-between gap-6 border-b border-border pb-8">
          <div><Wordmark /><p className="mt-4 text-sm font-bold uppercase tracking-[0.16em] text-brand">R21-S01 component reference</p><h1 className="mt-2 max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">A precise interface for grounded work.</h1><p className="mt-4 max-w-2xl text-lg text-foreground-muted">This is a living component catalogue, not a proposed product page. It demonstrates the shared visual language and interaction states that real Dolved screens compose.</p></div>
          <ThemeToggle />
        </header>

        <nav aria-label="Reference sections" className="sticky top-3 z-10 mt-5 flex flex-wrap gap-2 rounded-xl border border-border bg-background/90 p-2 shadow-sm backdrop-blur">
          <Button asChild size="sm" variant="ghost"><a href="#answer-pattern">Answer pattern</a></Button>
          <Button asChild size="sm" variant="ghost"><a href="#controls">Controls</a></Button>
          <Button asChild size="sm" variant="ghost"><a href="#states">States</a></Button>
        </nav>

        <section className="grid gap-5 py-10 lg:grid-cols-[1.25fr_0.75fr]" id="answer-pattern">
          <div className="lg:col-span-2"><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Product patterns</p><h2 className="mt-2 text-2xl font-semibold">Grounded answers and actions</h2><p className="mt-2 max-w-2xl text-foreground-muted">A representative answer surface beside the action and status vocabulary it depends on.</p></div>
          <Card className="overflow-hidden">
            <CardHeader className="border-b border-border"><div className="flex items-center justify-between gap-4"><div><CardTitle>Grounded answer</CardTitle><CardDescription>restrained hierarchy · explicit evidence · no decorative controls</CardDescription></div><StatusBadge status="success">Answer complete</StatusBadge></div></CardHeader>
            <CardContent className="space-y-6 pt-6">
              <div aria-label="Example conversation" className="space-y-4">
                <div className="ml-auto flex max-w-[85%] flex-row-reverse gap-3"><span className="grid size-9 shrink-0 place-items-center rounded-full bg-brand text-xs font-bold text-primary-foreground">DO</span><div className="rounded-2xl rounded-tr-sm bg-brand px-4 py-3 text-sm leading-6 text-primary-foreground"><p className="font-semibold">David</p><p className="mt-1">Can you help me check what staff should do after a medication error?</p></div></div>
                <div className="flex max-w-[85%] gap-3"><span className="grid size-9 shrink-0 place-items-center rounded-full bg-surface-raised text-sm font-bold">D</span><div className="rounded-2xl rounded-tl-sm bg-surface-raised px-4 py-3 text-sm leading-6"><p className="font-semibold">Dolved</p><p className="mt-1 text-foreground-muted">Yes. What happened, and are you asking about the current procedure?</p></div></div>
                <div className="ml-auto flex max-w-[85%] flex-row-reverse gap-3"><span className="grid size-9 shrink-0 place-items-center rounded-full bg-brand text-xs font-bold text-primary-foreground">DO</span><div className="rounded-2xl rounded-tr-sm bg-brand px-4 py-3 text-sm leading-6 text-primary-foreground"><p className="font-semibold">David</p><p className="mt-1">A scheduled dose was missed this morning and staff have just noticed. What should they do now?</p></div></div>
              </div>
              <div className="border-t border-border pt-6">
              <div className="flex gap-3"><span className="grid size-9 shrink-0 place-items-center rounded-full bg-surface-raised text-sm font-bold">D</span><div><p className="text-sm font-semibold">Dolved</p><p className="mt-2 leading-7 text-foreground-muted">The current procedure requires staff to record the omitted dose, assess immediate safety, and escalate according to the medicine policy.<button className="ml-2 rounded bg-surface-raised px-2 py-0.5 text-xs font-bold text-brand outline-none focus-visible:ring-2 focus-visible:ring-ring" type="button">[1]</button></p></div></div>
              </div>
              <div className="rounded-xl border border-border bg-surface-raised p-4" id="reference-source"><div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-lg bg-background"><FileText aria-hidden="true" className="size-5 text-brand" /></span><div className="min-w-0"><p className="font-semibold">Medication administration procedure</p><p className="mt-1 text-sm text-foreground-muted">Policy · 428 KB</p><p className="mt-3 border-l-2 border-brand pl-3 text-sm leading-6 text-foreground-muted">“Record the omission and complete an immediate safety assessment…”</p><Button asChild className="mt-3" variant="link"><a href="#reference-source">View source</a></Button></div></div></div>
              <p className="flex items-center gap-2 text-sm text-foreground-muted"><BookOpenText aria-hidden="true" className="size-4 text-brand" />Grounded in 1 source</p>
              <div className="flex gap-2"><Textarea aria-label="Ask a follow-up" className="min-h-12 resize-none" placeholder="Ask a follow-up…" /><Button aria-label="Send message" size="icon"><Send aria-hidden="true" /></Button></div>
            </CardContent>
          </Card>

          <div className="grid content-start gap-5">
            <Card><CardHeader><CardTitle>Actions</CardTitle><CardDescription>Complete interaction states share one semantic vocabulary.</CardDescription></CardHeader><CardContent className="flex flex-wrap gap-3"><Button>Primary</Button><Button variant="secondary">Secondary</Button><Button variant="outline">Outline</Button><Button disabled>Disabled</Button><Button><Spinner className="text-primary-foreground" label="Working" /></Button></CardContent></Card>
            <Card><CardHeader><CardTitle>Status</CardTitle></CardHeader><CardContent className="flex flex-wrap gap-2"><StatusBadge status="success">Indexed</StatusBadge><StatusBadge status="warning">Needs review</StatusBadge><StatusBadge status="destructive">Failed</StatusBadge><StatusBadge status="info">Information</StatusBadge><StatusBadge status="pending">Processing</StatusBadge><StatusBadge status="unavailable">Unavailable</StatusBadge></CardContent></Card>
          </div>
        </section>

        <section className="grid gap-5 border-t border-border py-10 lg:grid-cols-2" id="controls">
          <div className="lg:col-span-2"><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Interaction foundations</p><h2 className="mt-2 text-2xl font-semibold">Controls and feedback</h2><p className="mt-2 max-w-2xl text-foreground-muted">Form relationships, notices and destructive confirmation use the same accessible primitives throughout the product.</p></div>
          <Card><CardHeader><CardTitle>Form controls</CardTitle><CardDescription>Labels, help and errors remain structurally associated.</CardDescription></CardHeader><CardContent className="grid gap-4"><FormField help="A human-readable name for this workspace." id="workspace-name" label="Workspace name"><Input defaultValue="Alderbridge Care" /></FormField><FormField error="Use an organisational email address." id="email" label="Email address"><Input defaultValue="person@example.test" type="email" /></FormField><label className="grid gap-2 text-sm font-semibold" htmlFor="role">Role<Select defaultValue="owner"><SelectTrigger id="role"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="owner">Owner</SelectItem><SelectItem value="admin">Administrator</SelectItem><SelectItem value="member">Member</SelectItem></SelectContent></Select></label><label className="flex min-h-11 items-center gap-3 text-sm" htmlFor="send-invitation"><Checkbox defaultChecked id="send-invitation" />Send an invitation email</label></CardContent></Card>
          <Card><CardHeader><CardTitle>Notices and destructive confirmation</CardTitle></CardHeader><CardContent className="grid gap-3"><Notice tone="info">Usage figures are reported only when provider pricing is available.</Notice><Notice tone="success">Document indexing completed.</Notice><Notice tone="warning">This invitation expires tomorrow.</Notice><Notice tone="destructive">The source could not be processed.</Notice><AlertDialog><AlertDialogTrigger asChild><Button className="h-10 min-h-10 justify-self-start px-3 text-sm" size="sm" variant="destructive">Delete</Button></AlertDialogTrigger><AlertDialogContent><AlertDialogTitle>Delete this document?</AlertDialogTitle><AlertDialogDescription>The source will stop contributing to future answers. Historical evidence snapshots remain preserved.</AlertDialogDescription><AlertDialogFooter><AlertDialogCancel>Cancel</AlertDialogCancel><AlertDialogAction className="h-10 min-h-10 px-3 text-sm">Delete document</AlertDialogAction></AlertDialogFooter></AlertDialogContent></AlertDialog></CardContent></Card>
        </section>

        <section className="grid gap-5 border-t border-border py-10 lg:grid-cols-2" id="states"><div className="lg:col-span-2"><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">System states</p><h2 className="mt-2 text-2xl font-semibold">Empty, loading and partial data</h2><p className="mt-2 max-w-2xl text-foreground-muted">Every expected non-success state is explicit; unavailable information is never silently omitted or represented as zero.</p></div><EmptyState action={<Button><Sparkles aria-hidden="true" />Start a conversation</Button>} description="Questions and grounded answers will appear here once you begin." icon={Inbox} title="No conversations yet" /><Card><CardHeader><CardTitle>Loading and partial data</CardTitle></CardHeader><CardContent><Tabs defaultValue="loading"><TabsList><TabsTrigger value="loading">Loading</TabsTrigger><TabsTrigger value="partial">Partial</TabsTrigger></TabsList><TabsContent value="loading" className="space-y-3"><Skeleton className="h-5 w-2/3" /><Skeleton className="h-20 w-full" /><Skeleton className="h-5 w-1/2" /></TabsContent><TabsContent value="partial"><Notice tone="warning">Some provider cost fields are unavailable. Available totals remain visible and are not represented as zero.</Notice></TabsContent></Tabs></CardContent></Card></section>

        <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-border py-6 text-sm text-foreground-muted"><span>WCAG 2.2 AA baseline · 44px touch targets · semantic theme tokens</span><Badge variant="outline">Development/test only</Badge></footer>
      </div>
    </main>
  );
}
