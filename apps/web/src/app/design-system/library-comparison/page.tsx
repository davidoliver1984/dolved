import { notFound } from "next/navigation";
import { DocumentComparisonView } from "@/components/DocumentComparisonView";
import type { ComparisonElement, ComparisonSide, DocumentComparison } from "@/lib/server-api";

const element = (id: string, ordinal: number, text: string): ComparisonElement => ({ id, ordinal, kind: "paragraph", text });
const side = (name: string, date: string, elements: ComparisonElement[]): ComparisonSide => ({ document: { public_id: name, source_filename: name, publisher_label: "Alderbridge Care", source_url: null, governance_status: "approved", effective_from: date, approved_at: date, withdrawn_at: null }, content_available: true, truncated: false, elements, warnings: [] });
const before = side("Medication procedure v1.pdf", "2025-01-01T00:00:00Z", [element("a", 1, "Record the omitted dose in the medicine record."), element("b", 2, "Tell the shift lead before the end of the shift."), element("c", 3, "Review the incident at the next team meeting.")]);
const after = side("Medication procedure v2.pdf", "2026-01-01T00:00:00Z", [element("d", 1, "Record the omitted dose immediately and assess the person's safety."), element("e", 2, "Escalate to the shift lead immediately."), element("f", 4, "Contact the prescriber when clinical advice is required.")]);
const comparison: DocumentComparison = { available: true, from: before, to: after, differences: [{ ordinal: 1, status: "changed", before: before.elements[0], after: after.elements[0] }, { ordinal: 2, status: "changed", before: before.elements[1], after: after.elements[1] }, { ordinal: 3, status: "removed", before: before.elements[2], after: null }, { ordinal: 4, status: "added", before: null, after: after.elements[2] }] };

export default function LibraryComparisonReference() {
  if (process.env.NODE_ENV === "production") notFound();
  return <main className="mx-auto max-w-6xl p-6 sm:p-10"><p className="mb-6 rounded-lg bg-surface-muted p-3 text-sm text-foreground-muted">Development-only representative fixture. It uses the production comparison component and does not represent workspace evidence.</p><DocumentComparisonView comparison={comparison} familyName="Medication administration procedure" /></main>;
}
