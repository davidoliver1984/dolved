"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { apiFetch } from "@/lib/api";
import { Button } from "@/components/ui/button";

export function LogoutButton({ className }: Readonly<{ className?: string }>) {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  return (
    <Button
      className={className}
      disabled={pending}
      onClick={async () => {
        setPending(true);
        await apiFetch("/api/auth/logout", { method: "POST" });
        router.push("/login");
        router.refresh();
      }}
      type="button"
      variant="ghost"
    >
      {pending ? "Signing out…" : "Sign out"}
    </Button>
  );
}
