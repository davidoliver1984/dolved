import { AuthForm } from "@/components/AuthForm";
import { currentUser, validPendingInvitation } from "@/lib/server-api";
import { redirect } from "next/navigation";

type Props = { searchParams?: Promise<{ next?: string }> };

export default async function RegisterPage({ searchParams = Promise.resolve({}) }: Props = {}) {
  const { next } = await searchParams;
  const invitationEntry = typeof next === "string" && /^\/invitations\/[A-Za-z0-9_-]+$/.test(next) ? next : undefined;
  const user = await currentUser();

  if (user) {
    redirect(user.email_verified_at
      ? invitationEntry ?? "/app"
      : (invitationEntry ? `/verify-email?next=${encodeURIComponent(invitationEntry)}` : "/verify-email"));
  }

  if (process.env.AUTH_REGISTRATION_ENABLED === "false" && !invitationEntry) {
    return redirect("/login");
  }

  if (process.env.AUTH_REGISTRATION_ENABLED === "false" && invitationEntry) {
    const token = invitationEntry.slice("/invitations/".length);
    if (!await validPendingInvitation(token)) {
      return redirect(`/login?next=${encodeURIComponent(invitationEntry)}`);
    }
  }

  return invitationEntry ? <AuthForm mode="register" returnTo={invitationEntry} /> : <AuthForm mode="register" />;
}
