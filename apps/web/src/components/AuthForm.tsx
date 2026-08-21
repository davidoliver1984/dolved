"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { ThemeToggle } from "@/components/ThemeToggle";
import { Wordmark } from "@/components/Wordmark";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Notice } from "@/components/ui/notice";
import { apiFetch, ApiError, firstError, User } from "@/lib/api";
import type { PlatformOperationsPath } from "@/lib/platform-operations";

type Mode = "login" | "register" | "forgot" | "reset";

const content = {
  login: {
    eyebrow: "Welcome back",
    title: "Sign in to your workspace",
    submit: "Sign in",
    endpoint: "/api/auth/login",
  },
  register: {
    eyebrow: "Create your account",
    title: "Start making knowledge useful",
    submit: "Create account",
    endpoint: "/api/auth/register",
  },
  forgot: {
    eyebrow: "Password recovery",
    title: "Get a secure reset link",
    submit: "Send reset link",
    endpoint: "/api/auth/forgot-password",
  },
  reset: {
    eyebrow: "Choose a new password",
    title: "Reset your password",
    submit: "Save new password",
    endpoint: "/api/auth/reset-password",
  },
} satisfies Record<
  Mode,
  { eyebrow: string; title: string; submit: string; endpoint: string }
>;

type Props = {
  context?: "workspace" | "platform";
  mode: Mode;
  token?: string;
  email?: string;
  returnTo?: PlatformOperationsPath;
};

export function AuthForm({ context = "workspace", mode, token, email: initialEmail, returnTo }: Props) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const copy = content[mode];

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setError("");
    setMessage("");

    const form = new FormData(event.currentTarget);
    const body = Object.fromEntries(form.entries());

    try {
      const response = await apiFetch<{
        data?: { user: User; redirect_to?: string | null };
        message?: string;
      }>(
        copy.endpoint,
        {
          method: "POST",
          body: JSON.stringify(body),
        },
      );

      if (mode === "forgot") {
        setMessage(
          "If an account exists for that email, a reset link has been sent.",
        );
      } else if (mode === "register") {
        router.push("/verify-email");
      } else if (mode === "reset") {
        router.push("/login?reset=complete");
      } else if (mode === "login" && response.data?.redirect_to) {
        window.location.assign(response.data.redirect_to);
      } else {
        router.push(returnTo ?? "/app");
      }
    } catch (caught) {
      if (
        caught instanceof ApiError &&
        caught.status === 409 &&
        (mode === "login" || mode === "register")
      ) {
        router.push(returnTo ?? "/app");
      } else {
        setError(firstError(caught));
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <main className="grid min-h-screen bg-background lg:grid-cols-2">
      <section className="flex min-h-screen flex-col px-6 py-8 sm:px-10 lg:px-[max(3rem,calc((100vw-80rem)/4))] lg:py-10">
        <div className="flex items-center justify-between gap-4">
          <Wordmark />
          <ThemeToggle />
        </div>

        <div className="mt-20 max-w-xl sm:mt-28">
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand">{context === "platform" ? "Platform administration" : copy.eyebrow}</p>
          <h1 className="mt-4 text-5xl font-semibold leading-[0.95] tracking-tight sm:text-6xl">{context === "platform" ? "Platform Operations" : copy.title}</h1>
          <p className="mt-5 text-lg text-foreground-muted">
            {context === "platform"
              ? "Platform metrics, OpenTelemetry health, alerting and Grafana access."
              : mode === "register"
              ? "One secure account for your documents, searches, and answers."
              : "Your workspace is protected by the Laravel API."}
          </p>
        </div>

        <form className="mt-10 grid max-w-xl gap-5" onSubmit={submit}>
          {mode === "register" && (
            <label className="grid gap-2 text-sm font-semibold" htmlFor="auth-name">
              Name
              <Input id="auth-name" name="name" autoComplete="name" required />
            </label>
          )}

          <label className="grid gap-2 text-sm font-semibold" htmlFor="auth-email">
            Email address
            <Input
              id="auth-email"
              name="email"
              type="email"
              autoComplete="email"
              defaultValue={initialEmail}
              readOnly={mode === "reset" && Boolean(initialEmail)}
              required
            />
          </label>

          {(mode === "login" || mode === "register" || mode === "reset") && (
            <label className="grid gap-2 text-sm font-semibold" htmlFor="auth-password">
              Password
              <Input
                id="auth-password"
                name="password"
                type="password"
                autoComplete={
                  mode === "login" ? "current-password" : "new-password"
                }
                minLength={mode === "login" ? undefined : 12}
                required
              />
            </label>
          )}

          {(mode === "register" || mode === "reset") && (
            <>
              <p className="text-sm text-foreground-muted">
                Use 12+ characters with upper and lowercase letters, a number,
                and a symbol.
              </p>
              <label className="grid gap-2 text-sm font-semibold" htmlFor="auth-password-confirmation">
                Confirm password
                <Input
                  id="auth-password-confirmation"
                  name="password_confirmation"
                  type="password"
                  autoComplete="new-password"
                  minLength={12}
                  required
                />
              </label>
            </>
          )}

          {mode === "reset" && <input name="token" type="hidden" value={token} />}

          {error ? <Notice tone="destructive">{error}</Notice> : null}
          {message ? <Notice tone="success">{message}</Notice> : null}

          <Button disabled={pending} type="submit">
            {pending ? "Please wait…" : copy.submit}
          </Button>
        </form>

        <nav className="mt-6 flex max-w-xl flex-wrap gap-6 text-sm font-semibold" aria-label="Account links">
          {mode === "login" && (
            <>
              <Link href="/forgot-password">Forgot password?</Link>
              <Link href="/register">Create an account</Link>
            </>
          )}
          {mode !== "login" && <Link href="/login">Back to sign in</Link>}
        </nav>
      </section>

      <aside className="hidden min-h-screen flex-col justify-end bg-surface-raised px-16 py-20 lg:flex" aria-label={context === "platform" ? "Platform operations introduction" : "Product introduction"}>
        <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand">{context === "platform" ? "Operational clarity, bounded access" : "Grounded answers, less searching"}</p>
        <blockquote className="mt-5 max-w-2xl text-5xl font-semibold leading-tight tracking-tight">
          {context === "platform"
            ? "“See platform health clearly, without crossing tenant boundaries.”"
            : "“Turn scattered source material into answers your team can trust.”"}
        </blockquote>
        <div className="mt-20 flex max-w-xl items-start gap-4 border-t border-border pt-8">
          <span className="mt-1 size-3 shrink-0 rounded-full bg-brand ring-8 ring-brand/15" />
          <div>
            <strong className="font-semibold">{context === "platform" ? "Global health, carefully scoped" : "Source-aware by design"}</strong>
            <p className="mt-1 text-foreground-muted">{context === "platform" ? "Curated operations data stays separate from tenant workspaces." : "Every answer keeps its evidence close."}</p>
          </div>
        </div>
      </aside>
    </main>
  );
}
