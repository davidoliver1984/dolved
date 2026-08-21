import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import {
  currentUser,
  hasPlatformOperationsAccess,
  platformAccess,
  userWorkspaces,
} from "@/lib/server-api";

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
  const [canOperatePlatform, workspaces] = await Promise.all([
    hasPlatformOperationsAccess(),
    userWorkspaces(),
  ]);

  return (
    <AppShell canOperatePlatform={canOperatePlatform} user={user} workspaces={workspaces}>
      {children}
    </AppShell>
  );
}
