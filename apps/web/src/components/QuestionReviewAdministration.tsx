"use client";

import { BookOpenCheck, CircleAlert, Eye, FolderSearch, LoaderCircle, Plus } from "lucide-react";
import { type FormEvent, useCallback, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import { StatusBadge } from "@/components/ui/status-badge";
import { Textarea } from "@/components/ui/textarea";
import {
  createQuestionReviewSubject,
  firstError,
  questionReviewDetail,
  questionReviewItems,
  questionReviewKnowledgeGaps,
  questionReviewPolicy,
  questionReviewSubjects,
  updateQuestionReview,
  updateQuestionReviewPolicy,
  type KnowledgeGap,
  type QuestionReviewDetail,
  type QuestionReviewItem,
  type QuestionReviewPolicy,
  type QuestionReviewSubject,
} from "@/lib/api";
import { formatDateTime } from "@/lib/date";

const reviewStatuses = ["unreviewed", "answered_adequately", "incomplete", "unsupported_answer", "missing_evidence", "retrieval_issue", "question_outside_scope", "technical_failure"];
const issueTypes = ["not_yet_established", "missing_content", "incomplete_or_unclear_content", "retrieval_or_applicability_issue", "answer_generation_issue", "technical_failure", "outside_scope", "not_a_gap"];
const workflowStates = ["needs_review", "confirmed", "action_planned", "awaiting_verification", "resolved", "not_a_gap"];
const label = (value: string) => value.replaceAll("_", " ");

export function QuestionReviewAdministration({ workspaceId }: Readonly<{ workspaceId: string }>) {
  const [policy, setPolicy] = useState<QuestionReviewPolicy | null>(null);
  const [items, setItems] = useState<QuestionReviewItem[]>([]);
  const [subjects, setSubjects] = useState<QuestionReviewSubject[]>([]);
  const [gaps, setGaps] = useState<KnowledgeGap[]>([]);
  const [gapDescription, setGapDescription] = useState("");
  const [selected, setSelected] = useState<QuestionReviewDetail | null>(null);
  const [retrospective, setRetrospective] = useState(false);
  const [retrospectiveDays, setRetrospectiveDays] = useState(30);
  const [newSubject, setNewSubject] = useState("");
  const [busy, setBusy] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    const nextPolicy = await questionReviewPolicy(workspaceId);
    setPolicy(nextPolicy);
    if (!nextPolicy.can_review) { setItems([]); setSubjects([]); setGaps([]); return; }
    const [nextItems, nextSubjects, nextGaps] = await Promise.all([questionReviewItems(workspaceId), questionReviewSubjects(workspaceId), questionReviewKnowledgeGaps(workspaceId)]);
    setItems(nextItems); setSubjects(nextSubjects); setGaps(nextGaps.data); setGapDescription(nextGaps.description);
  }, [workspaceId]);

  useEffect(() => {
    let active = true;
    void questionReviewPolicy(workspaceId).then(async (nextPolicy) => {
      if (!active) return;
      setPolicy(nextPolicy);
      if (!nextPolicy.can_review) { setItems([]); setSubjects([]); setGaps([]); return; }
      const [nextItems, nextSubjects, nextGaps] = await Promise.all([questionReviewItems(workspaceId), questionReviewSubjects(workspaceId), questionReviewKnowledgeGaps(workspaceId)]);
      if (!active) return;
      setItems(nextItems); setSubjects(nextSubjects); setGaps(nextGaps.data); setGapDescription(nextGaps.description);
    }).catch((caught) => { if (active) setError(firstError(caught)); }).finally(() => { if (active) setBusy(false); });
    return () => { active = false; };
  }, [workspaceId]);

  const changePolicy = async (enabled: boolean) => {
    setBusy(true); setError(null);
    try { setPolicy(await updateQuestionReviewPolicy(workspaceId, enabled, enabled && retrospective, enabled && retrospective ? retrospectiveDays : 0)); await refresh(); }
    catch (caught) { setError(firstError(caught)); }
    finally { setBusy(false); }
  };

  const inspect = async (runId: string) => {
    setBusy(true); setError(null);
    try { setSelected(await questionReviewDetail(workspaceId, runId, "Quality and corpus review")); }
    catch (caught) { setError(firstError(caught)); }
    finally { setBusy(false); }
  };

  const addSubject = async (event: FormEvent) => {
    event.preventDefault(); if (!newSubject.trim()) return;
    setBusy(true); setError(null);
    try { await createQuestionReviewSubject(workspaceId, newSubject.trim(), "normal"); setNewSubject(""); await refresh(); }
    catch (caught) { setError(firstError(caught)); }
    finally { setBusy(false); }
  };

  if (busy && !policy) return <Card><CardContent className="flex min-h-48 items-center justify-center gap-3"><LoaderCircle className="animate-spin text-brand" />Loading restricted review controls…</CardContent></Card>;
  if (!policy) return <Notice tone="destructive">Question review controls are unavailable.</Notice>;

  return <div className="grid gap-6">
    {error ? <Notice tone="destructive"><CircleAlert className="mr-2 inline size-4" />{error}</Notice> : null}
    <Card><CardHeader><CardTitle className="flex items-center gap-2"><BookOpenCheck className="size-5 text-brand" />Question-review policy</CardTitle><CardDescription>Conversation content remains private unless the workspace owner deliberately enables this restricted review feature.</CardDescription></CardHeader><CardContent className="grid gap-4">
      <div className="flex flex-wrap items-center gap-3"><StatusBadge status={policy.enabled ? "success" : "unavailable"}>{policy.enabled ? "Enabled" : "Off"}</StatusBadge><span className="text-sm text-foreground-muted">Policy {policy.policy_version}{policy.review_from ? ` · reviewable from ${formatDateTime(policy.review_from)}` : ""}</span></div>
      {policy.can_manage && !policy.enabled ? <div className="grid gap-3 rounded-lg border border-border p-4"><div className="flex gap-3"><input aria-labelledby="include-retained-questions-label" checked={retrospective} id="include-retained-questions" onChange={(event) => setRetrospective(event.target.checked)} type="checkbox" /><label htmlFor="include-retained-questions" id="include-retained-questions-label"><strong>Include retained earlier questions</strong><span className="block text-sm text-foreground-muted">Optional and off by default. This exposes potentially sensitive wording to authorised reviewers and records the decision in the audit trail.</span></label></div>{retrospective ? <label className="grid max-w-xs gap-2 text-sm font-semibold" htmlFor="retrospective-days">Days to include (maximum 30)<Input id="retrospective-days" max={30} min={1} onChange={(event) => setRetrospectiveDays(Number(event.target.value))} type="number" value={retrospectiveDays} /></label> : null}<Button className="w-fit" disabled={busy} onClick={() => void changePolicy(true)}>Enable restricted review</Button></div> : null}
      {policy.can_manage && policy.enabled ? <Button className="w-fit" disabled={busy} onClick={() => void changePolicy(false)} variant="outline">Turn off question review</Button> : null}
      {!policy.can_manage ? <Notice tone="info">Only the current workspace owner can change this policy. Ownership transfer moves that authority automatically.</Notice> : null}
    </CardContent></Card>

    {policy.enabled && !policy.can_review ? <Notice tone="warning">Question review is enabled, but your account does not have the restricted review capability.</Notice> : null}
    {policy.can_review ? <>
      <div className="grid gap-6 xl:grid-cols-[minmax(22rem,1fr)_minmax(26rem,1.2fr)]">
        <Card><CardHeader><CardTitle>Review queue</CardTitle><CardDescription>Question wording is hidden until an authorised, audited content view is opened.</CardDescription></CardHeader><CardContent className="grid gap-3">{items.length ? items.map((item) => <article className="grid gap-3 rounded-lg border border-border p-4" key={item.run_id}><div className="flex flex-wrap items-start justify-between gap-2"><div><strong>{formatDateTime(item.occurred_at)}</strong><p className="text-sm text-foreground-muted">{item.site_context} · {label(item.run_status)}</p></div><StatusBadge status={item.review.review_status === "unreviewed" ? "pending" : "info"}>{label(item.review.review_status)}</StatusBadge></div><Button disabled={busy} onClick={() => void inspect(item.run_id)} size="sm" variant="outline"><Eye />Open audited review</Button></article>) : <p className="text-sm text-foreground-muted">No retained questions are available within the enabled review period.</p>}</CardContent></Card>
        <Card><CardHeader><CardTitle>Subjects</CardTitle><CardDescription>Human-managed topics only. No model classification or extra provider call is used.</CardDescription></CardHeader><CardContent className="grid gap-4"><form className="flex gap-2" onSubmit={addSubject}><Input aria-label="New subject name" onChange={(event) => setNewSubject(event.target.value)} placeholder="e.g. Annual leave" value={newSubject} /><Button disabled={busy || !newSubject.trim()} type="submit"><Plus />Add</Button></form><div className="flex flex-wrap gap-2">{subjects.length ? subjects.map((subject) => <StatusBadge key={subject.public_id} status="info">{subject.name}</StatusBadge>) : <span className="text-sm text-foreground-muted">Unclassified questions remain visible in the queue.</span>}</div></CardContent></Card>
      </div>
      <Card><CardHeader><CardTitle className="flex items-center gap-2"><FolderSearch className="size-5 text-brand" />Knowledge gaps</CardTitle><CardDescription>{gapDescription || "Human-reviewed records only."}</CardDescription></CardHeader><CardContent className="grid gap-3">{gaps.length ? gaps.map((gap) => <article className="rounded-lg border border-border p-4" key={gap.public_id}><div className="flex flex-wrap justify-between gap-2"><strong>{gap.name}</strong><StatusBadge status={gap.confirmed_gap_count ? "warning" : "unavailable"}>{gap.confirmed_gap_count} confirmed</StatusBadge></div><p className="mt-2 text-sm text-foreground-muted">{gap.question_count} assigned · {gap.reviewed_count} reviewed · {gap.unresolved_count} unresolved</p></article>) : <p className="text-sm text-foreground-muted">No subject-level findings have been recorded.</p>}</CardContent></Card>
    </> : null}

    {selected ? <QuestionReviewEditor busy={busy} detail={selected} onClose={() => setSelected(null)} onSaved={async () => { setSelected(null); await refresh(); }} subjects={subjects} workspaceId={workspaceId} /> : null}
  </div>;
}

