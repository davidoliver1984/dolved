"use client";

import { ArrowRight, BookOpenCheck, CheckCircle2, CircleDot, Upload } from "lucide-react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";

export type StarterQuestion = { family_public_id: string; question: string };

type Props = {
  onAsk: () => void;
  onChooseQuestion: (question: string) => void;
  searchableDocumentCount: number;
  starterQuestions: StarterQuestion[];
  workspaceId: string;
};

const detailedStates = [
  "Selected or uploading", "Safely staged", "Matching or details unresolved",
  "Promoted and queued", "Processing", "Awaiting approval",
  "Approved but not technically ready", "Indexed but not yet authoritative",
  "Approved, current, indexed and searchable", "Warning or failed",
];

const userStages = [
  ["Upload documents", "Add the files that should become part of the workspace knowledge base."],
  ["Match or create", "Match each upload to an existing document, or create a new document family."],
  ["Review details", "Confirm metadata, applicability and any issues that need attention."],
  ["Approve", "Authorise the prepared version when it is ready to be used."],
  ["Ask grounded questions", "Only approved, current and indexed documents become searchable."],
];

export function KnowledgeReadinessPanel({ onAsk, onChooseQuestion, searchableDocumentCount, starterQuestions, workspaceId }: Readonly<Props>) {
  const ready = searchableDocumentCount > 0;
  const countLabel = `${searchableDocumentCount} ${searchableDocumentCount === 1 ? "document" : "documents"} currently searchable within your workspace’s knowledge base`;

  return (
    <Card aria-labelledby="knowledge-readiness-heading" className="overflow-hidden border-brand/30 bg-brand/5">
      <CardContent className="grid gap-5 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-3">
            <StatusBadge status={ready ? "success" : "pending"}>{ready ? "Ready to ask" : "Preparing knowledge"}</StatusBadge>
            <p className="text-sm font-semibold text-foreground" role="status">{countLabel}</p>
          </div>
          <h2 className="mt-3 text-xl font-semibold" id="knowledge-readiness-heading">{ready ? "Your searchable knowledge base is ready" : "Prepare a document before asking Dolved"}</h2>
          <p className="mt-2 max-w-3xl text-sm leading-6 text-foreground-muted">
            {ready
              ? "This count includes document families whose current version is approved and fully indexed. A question can still narrow the eligible evidence by authority or location."
              : "Uploaded files are not searchable automatically. A version must finish processing, be approved, and be current before Dolved can answer from it."}
          </p>

          {ready && starterQuestions.length > 0 ? <div className="mt-4">
            <p className="text-xs font-bold uppercase tracking-[0.12em] text-foreground-muted">Try a grounded question</p>
            <div className="mt-2 flex flex-wrap gap-2">{starterQuestions.map((starter) => <button className="rounded-full border border-border bg-card px-3 py-2 text-left text-sm font-medium hover:border-brand hover:bg-brand/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" key={starter.family_public_id} onClick={() => onChooseQuestion(starter.question)} type="button">{starter.question}</button>)}</div>
          </div> : null}

          <details className="mt-4 rounded-lg border border-border bg-card/70 px-4 py-3">
            <summary className="cursor-pointer text-sm font-semibold">How documents become searchable</summary>
            <ol className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">{userStages.map(([title, description], index) => <li className="grid grid-cols-[auto_1fr] gap-2" key={title}><span className="flex size-6 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">{index + 1}</span><span><strong className="block text-sm">{title}</strong><span className="mt-1 block text-xs leading-5 text-foreground-muted">{description}</span></span></li>)}</ol>
            <details className="mt-4 border-t border-border pt-3">
              <summary className="cursor-pointer text-xs font-semibold text-foreground-muted">See all ten readiness states</summary>
              <ol className="mt-3 grid gap-x-6 gap-y-2 text-xs text-foreground-muted sm:grid-cols-2">{detailedStates.map((state, index) => <li className="flex gap-2" key={state}><CircleDot aria-hidden="true" className="mt-0.5 size-3.5 shrink-0 text-brand" /><span>{index + 1}. {state}</span></li>)}</ol>
            </details>
          </details>
        </div>

        <div className="flex flex-wrap gap-2 lg:max-w-56 lg:flex-col">
          {ready ? <Button onClick={onAsk} type="button"><CheckCircle2 />Ask Dolved</Button> : <Button asChild><Link href={`/app/workspaces/${workspaceId}/documents`}><Upload />Upload documents</Link></Button>}
          <Button asChild variant="secondary"><Link href={`/app/workspaces/${workspaceId}/documents?searchable=true`}><BookOpenCheck />View searchable documents<ArrowRight /></Link></Button>
        </div>
      </CardContent>
    </Card>
  );
}
