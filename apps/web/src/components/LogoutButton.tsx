"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { apiFetch } from "@/lib/api";
import { cn } from "@/lib/utils";

export function LogoutButton({ className }: Readonly<{ className?: string }>) {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  return (
    <button
      className={cn("text-button", className)}
      disabled={pending}
      onClick={async () => {
        setPending(true);
        await apiFetch("/api/auth/logout", { method: "POST" });
        router.push("/login");
        router.refresh();
      }}
      type="button"
    >
      {pending ? "Signing out…" : "Sign out"}
    </button>
  );
}