function QuestionReviewEditor({ busy, detail, onClose, onSaved, subjects, workspaceId }: Readonly<{ busy: boolean; detail: QuestionReviewDetail; onClose: () => void; onSaved: () => Promise<void>; subjects: QuestionReviewSubject[]; workspaceId: string }>) {
  const [reviewStatus, setReviewStatus] = useState(detail.review.review_status);
  const [issueType, setIssueType] = useState(detail.review.issue_type ?? "not_yet_established");
  const [workflowState, setWorkflowState] = useState(detail.review.workflow_state);
  const [priority, setPriority] = useState(detail.review.priority);
  const [notes, setNotes] = useState(detail.review.review_notes ?? "");
  const [subjectIds, setSubjectIds] = useState(detail.review.subjects.map((subject) => subject.public_id));
  const [error, setError] = useState<string | null>(null);
  const save = async () => {
    setError(null);
    try { await updateQuestionReview(workspaceId, detail.run_id, { review_status: reviewStatus, issue_type: issueType, workflow_state: workflowState, priority, review_notes: notes || null, linked_document_public_id: null, owner_user_id: null, subject_ids: subjectIds }); await onSaved(); }
    catch (caught) { setError(firstError(caught)); }
  };
  return <Card><CardHeader><CardTitle>Restricted question review</CardTitle><CardDescription>{detail.notice}</CardDescription></CardHeader><CardContent className="grid gap-5">{error ? <Notice tone="destructive">{error}</Notice> : null}<div className="grid gap-4 rounded-lg border border-border p-4"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-foreground-muted">Question</p><p className="mt-2 whitespace-pre-wrap">{detail.question}</p></div><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-foreground-muted">Answer</p><p className="mt-2 whitespace-pre-wrap">{detail.answer ?? "No saved answer."}</p></div><p className="text-sm text-foreground-muted">{detail.sources.length} source passages were displayed for this run.</p></div><div className="grid gap-4 md:grid-cols-2"><label className="grid gap-2 text-sm font-semibold" htmlFor="review-outcome">Review outcome<select className="min-h-11 rounded-md border border-input bg-background px-3" id="review-outcome" onChange={(event) => setReviewStatus(event.target.value)} value={reviewStatus}>{reviewStatuses.map((value) => <option key={value} value={value}>{label(value)}</option>)}</select></label><label className="grid gap-2 text-sm font-semibold" htmlFor="review-finding">Finding<select className="min-h-11 rounded-md border border-input bg-background px-3" id="review-finding" onChange={(event) => setIssueType(event.target.value)} value={issueType}>{issueTypes.map((value) => <option key={value} value={value}>{label(value)}</option>)}</select></label><label className="grid gap-2 text-sm font-semibold" htmlFor="review-workflow-state">Workflow state<select className="min-h-11 rounded-md border border-input bg-background px-3" id="review-workflow-state" onChange={(event) => setWorkflowState(event.target.value)} value={workflowState}>{workflowStates.map((value) => <option key={value} value={value}>{label(value)}</option>)}</select></label><label className="grid gap-2 text-sm font-semibold" htmlFor="review-priority">Priority<select className="min-h-11 rounded-md border border-input bg-background px-3" id="review-priority" onChange={(event) => setPriority(event.target.value)} value={priority}>{["low", "normal", "high", "urgent"].map((value) => <option key={value}>{value}</option>)}</select></label></div><fieldset className="grid gap-2"><legend className="font-semibold">Subjects</legend><div className="flex flex-wrap gap-4">{subjects.map((subject) => <label className="flex gap-2" htmlFor={`review-subject-${subject.public_id}`} key={subject.public_id}><input checked={subjectIds.includes(subject.public_id)} id={`review-subject-${subject.public_id}`} onChange={(event) => setSubjectIds((current) => event.target.checked ? [...current, subject.public_id] : current.filter((id) => id !== subject.public_id))} type="checkbox" />{subject.name}</label>)}</div></fieldset><label className="grid gap-2 text-sm font-semibold" htmlFor="restricted-review-notes">Restricted review notes<Textarea id="restricted-review-notes" maxLength={4000} onChange={(event) => setNotes(event.target.value)} value={notes} /></label><div className="flex gap-3"><Button disabled={busy} onClick={() => void save()}>Save review</Button><Button onClick={onClose} variant="outline">Close</Button></div></CardContent></Card>;
}
