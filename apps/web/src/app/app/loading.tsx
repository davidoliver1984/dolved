export default function AuthenticatedApplicationLoading() {
  return (
    <section aria-live="polite" className="rounded-xl border border-border bg-card p-6">
      <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand">Loading workspace</p>
      <h1 className="mt-2 text-3xl font-semibold">Preparing your workspace…</h1>
      <p className="mt-3 text-foreground-muted">Checking the latest workspace information with Laravel.</p>
    </section>
  );
}
