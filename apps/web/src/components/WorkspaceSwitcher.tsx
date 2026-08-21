import Link from "next/link";
import type { Workspace } from "@/lib/api";

type WorkspaceSwitcherProps = {
  activeWorkspace: Workspace;
  workspaces: Workspace[];
};

export function WorkspaceSwitcher({
  activeWorkspace,
  workspaces,
}: WorkspaceSwitcherProps) {
  return (
    <nav className="rounded-xl border border-border bg-card p-4" aria-label="Your workspaces">
      <p className="text-xs font-bold uppercase tracking-[0.16em] text-foreground-muted">Your workspaces</p>
      <div className="mt-3 grid gap-2">
        {workspaces.map((workspace) => {
          const isActive = workspace.public_id === activeWorkspace.public_id;

          return (
            <Link
              className={isActive ? "grid min-h-11 rounded-lg bg-brand px-3 py-2 text-brand-foreground" : "grid min-h-11 rounded-lg px-3 py-2 hover:bg-surface-hover"}
              href={`/app/workspaces/${workspace.public_id}`}
              key={workspace.public_id}
              aria-current={isActive ? "page" : undefined}
              aria-label={`${workspace.name}, role ${workspace.role}`}
            >
              <span>{workspace.name}</span>
              <small className={isActive ? "text-brand-foreground/80" : "text-foreground-muted"}>{workspace.role}</small>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
