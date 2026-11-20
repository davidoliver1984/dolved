"use client";

import { useEffect, useMemo, useState } from "react";
import { Check, ImageUp, RotateCcw, Save } from "lucide-react";
import {
  applyTenantBranding,
  firstError,
  saveTenantBrandingDraft,
  switchTenantBrandingMode,
  uploadTenantBrandingAsset,
  type BrandingAsset,
  type BrandingConfiguration,
  type TenantBrandingSnapshot,
} from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";

type Preview = "light-desktop" | "dark-desktop" | "light-mobile" | "email";
const textareaClass = "min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring";

export function TenantBrandingEditor({ initial }: Readonly<{ initial: TenantBrandingSnapshot }>) {
  const [snapshot, setSnapshot] = useState(initial);
  const [draft, setDraft] = useState(initial.draft);
  const [savedDraft, setSavedDraft] = useState(initial.draft);
  const [preview, setPreview] = useState<Preview>("light-desktop");
  const [pending, setPending] = useState<string | null>(null);
  const [message, setMessage] = useState<{ tone: "success" | "destructive"; text: string } | null>(null);
  const dirty = useMemo(() => JSON.stringify(draft) !== JSON.stringify(savedDraft), [draft, savedDraft]);
  const set = <K extends keyof BrandingConfiguration>(key: K, value: BrandingConfiguration[K]) => setDraft((current) => ({ ...current, [key]: value }));

  useEffect(() => {
    const warn = (event: BeforeUnloadEvent) => {
      if (!dirty) return;
      event.preventDefault();
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);

  const act = async (name: string, operation: () => Promise<TenantBrandingSnapshot>, success: string) => {
    setPending(name); setMessage(null);
    try {
      const next = await operation();
      setSnapshot(next); setDraft(next.draft); setSavedDraft(next.draft);
      setMessage({ tone: "success", text: success });
    } catch (error) {
      setMessage({ tone: "destructive", text: firstError(error) });
    } finally { setPending(null); }
  };

  const upload = async (kind: BrandingAsset["kind"], file: File | undefined) => {
    if (!file) return;
    setPending(kind); setMessage(null);
    try {
      const asset = await uploadTenantBrandingAsset(initial.tenant.public_id, kind, file);
      set(kind, asset);
      setMessage({ tone: "success", text: "Asset uploaded to this draft. Save the draft when you are ready." });
    } catch (error) { setMessage({ tone: "destructive", text: firstError(error) }); }
    finally { setPending(null); }
  };

  const previewDark = preview === "dark-desktop";
  const previewMobile = preview === "light-mobile";
  const brandStyles = {
    "--brand": draft.primary_colour,
    "--brand-foreground": foreground(draft.primary_colour),
    "--ring": draft.accent_colour,
  } as React.CSSProperties;

  return <section className="grid gap-8">
    <header><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand">Platform administration · Tenant branding</p><h1 className="mt-2 text-4xl font-semibold tracking-tight">{initial.tenant.name}</h1><p className="mt-2 text-sm text-foreground-muted">Stable identity: {initial.tenant.public_id} · {initial.tenant.slug}</p><p className="mt-3 max-w-3xl text-foreground-muted">Drafts are private. Applying creates an immutable custom revision; switching to Dolved defaults retains the last custom identity for demonstration or later reuse.</p></header>
    <div className="flex flex-wrap items-center gap-3"><span className="rounded-full bg-surface-raised px-3 py-1.5 text-sm font-semibold">Live: {snapshot.mode}</span><span className="text-sm text-foreground-muted">Applied revision: {snapshot.applied_revision ?? "Dolved default"}</span>{dirty ? <span className="text-sm font-semibold text-status-warning">Unsaved changes</span> : null}</div>
    <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(26rem,0.95fr)]">
      <div className="grid gap-5">
        <Card><CardHeader><CardTitle>Identity and assets</CardTitle><CardDescription>PNG, JPEG or WebP logos and PNG favicons up to 2 MiB. Favicons are resized and safely compressed to a maximum 512 KiB derivative.</CardDescription></CardHeader><CardContent className="grid gap-4 sm:grid-cols-2">
          <Field label="Display brand name"><Input maxLength={80} onChange={(e) => set("display_name", e.target.value)} value={draft.display_name} /></Field>
          <Field label="Accessible logo label"><Input maxLength={120} onChange={(e) => set("logo_label", e.target.value)} value={draft.logo_label} /></Field>
          <AssetField asset={draft.primary_logo} busy={pending === "primary_logo"} label="Primary logo (required)" onFile={(file) => upload("primary_logo", file)} />
          <AssetField asset={draft.dark_logo} busy={pending === "dark_logo"} label="Dark-background logo (optional)" onFile={(file) => upload("dark_logo", file)} />
          <AssetField asset={draft.favicon} busy={pending === "favicon"} label="Favicon (optional PNG)" onFile={(file) => upload("favicon", file)} />
        </CardContent></Card>
        <Card><CardHeader><CardTitle>Colours</CardTitle><CardDescription>Colours map to approved product tokens. Status colours remain independent.</CardDescription></CardHeader><CardContent className="grid gap-4 sm:grid-cols-2"><ColourField label="Primary" value={draft.primary_colour} onChange={(value) => set("primary_colour", value)} /><ColourField label="Accent" value={draft.accent_colour} onChange={(value) => set("accent_colour", value)} /></CardContent></Card>
        <Card><CardHeader><CardTitle>Welcome copy</CardTitle><CardDescription>Plain text only. Blank fields use the approved template with the resolved brand name.</CardDescription></CardHeader><CardContent className="grid gap-4">
          <Field label="Sign-in heading"><Input maxLength={120} onChange={(e) => set("sign_in_heading", e.target.value || null)} value={draft.sign_in_heading ?? ""} /></Field>
          <Field label="Sign-in introduction"><textarea className={textareaClass} maxLength={400} onChange={(e) => set("sign_in_introduction", e.target.value || null)} value={draft.sign_in_introduction ?? ""} /></Field>
          <Field label="Chat welcome heading"><Input maxLength={120} onChange={(e) => set("chat_welcome_heading", e.target.value || null)} value={draft.chat_welcome_heading ?? ""} /></Field>
          <Field label="Chat welcome introduction"><textarea className={textareaClass} maxLength={400} onChange={(e) => set("chat_welcome_introduction", e.target.value || null)} value={draft.chat_welcome_introduction ?? ""} /></Field>
        </CardContent></Card>
        <Card><CardHeader><CardTitle>Correspondence</CardTitle><CardDescription>The authenticated From address and delivery security do not change.</CardDescription></CardHeader><CardContent className="grid gap-4">
          <Field label="Sender display name"><Input maxLength={80} onChange={(e) => set("sender_display_name", e.target.value || null)} value={draft.sender_display_name ?? ""} /></Field>
          <Field label="Support contact"><Input inputMode="email" onChange={(e) => set("support_contact", e.target.value || null)} value={draft.support_contact ?? ""} /></Field>
          <Field label="Footer text"><textarea className={textareaClass} maxLength={300} onChange={(e) => set("footer_text", e.target.value || null)} value={draft.footer_text ?? ""} /></Field>
        </CardContent></Card>
      </div>
      <div className="grid gap-4 xl:sticky xl:top-6">
        <Card><CardHeader><CardTitle>Preview</CardTitle><CardDescription>No live settings, emails or providers are changed by this preview.</CardDescription></CardHeader><CardContent className="grid gap-4"><label className="grid gap-1.5 text-sm font-semibold">Surface<select className="min-h-11 rounded-md border border-input bg-background px-3" onChange={(e) => setPreview(e.target.value as Preview)} value={preview}><option value="light-desktop">Light · desktop</option><option value="dark-desktop">Dark · desktop</option><option value="light-mobile">Light · mobile</option><option value="email">Email</option></select></label>
          <div className={previewDark ? "dark" : ""}><div className={`mx-auto overflow-hidden rounded-xl border border-border bg-background text-foreground ${previewMobile ? "max-w-[22rem]" : "w-full"}`} style={brandStyles}>{preview === "email" ? <EmailPreview draft={draft} /> : <ProductPreview draft={draft} dark={previewDark} />}</div></div>
        </CardContent></Card>
      </div>
    </div>
    <div className="sticky bottom-3 z-10 grid gap-3 rounded-xl border border-border bg-background/95 p-4 shadow-lg backdrop-blur">
      {message ? <Notice className="shadow-sm" tone={message.tone}>{message.text}</Notice> : null}
      <div className="flex flex-wrap gap-3">
        <Button disabled={pending !== null || !dirty} onClick={() => void act("save", () => saveTenantBrandingDraft(initial.tenant.public_id, snapshot.draft_version, draft), "Branding draft saved without changing the live tenant.")}><Save />Save draft</Button>
        <Button disabled={pending !== null || dirty || !savedDraft.primary_logo} onClick={() => window.confirm(`Apply custom branding to ${initial.tenant.name}?`) && void act("apply", () => applyTenantBranding(initial.tenant.public_id, snapshot.draft_version), "Custom branding is now live.")} variant="secondary"><Check />Apply custom branding</Button>
        <Button disabled={pending !== null || snapshot.mode === "default"} onClick={() => window.confirm(`Use Dolved defaults for ${initial.tenant.name}? The custom profile will be retained.`) && void act("default", () => switchTenantBrandingMode(initial.tenant.public_id, "default"), "Dolved defaults are now live; the custom profile is retained.")} variant="outline"><RotateCcw />Use Dolved defaults</Button>
        {snapshot.mode === "default" && snapshot.can_reapply_custom ? <Button disabled={pending !== null} onClick={() => window.confirm(`Restore the last applied custom branding for ${initial.tenant.name}?`) && void act("custom", () => switchTenantBrandingMode(initial.tenant.public_id, "custom"), "The last custom revision is live again.")} variant="outline">Restore custom</Button> : null}
        <Button disabled={!dirty || pending !== null} onClick={() => window.confirm("Discard all unsaved branding changes?") && setDraft(savedDraft)} variant="ghost">Discard unsaved changes</Button>
      </div>
    </div>
  </section>;
}

function Field({ children, label }: Readonly<{ children: React.ReactNode; label: string }>) { return <label className="grid gap-1.5 text-sm font-semibold"><span>{label}</span>{children}</label>; }
function foreground(hex: string): string {
  const hash = String.fromCharCode(35);
  if (!(new RegExp(`^${hash}[0-9A-Fa-f]{6}$`)).test(hex)) return `${hash}FFFFFF`;
  const channels = [1, 3, 5].map((offset) => Number.parseInt(hex.slice(offset, offset + 2), 16) / 255)
    .map((value) => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
  const luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
  return ((luminance + 0.05) / 0.05) >= (1.05 / (luminance + 0.05)) ? `${hash}000000` : `${hash}FFFFFF`;
}
function ColourField({ label, onChange, value }: Readonly<{ label: string; onChange: (value: string) => void; value: string }>) { return <Field label={label}><div className="flex gap-2"><input aria-label={`${label} colour picker`} className="h-11 w-14 rounded-md border border-input bg-background p-1" onChange={(e) => onChange(e.target.value.toUpperCase())} type="color" value={value} /><Input onChange={(e) => onChange(e.target.value)} pattern="^#[0-9A-Fa-f]{6}$" value={value} /></div></Field>; }
function AssetField({ asset, busy, label, onFile }: Readonly<{ asset: BrandingAsset | null; busy: boolean; label: string; onFile: (file: File | undefined) => void }>) { const favicon = asset?.kind === "favicon" || label.startsWith("Favicon"); return <Field label={label}><div className="grid gap-2 rounded-lg border border-border p-3">{asset ? <img alt="" className="h-12 max-w-full object-contain object-left" src={asset.url} /> : <span className="text-sm font-normal text-foreground-muted">No asset selected</span>}<label className="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-md border border-border px-3 text-sm font-semibold hover:bg-accent"><ImageUp className="size-4" />{busy ? "Uploading…" : "Choose image"}<input accept={favicon ? "image/png" : "image/png,image/jpeg,image/webp"} className="sr-only" disabled={busy} onChange={(e) => onFile(e.target.files?.[0])} type="file" /></label></div></Field>; }
function ProductPreview({ draft, dark }: Readonly<{ draft: BrandingConfiguration; dark: boolean }>) { const logo = dark ? draft.dark_logo ?? draft.primary_logo : draft.primary_logo; return <div className="grid min-h-80 grid-cols-[5rem_1fr]"><aside className="border-r border-border bg-sidebar p-3">{logo ? <img alt={draft.logo_label} className="max-h-10 max-w-full object-contain" src={logo.url} /> : <strong>{draft.display_name}</strong>}<div className="mt-8 h-9 rounded-md bg-primary" /><div className="mt-2 h-9 rounded-md bg-surface-raised" /></aside><main className="p-5"><span className="text-xs font-bold uppercase tracking-[0.14em] text-brand">Knowledge workspace</span><h2 className="mt-3 text-2xl font-semibold">{draft.chat_welcome_heading || `Ask ${draft.display_name}`}</h2><p className="mt-2 text-sm text-foreground-muted">{draft.chat_welcome_introduction || `Ask a question grounded in ${draft.display_name}'s approved knowledge base.`}</p><button className="mt-6 min-h-11 rounded-md bg-primary px-4 font-semibold text-primary-foreground">Start a conversation</button></main></div>; }
function EmailPreview({ draft }: Readonly<{ draft: BrandingConfiguration }>) { return <div className="bg-background p-6 text-foreground"><div className="flex justify-center text-center">{draft.primary_logo ? <img alt={draft.logo_label} className="h-12 max-w-48 object-contain" src={draft.primary_logo.url} /> : <strong className="text-xl">{draft.display_name}</strong>}</div><h2 className="mt-6 text-xl font-semibold">Document review needs attention</h2><p className="mt-3 text-sm">A document governance reminder is waiting in your workspace.</p><button className="mt-5 rounded-md px-4 py-3 font-semibold" style={{ background: draft.accent_colour, color: foreground(draft.accent_colour) }}>Review governance work</button><p className="mt-8 border-t pt-4 text-xs text-foreground-muted">{draft.footer_text || `Sent by ${draft.display_name}.`}{draft.support_contact ? ` Support: ${draft.support_contact}` : ""}</p></div>; }
