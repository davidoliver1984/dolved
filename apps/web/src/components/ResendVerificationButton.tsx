"use client";

import { useState } from "react";
import { apiFetch, firstError } from "@/lib/api";

export function ResendVerificationButton() {
  const [status, setStatus] = useState("");
  const [pending, setPending] = useState(false);

  return (
    <>
      <button
        className="primary-button"
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
      </button>
      {status && (
        <p className="form-alert success" role="status">
          {status}
        </p>
      )}
    </>
  );
}
