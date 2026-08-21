"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Notice } from "@/components/ui/notice";
import { apiFetch, firstError } from "@/lib/api";

export function ResendVerificationButton() {
  const [status, setStatus] = useState("");
  const [pending, setPending] = useState(false);

  return (
    <>
      <Button
        className="w-fit"
        disabled={pending}
        onClick={async () => {
          setPending(true);
          setStatus("");

          try {
            await apiFetch("/api/auth/email/verification-notification", {
              method: "POST",
            });
            setStatus("A fresh verification email is on its way.");
          } catch (error) {
            setStatus(firstError(error));
          } finally {
            setPending(false);
          }
        }}
        type="button"
      >
        {pending ? "Sending…" : "Resend verification email"}
      </Button>
      {status && (
        <Notice tone="info">
          {status}
        </Notice>
      )}
    </>
  );
}
