import Link from "next/link";
import { ResendVerificationButton } from "@/components/ResendVerificationButton";

export default function VerifyEmailPage() {
  return (
    <main className="centered-shell">
      <section className="message-card">
        <p className="eyebrow">One quick check</p>
        <h1>Verify your email address</h1>
        <p>
          We sent a signed verification link to your inbox. Open it to unlock
          the platform.
        </p>
        <ResendVerificationButton />
        <Link className="text-link" href="/login">
          Return to sign in
        </Link>
      </section>
    </main>
  );
}
