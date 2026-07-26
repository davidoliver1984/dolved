import { AuthForm } from "@/components/AuthForm";

type Props = {
  searchParams: Promise<{ token?: string; email?: string }>;
};

export default async function ResetPasswordPage({ searchParams }: Props) {
  const { token = "", email = "" } = await searchParams;

  return <AuthForm email={email} mode="reset" token={token} />;
}
