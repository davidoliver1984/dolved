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
    <nav className="workspace-switcher" aria-label="Your workspaces">
      <p className="workspace-switcher-label">Your workspaces</p>
      <div className="workspace-options">
        {workspaces.map((workspace) => {
          const isActive = workspace.public_id === activeWorkspace.public_id;

          return (
            <Link
              className={isActive ? "workspace-option active" : "workspace-option"}
              href={`/app/workspaces/${workspace.public_id}`}
              key={workspace.public_id}
              aria-current={isActive ? "page" : undefined}
              aria-label={`${workspace.name}, role ${workspace.role}`}
            >
              <span>{workspace.name}</span>
              <small>{workspace.role}</small>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
