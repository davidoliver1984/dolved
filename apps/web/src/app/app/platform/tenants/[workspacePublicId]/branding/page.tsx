import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { TenantBrandingEditor } from "@/components/TenantBrandingEditor";
import { platformTenantBranding } from "@/lib/server-api";

export const metadata: Metadata = { title: "Branding · Platform administration · Dolved", robots: { index: false, follow: false } };

export default async function TenantBrandingPage({ params }: Readonly<{ params: Promise<{ workspacePublicId: string }> }>) {
  const { workspacePublicId } = await params;
  const result = await platformTenantBranding(workspacePublicId);
  if (result.status === "unauthorized") redirect(`/login?next=/app/platform/tenants/${encodeURIComponent(workspacePublicId)}/branding`);
  if (result.status === "concealed") notFound();
  if (result.status === "unavailable") throw new Error("Tenant branding is unavailable.");
  return <TenantBrandingEditor initial={result.data} />;
}
