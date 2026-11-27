import type { Metadata } from "next";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { platformTenants } from "@/lib/server-api";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export const metadata: Metadata = { title: "Tenants · Platform administration · Dolved", robots: { index: false, follow: false } };

export default async function PlatformTenantsPage() {
  const result = await platformTenants();
  if (result.status === "unauthorized") redirect("/login?next=/app/platform/tenants");
  if (result.status === "concealed") notFound();
  if (result.status === "unavailable") throw new Error("Tenant administration is unavailable.");

  return <section className="grid gap-8">
    <header><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Platform administration</p><h1 className="mt-2 text-4xl font-semibold tracking-tight">Tenants</h1><p className="mt-3 max-w-2xl text-foreground-muted">Manage presentation identity without changing a tenant&apos;s stable workspace identity, permissions or knowledge configuration.</p></header>
    <div className="grid gap-4">
      {result.data.map((tenant) => <Card key={tenant.public_id}><CardHeader><div className="flex flex-wrap items-start justify-between gap-4"><div><CardTitle>{tenant.name}</CardTitle><CardDescription>{tenant.slug} · {tenant.public_id}</CardDescription></div><span className="rounded-full border border-border px-3 py-1 text-xs font-bold uppercase tracking-[0.12em]">{tenant.branding_mode}</span></div></CardHeader><CardContent><Link className="font-semibold text-brand underline-offset-4 hover:underline" href={`/app/platform/tenants/${tenant.public_id}/branding`}>Open branding</Link></CardContent></Card>)}
    </div>
  </section>;
}
