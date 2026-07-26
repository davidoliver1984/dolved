import Link from "next/link";

type Props = {
  searchParams: Promise<{ status?: string }>;
};

export default async function VerificationResultPage({ searchParams }: Props) {
  const { status } = await searchParams;
  const verified = status === "verified";

  return (
    <main className="centered-shell">
      <section className="message-card">
        <p className="eyebrow">
          {verified ? "Email confirmed" : "Verification incomplete"}
        </p>
        <h1>{verified ? "You’re ready to begin." : "That link did not work."}</h1>
        <p>
          {verified
            ? "Your account is verified and the protected workspace is now available."
            : "Return to the verification page to request a fresh signed link."}
        </p>
        <Link
          className="primary-button"
          href={verified ? "/app" : "/verify-email"}
        >
          {verified ? "Open workspace" : "Try again"}
        </Link>
      </section>
    </main>
  );
}
