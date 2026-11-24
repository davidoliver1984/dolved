"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ThemeToggle } from "@/components/ThemeToggle";
import { Wordmark } from "@/components/Wordmark";
import { Button } from "@/components/ui/button";
import { Notice } from "@/components/ui/notice";
import { acceptWorkspaceInvitation, ApiError, firstError, invitationBranding, type EffectiveBranding } from "@/lib/api";

export function InvitationAcceptance({ token }: Readonly<{ token: string }>) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [branding, setBranding] = useState<EffectiveBranding | null>(null);

  useEffect(() => {
    let active = true;
    void invitationBranding(token).then((resolved) => {
      if (active) setBranding(resolved);
    }).catch(() => undefined);
    return () => { active = false; };
  }, [token]);

  const brand = branding?.configuration;
  const style = brand ? { "--brand": brand.primary_colour, "--brand-foreground": brand.primary_foreground, "--ring": brand.accent_colour } as React.CSSProperties : undefined;

  const accept = async () => {
    setBusy(true);
    setError(null);
    try {
      const response = await acceptWorkspaceInvitation(token);
      router.push(`/app/workspaces/${encodeURIComponent(response.data.workspace_id)}`);
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 401) {
        router.push(`/login?next=${encodeURIComponent(`/invitations/${token}`)}`);
        return;
      }
      setError(firstError(caught));
      setBusy(false);
    }
  };

  return (
    <main className="grid min-h-screen bg-background lg:grid-cols-2" style={style}>
      <section className="flex min-h-screen flex-col px-6 py-8 sm:px-10 lg:px-[max(3rem,calc((100vw-80rem)/4))] lg:py-10" aria-labelledby="invitation-heading">
        <div className="flex items-center justify-between gap-4">{brand?.primary_logo ? <img alt={brand.logo_label} className="h-12 max-w-56 object-contain object-left" src={brand.primary_logo.url} /> : <Wordmark />}<ThemeToggle /></div>
        <div className="mt-24 max-w-xl">
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand">Workspace invitation</p>
          <h1 className="mt-4 text-5xl font-semibold tracking-tight" id="invitation-heading">Join {brand?.display_name ?? "this workspace"}</h1>
          <p className="mt-5 text-lg text-foreground-muted">Accept using the signed-in account whose verified email address received the invitation.</p>
        </div>
        {error ? <Notice className="mt-8 max-w-xl" tone="destructive"><strong>Invitation could not be accepted</strong><p className="mt-1">{error}</p><p className="mt-2 text-xs">The invitation may be expired, revoked, already resolved, or intended for a different verified account.</p></Notice> : null}
        <Button className="mt-8 w-fit" disabled={busy} onClick={() => void accept()} type="button">
          {busy ? "Checking invitation…" : "Accept invitation"}
        </Button>
      </section>
      <aside className="hidden min-h-screen flex-col justify-end bg-surface-raised px-16 py-20 lg:flex" aria-label="Invitation security">
        <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand">Verified access</p>
        <blockquote className="mt-5 max-w-2xl text-5xl font-semibold leading-tight tracking-tight">The invitation works only for its intended verified email address.</blockquote>
      </aside>
    </main>
  );
}
