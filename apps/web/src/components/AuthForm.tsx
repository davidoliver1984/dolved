"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { apiFetch, ApiError, firstError, User } from "@/lib/api";

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
  mode: Mode;
  token?: string;
  email?: string;
};

export function AuthForm({ mode, token, email: initialEmail }: Props) {
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
        router.push("/app");
      }
    } catch (caught) {
      if (
        caught instanceof ApiError &&
        caught.status === 409 &&
        (mode === "login" || mode === "register")
      ) {
        router.push("/app");
      } else {
        setError(firstError(caught));
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <main className="auth-shell">
      <section className="auth-panel">
        <Link className="wordmark" href="/">
          Dolved<span>.</span>
        </Link>

        <div className="auth-heading">
          <p className="eyebrow">{copy.eyebrow}</p>
          <h1>{copy.title}</h1>
          <p>
            {mode === "register"
              ? "One secure account for your documents, searches, and answers."
              : "Your workspace is protected by the Laravel API."}
          </p>
        </div>

        <form className="auth-form" onSubmit={submit}>
          {mode === "register" && (
            <label>
              Name
              <input name="name" autoComplete="name" required />
            </label>
          )}

          <label>
            Email address
            <input
              name="email"
              type="email"
              autoComplete="email"
              defaultValue={initialEmail}
              readOnly={mode === "reset" && Boolean(initialEmail)}
              required
            />
          </label>

          {(mode === "login" || mode === "register" || mode === "reset") && (
            <label>
              Password
              <input
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
              <p className="password-hint">
                Use 12+ characters with upper and lowercase letters, a number,
                and a symbol.
              </p>
              <label>
                Confirm password
                <input
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

          {error && (
            <p className="form-alert error" role="alert">
              {error}
            </p>
          )}
          {message && (
            <p className="form-alert success" role="status">
              {message}
            </p>
          )}

          <button className="primary-button" disabled={pending} type="submit">
            {pending ? "Please wait…" : copy.submit}
          </button>
        </form>

        <nav className="auth-links" aria-label="Account links">
          {mode === "login" && (
            <>
              <Link href="/forgot-password">Forgot password?</Link>
              <Link href="/register">Create an account</Link>
            </>
          )}
          {mode !== "login" && <Link href="/login">Back to sign in</Link>}
        </nav>
      </section>

      <aside className="auth-story" aria-label="Product introduction">
        <p className="eyebrow">Grounded answers, less searching</p>
        <blockquote>
          “Turn scattered source material into answers your team can trust.”
        </blockquote>
        <div className="signal-card">
          <span className="signal-dot" />
          <div>
            <strong>Source-aware by design</strong>
            <p>Every answer keeps its evidence close.</p>
          </div>
        </div>
      </aside>
    </main>
  );
}
