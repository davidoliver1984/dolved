"use client";

import Link from "next/link";
import { useEffect, useSyncExternalStore } from "react";
import { Button } from "@/components/ui/button";

const invitationPath = /^\/invitations\/[A-Za-z0-9_-]+$/;

export function VerificationResultAction({ verified }: Readonly<{ verified: boolean }>) {
  const storedReturn = useSyncExternalStore(
    () => () => undefined,
    () => window.localStorage.getItem("dolved.postVerificationReturnTo"),
    () => null,
  );
  const invitation = verified && storedReturn && invitationPath.test(storedReturn)
    ? storedReturn
    : null;

  useEffect(() => {
    if (invitation) {
      window.localStorage.removeItem("dolved.postVerificationReturnTo");
    }
  }, [invitation]);

  return (
    <Button asChild className="w-fit">
      <Link href={verified ? invitation ?? "/app" : "/verify-email"}>
        {verified ? (invitation ? "Continue to invitation" : "Open workspace") : "Try again"}
      </Link>
    </Button>
  );
}
