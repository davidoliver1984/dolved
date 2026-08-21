import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";

type Props = {
  searchParams: Promise<{ status?: string }>;
};

export default async function VerificationResultPage({ searchParams }: Props) {
  const { status } = await searchParams;
  const verified = status === "verified";

  return (
    <main className="grid min-h-screen place-items-center bg-background p-6">
      <Card className="w-full max-w-xl">
        <CardHeader>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand">
            {verified ? "Email confirmed" : "Verification incomplete"}
          </p>
          <h1 className="mt-2 text-3xl font-semibold">
            {verified ? "You’re ready to begin." : "That link did not work."}
          </h1>
        </CardHeader>
        <CardContent className="grid gap-5">
          <p className="text-foreground-muted">
            {verified
              ? "Your account is verified and the protected workspace is now available."
              : "Return to the verification page to request a fresh signed link."}
          </p>
          <Button asChild className="w-fit">
            <Link href={verified ? "/app" : "/verify-email"}>
              {verified ? "Open workspace" : "Try again"}
            </Link>
          </Button>
        </CardContent>
      </Card>
    </main>
  );
}
