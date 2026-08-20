import { AuthForm } from "@/components/AuthForm";
import { currentUser } from "@/lib/server-api";
import { redirect } from "next/navigation";

export default async function LoginPage() {
  const user = await currentUser();

  if (user) {
    redirect(user.email_verified_at ? "/app" : "/verify-email");
  }

  return <AuthForm mode="login" />;
}
