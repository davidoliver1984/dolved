import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { LogoutButton } from "@/components/LogoutButton";
import { currentUser, platformAccess } from "@/lib/server-api";

export const metadata: Metadata = {
  title: "Workspace",
  robots: { index: false, follow: false },
};

export default async function AuthenticatedApplicationLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const access = await platformAccess();

  if (access.status === 401) {
    redirect("/login");
  }

  if (access.status === 403) {
    redirect("/verify-email");
  }

  if (!access.ok) {
    throw new Error("The platform API is unavailable.");
  }

  const user = await currentUser();

  if (!user) {
    redirect("/login");
  }

  return (
    <main className="workspace-shell">
      <nav aria-label="Account" className="site-nav">
        <span className="wordmark">
          Dolved<span>.</span>
        </span>
        <div className="account-nav">
          <span>{user.email}</span>
          <LogoutButton />
        </div>
      </nav>

      {children}
    </main>
  );
}
