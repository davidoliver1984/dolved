import { AuthForm } from "@/components/AuthForm";
import { currentUser } from "@/lib/server-api";
import { redirect } from "next/navigation";

export default async function RegisterPage() {
  const user = await currentUser();

  if (user) {
    redirect(user.email_verified_at ? "/app" : "/verify-email");
  }

  if (process.env.AUTH_REGISTRATION_ENABLED === "false") {
    return redirect("/login");
  }

  return <AuthForm mode="register" />;
}
