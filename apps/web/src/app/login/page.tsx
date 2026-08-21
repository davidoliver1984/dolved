import { AuthForm } from "@/components/AuthForm";
import { currentUser } from "@/lib/server-api";
import { redirect } from "next/navigation";

type Props = { searchParams?: Promise<{ next?: string }> };

export default async function LoginPage({ searchParams = Promise.resolve({}) }: Props = {}) {
  const { next } = await searchParams;
  const platformEntry = next === "/app/platform/operations";
  const user = await currentUser();

  if (user) {
    redirect(user.email_verified_at ? (platformEntry ? next : "/app") : "/verify-email");
  }

  return platformEntry
    ? <AuthForm context="platform" mode="login" returnTo={next} />
    : <AuthForm mode="login" />;
}
