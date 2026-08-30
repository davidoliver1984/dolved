import { notFound } from "next/navigation";
import { DocumentComparisonView } from "@/components/DocumentComparisonView";
import type { ComparisonElement, ComparisonSide, DocumentComparison } from "@/lib/server-api";

const element = (id: string, ordinal: number, text: string, kind = "paragraph"): ComparisonElement => ({ id, ordinal, kind, text });
const side = (name: string, date: string, elements: ComparisonElement[]): ComparisonSide => ({ document: { public_id: name, source_filename: name, publisher_label: "Alderbridge Care", source_url: null, governance_status: "approved", effective_from: date, approved_at: date, withdrawn_at: null }, content_available: true, truncated: false, elements, warnings: [] });
const before = side("Medication procedure v1.pdf", "2025-01-01T00:00:00Z", [element("a", 1, "When a dose is omitted", "heading"), element("b", 2, "Record the omitted dose in the medicine record."), element("c", 3, "Tell the shift lead before the end of the shift."), element("d", 4, "Review the incident at the next team meeting."), element("e", 5, "Keep the medicine record with the person's care notes.")]);
const after = side("Medication procedure v2.pdf", "2026-01-01T00:00:00Z", [element("f", 1, "When a dose is omitted", "heading"), element("g", 2, "Record the omitted dose immediately and assess the person's safety."), element("h", 3, "Escalate to the shift lead immediately."), element("i", 4, "Contact the prescriber when clinical advice is required."), element("j", 5, "Keep the medicine record with the person's care notes.")]);
const comparison: DocumentComparison = {
  available: true, from: before, to: after, alignment_status: "reliable", alignment_reason: null,
  formatting_comparison: "unavailable", formatting_reason: "The extraction projection does not retain inline formatting signals.",
  change_counts: { added: 1, removed: 1, modified: 2, moved: 0, unchanged: 2 },
  differences: [
    { id: "same-heading", position: 1, section: "Document start", status: "unchanged", before: before.elements[0], after: after.elements[0] },
    { id: "record-change", position: 2, section: "When a dose is omitted", status: "modified", before: before.elements[1], after: after.elements[1] },
    { id: "escalation-change", position: 3, section: "When a dose is omitted", status: "modified", before: before.elements[2], after: after.elements[2] },
    { id: "meeting-removed", position: 4, section: "When a dose is omitted", status: "removed", before: before.elements[3], after: null },
    { id: "prescriber-added", position: 4, section: "When a dose is omitted", status: "added", before: null, after: after.elements[3] },
    { id: "record-same", position: 5, section: "When a dose is omitted", status: "unchanged", before: before.elements[4], after: after.elements[4] },
  ],
};

export default function LibraryComparisonReference() {
  if (process.env.NODE_ENV === "production") notFound();
  return <main className="mx-auto max-w-6xl p-6 sm:p-10"><p className="mb-6 rounded-lg bg-surface-muted p-3 text-sm text-foreground-muted">Development-only representative fixture. It uses the production comparison component and does not represent workspace evidence.</p><DocumentComparisonView comparison={comparison} familyName="Medication administration procedure" /></main>;
}
