import Link from "next/link";
import { ResendVerificationButton } from "@/components/ResendVerificationButton";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";

export default function VerifyEmailPage() {
  return (
    <main className="grid min-h-screen place-items-center bg-background p-6">
      <Card className="w-full max-w-xl">
        <CardHeader>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand">One quick check</p>
          <h1 className="mt-2 text-3xl font-semibold">Verify your email address</h1>
        </CardHeader>
        <CardContent className="grid gap-5">
          <p className="text-foreground-muted">
            We sent a signed verification link to your inbox. Open it to unlock
            the platform.
          </p>
          <ResendVerificationButton />
          <Button asChild className="w-fit" variant="link">
            <Link href="/login">Return to sign in</Link>
          </Button>
        </CardContent>
      </Card>
    </main>
  );
}
