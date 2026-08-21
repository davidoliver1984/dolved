import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { OperationsHeader, UnavailableOperations } from "@/app/app/platform/operations/_components";
import { OperationalPolicyPanel } from "@/components/OperationalPolicyPanel";
import { platformOperations } from "@/lib/server-api";

const PATH = "/app/platform/operations/policy";
export const metadata: Metadata = { title: "Policy · Platform operations · Dolved", robots: { index: false, follow: false } };

export default async function PlatformPolicyPage() {
  const result = await platformOperations();
  if (result.status === "unauthorized") redirect(`/login?next=${PATH}`);
  if (result.status === "concealed") notFound();
  if (result.status === "unavailable") return <UnavailableOperations title="Operational policy is unavailable." />;
  return <section aria-label="Operational policy" className="grid gap-8"><OperationsHeader data={result.data} eyebrow="Infrastructure reconciliation" title="Operational policy" description="Record desired observability policy and inspect independent reconciliation state for every authenticated target." /><OperationalPolicyPanel policy={result.data.operational_policy} /></section>;
}
