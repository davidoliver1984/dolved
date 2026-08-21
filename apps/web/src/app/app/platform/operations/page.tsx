import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { OperationsHeader, OverviewDestinations, SloSummary, UnavailableOperations } from "@/app/app/platform/operations/_components";
import { platformOperations } from "@/lib/server-api";

const PATH = "/app/platform/operations";

export const metadata: Metadata = {
  title: "Overview · Platform operations · Dolved",
  robots: { index: false, follow: false },
};

export default async function PlatformOperationsPage() {
  const result = await platformOperations();
  if (result.status === "unauthorized") redirect(`/login?next=${PATH}`);
  if (result.status === "concealed") notFound();
  if (result.status === "unavailable") return <UnavailableOperations title="Platform health is unavailable." />;

  return (
    <section aria-label="Platform operations overview" className="grid gap-8">
      <OperationsHeader data={result.data} description="Rolling 28-day technical signals. These objectives are provisional and unmeasured until representative traffic exists." eyebrow="Platform operations overview" title={`Platform health is ${result.data.health_status}.`} />
      <SloSummary data={result.data} />
      <OverviewDestinations data={result.data} />
    </section>
  );
}
