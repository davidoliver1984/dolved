import Link from "next/link";
import { Wordmark } from "@/components/Wordmark";
import { Button } from "@/components/ui/button";

export default function Home() {
  return (
    <main className="min-h-screen bg-background px-6 py-8 sm:px-10 lg:px-16">
      <nav className="mx-auto flex max-w-7xl items-center justify-between" aria-label="Public navigation">
        <Wordmark />
        <div className="flex items-center gap-3">
          <Button asChild variant="ghost"><Link href="/login">
            Sign in
          </Link></Button>
          <Button asChild><Link href="/register">
            Get started
          </Link></Button>
        </div>
      </nav>

      <section className="mx-auto max-w-7xl py-24 sm:py-32">
        <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand">Your knowledge, ready when you are</p>
        <h1 className="mt-5 max-w-4xl text-6xl font-semibold leading-[0.9] tracking-tight sm:text-8xl">
          Stop hunting.
          <br />
          Start knowing.
        </h1>
        <p className="mt-8 max-w-2xl text-xl leading-8 text-foreground-muted">
          Dolved turns the documents your team already trusts into clear,
          grounded answers—with the source always in sight.
        </p>
        <div className="mt-10 flex flex-wrap gap-3">
          <Button asChild size="lg"><Link href="/register">
            Build your workspace
          </Link></Button>
          <Button asChild size="lg" variant="secondary"><Link href="/login">
            I already have an account
          </Link></Button>
        </div>
      </section>

      <section className="mx-auto grid max-w-7xl gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-3" aria-label="Product principles">
        <div className="bg-surface p-6">
          <span className="text-xs font-bold text-brand">01</span>
          <strong className="mt-4 block text-lg">Grounded</strong>
          <p className="mt-1 text-sm text-foreground-muted">Answers stay connected to their evidence.</p>
        </div>
        <div className="bg-surface p-6">
          <span className="text-xs font-bold text-brand">02</span>
          <strong className="mt-4 block text-lg">Private</strong>
          <p className="mt-1 text-sm text-foreground-muted">Your workspace remains your workspace.</p>
        </div>
        <div className="bg-surface p-6">
          <span className="text-xs font-bold text-brand">03</span>
          <strong className="mt-4 block text-lg">Useful</strong>
          <p className="mt-1 text-sm text-foreground-muted">Designed for the question you have right now.</p>
        </div>
      </section>
    </main>
  );
}
