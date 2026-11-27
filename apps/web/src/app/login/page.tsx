import { AuthForm } from "@/components/AuthForm";
import { currentUser, validPendingInvitation } from "@/lib/server-api";
import { redirect } from "next/navigation";
import { isPlatformOperationsPath } from "@/lib/platform-operations";

type Props = { searchParams?: Promise<{ next?: string }> };

export default async function LoginPage({ searchParams = Promise.resolve({}) }: Props) {
  const { next } = await searchParams;
  const platformEntry = isPlatformOperationsPath(next);
  const invitationEntry = typeof next === "string" && /^\/invitations\/[A-Za-z0-9_-]+$/.test(next) ? next : undefined;
  const publicRegistrationEnabled = process.env.AUTH_REGISTRATION_ENABLED !== "false";
  const user = await currentUser();

  if (user) {
    redirect(user.email_verified_at
      ? (platformEntry ? next : invitationEntry ?? "/app")
      : (invitationEntry ? `/verify-email?next=${encodeURIComponent(invitationEntry)}` : "/verify-email"));
  }

  const invitedRegistrationEnabled = !publicRegistrationEnabled && invitationEntry
    ? await validPendingInvitation(invitationEntry.slice("/invitations/".length))
    : false;
  const registrationEnabled = publicRegistrationEnabled || invitedRegistrationEnabled;

  return platformEntry
    ? <AuthForm context="platform" mode="login" registrationEnabled={registrationEnabled} returnTo={next} />
    : invitationEntry
      ? <AuthForm mode="login" registrationEnabled={registrationEnabled} returnTo={invitationEntry} />
      : <AuthForm mode="login" registrationEnabled={registrationEnabled} />;
}
