"use client";

import { type FormEvent, useState } from "react";
import {
  changeWorkspaceMemberRole,
  firstError,
  issueWorkspaceInvitation,
  leaveWorkspace,
  removeWorkspaceMember,
  revokeWorkspaceInvitation,
  transferWorkspaceOwnership,
  workspaceInvitations,
  workspaceMembers,
  type WorkspaceAdministrationSnapshot,
  type WorkspaceRole,
} from "@/lib/api";

type Props = {
  initialSnapshot: WorkspaceAdministrationSnapshot | null;
  actorRole: WorkspaceRole;
  workspaceId: string;
};

function commandKey(): string {
  return crypto.randomUUID();
}

export function WorkspaceAdministration({ initialSnapshot, actorRole, workspaceId }: Props) {
  const [snapshot, setSnapshot] = useState(initialSnapshot);
  const [email, setEmail] = useState("");
  const [invitedRole, setInvitedRole] = useState<"member" | "admin">("member");
  const [oneTimeLink, setOneTimeLink] = useState<string | null>(null);
  const [delivery, setDelivery] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const refresh = async () => {
    const [memberships, invitations] = await Promise.all([
      workspaceMembers(workspaceId),
      workspaceInvitations(workspaceId),
    ]);
    setSnapshot({ memberships: memberships.data, invitations: invitations.data });
  };

  const act = async (operation: () => Promise<unknown>, reloadAfter = false) => {
    setBusy(true);
    setError(null);
    try {
      await operation();
      if (reloadAfter) {
        window.location.reload();
        return;
      }
      await refresh();
    } catch (caught) {
      setError(firstError(caught));
    } finally {
      setBusy(false);
    }
  };

  const issue = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setOneTimeLink(null);
    try {
      const response = await issueWorkspaceInvitation(
        workspaceId,
        email,
        invitedRole,
        commandKey(),
      );
      setOneTimeLink(response.data.invitation_link);
      setDelivery(response.data.delivery_status);
      setEmail("");
      await refresh();
    } catch (caught) {
      setError(firstError(caught));
    } finally {
      setBusy(false);
    }
  };

  const leave = async () => {
    if (!window.confirm("Leave this workspace? You will immediately lose access.")) return;
    setBusy(true);
    setError(null);
    try {
      await leaveWorkspace(workspaceId);
      window.location.assign("/app");
    } catch (caught) {
      setError(firstError(caught));
      setBusy(false);
    }
  };

  return (
    <details className="document-tools" open={actorRole !== "member"}>
      <summary>Workspace members</summary>
      <section aria-labelledby="workspace-members-heading">
        <h2 id="workspace-members-heading">Membership administration</h2>
        <p className="scope-note">
          Membership changes take effect immediately. Workspace content remains with the workspace.
        </p>
        {error ? <p className="form-error" role="alert">{error}</p> : null}

        {snapshot ? (
          <>
            <form onSubmit={issue}>
              <label htmlFor="invitation-email">Invite by verified email</label>
              <input
                id="invitation-email"
                onChange={(event) => setEmail(event.target.value)}
                required
                type="email"
                value={email}
              />
              <label htmlFor="invitation-role">Role</label>
              <select
                disabled={actorRole !== "owner"}
                id="invitation-role"
                onChange={(event) => setInvitedRole(event.target.value as "member" | "admin")}
                value={invitedRole}
              >
                <option value="member">Member</option>
                {actorRole === "owner" ? <option value="admin">Administrator</option> : null}
              </select>
              <button className="primary-button compact" disabled={busy} type="submit">Issue invitation</button>
            </form>

            {oneTimeLink ? (
              <div className="status-panel" role="status">
                <strong>Copy this invitation link now.</strong>
                <p>This link is returned only once. Email delivery: {delivery}.</p>
                <code>{oneTimeLink}</code>
                <button className="secondary-button compact" onClick={() => void navigator.clipboard.writeText(oneTimeLink)} type="button">Copy link</button>
              </div>
            ) : null}

            <h3>Members</h3>
            <ul className="administration-list">
              {snapshot.memberships.map((membership) => (
                <li key={membership.public_id}>
                  <div><strong>{membership.user.name}</strong><br /><small>{membership.user.email} · {membership.role}</small></div>
                  <div>
                    {membership.capabilities.change_role ? (
                      <button
                        className="secondary-button compact"
                        disabled={busy}
                        onClick={() => void act(() => changeWorkspaceMemberRole(workspaceId, membership.public_id, membership.role === "admin" ? "member" : "admin", commandKey()))}
                        type="button"
                      >
                        {membership.role === "admin" ? "Make member" : "Make admin"}
                      </button>
                    ) : null}
                    {membership.capabilities.transfer_ownership ? (
                      <button
                        className="secondary-button compact"
                        disabled={busy}
                        onClick={() => window.confirm(`Transfer ownership to ${membership.user.name}?`) && void act(() => transferWorkspaceOwnership(workspaceId, membership.public_id, commandKey()), true)}
                        type="button"
                      >Transfer ownership</button>
                    ) : null}
                    {membership.capabilities.remove ? (
                      <button
                        className="secondary-button compact"
                        disabled={busy}
                        onClick={() => window.confirm(`Remove ${membership.user.name}?`) && void act(() => removeWorkspaceMember(workspaceId, membership.public_id, commandKey()))}
                        type="button"
                      >Remove</button>
                    ) : null}
                  </div>
                </li>
              ))}
            </ul>

            <h3>Invitations</h3>
            <ul className="administration-list">
              {snapshot.invitations.length === 0 ? <li>No invitations yet.</li> : null}
              {snapshot.invitations.map((invitation) => (
                <li key={invitation.public_id}>
                  <div><strong>{invitation.invited_email}</strong><br /><small>{invitation.intended_role} · {invitation.status}</small></div>
                  {invitation.capabilities.revoke ? (
                    <button
                      className="secondary-button compact"
                      disabled={busy}
                      onClick={() => void act(() => revokeWorkspaceInvitation(workspaceId, invitation.public_id, commandKey()))}
                      type="button"
                    >Revoke</button>
                  ) : null}
                </li>
              ))}
            </ul>
          </>
        ) : (
          <p>Only workspace owners and administrators can view the member directory.</p>
        )}

        {actorRole === "owner" ? (
          <p>Transfer ownership before leaving this workspace.</p>
        ) : (
          <button className="secondary-button" disabled={busy} onClick={() => void leave()} type="button">Leave workspace</button>
        )}
      </section>
    </details>
  );
}
