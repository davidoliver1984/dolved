"use client";

import Link from "next/link";

export default function AuthenticatedApplicationError({
  reset,
}: Readonly<{ error: Error & { digest?: string }; reset: () => void }>) {
  return (
    <section className="empty-workspace" role="alert">
      <div aria-hidden="true" className="empty-mark">
        !
      </div>
      <h1>We could not load this workspace.</h1>
      <p>
        Your account is still secure. Try loading this page again, or return
        to your workspace list.
      </p>
      <div className="upload-item-actions">
        <button className="primary-button" onClick={reset} type="button">
          Try again
        </button>
        <Link className="secondary-button" href="/app">
          Return to workspaces
        </Link>
      </div>
    </section>
  );
}
