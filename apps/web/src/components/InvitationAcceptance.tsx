"use client";

import { useState } from "react";
import { acceptWorkspaceInvitation, firstError } from "@/lib/api";

export function InvitationAcceptance({ token }: Readonly<{ token: string }>) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const accept = async () => {
    setBusy(true);
    setError(null);
    try {
      const response = await acceptWorkspaceInvitation(token);
      window.location.assign(`/app/workspaces/${encodeURIComponent(response.data.workspace_id)}`);
    } catch (caught) {
      setError(firstError(caught));
      setBusy(false);
    }
  };

  return (
    <main className="auth-shell">
      <section className="auth-panel" aria-labelledby="invitation-heading">
        <div className="auth-heading">
          <p className="eyebrow">Workspace invitation</p>
          <h1 id="invitation-heading">Join this workspace</h1>
          <p>Accept using the signed-in account whose verified email address received the invitation.</p>
        </div>
        {error ? <p className="form-error" role="alert">{error}</p> : null}
        <button className="primary-button" disabled={busy} onClick={() => void accept()} type="button">
          {busy ? "Checking invitation…" : "Accept invitation"}
        </button>
      </section>
      <aside className="auth-story" aria-label="Invitation security">
        <p className="eyebrow">Verified access</p>
        <blockquote>The invitation works only for its intended verified email address.</blockquote>
      </aside>
    </main>
  );
}
