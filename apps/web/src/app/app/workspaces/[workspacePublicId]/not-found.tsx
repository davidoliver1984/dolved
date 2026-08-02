import Link from "next/link";

export default function WorkspaceNotFound() {
  return (
    <section className="empty-workspace">
      <div aria-hidden="true" className="empty-mark">
        ?
      </div>
      <h1>Workspace not found.</h1>
      <p>
        This workspace does not exist or is not available to your account.
      </p>
      <Link className="primary-button" href="/app">
        Return to your workspaces
      </Link>
    </section>
  );
}
