import { AuthForm } from "@/components/AuthForm";
import { currentUser } from "@/lib/server-api";
import { redirect } from "next/navigation";

type Props = { searchParams?: Promise<{ next?: string }> };

export default async function RegisterPage({ searchParams = Promise.resolve({}) }: Props = {}) {
  const { next } = await searchParams;
  const invitationEntry = typeof next === "string" && /^\/invitations\/[A-Za-z0-9_-]+$/.test(next) ? next : undefined;
  const user = await currentUser();

  if (user) {
    redirect(user.email_verified_at ? invitationEntry ?? "/app" : "/verify-email");
  }

  if (process.env.AUTH_REGISTRATION_ENABLED === "false") {
    return redirect(invitationEntry ? `/login?next=${encodeURIComponent(invitationEntry)}` : "/login");
  }

  return invitationEntry ? <AuthForm mode="register" returnTo={invitationEntry} /> : <AuthForm mode="register" />;
}
