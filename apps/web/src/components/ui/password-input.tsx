"use client";

import { Eye, EyeOff } from "lucide-react";
import { forwardRef, type InputHTMLAttributes, useState } from "react";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

export const PasswordInput = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function PasswordInput({ className, ...props }, ref) {
  const [visible, setVisible] = useState(false);
  return <div className="relative">
    <Input className={cn("pr-12", className)} ref={ref} type={visible ? "text" : "password"} {...props} />
    <button aria-label={visible ? "Hide password" : "Show password"} className="absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-md text-foreground-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onClick={() => setVisible((current) => !current)} type="button">
      {visible ? <EyeOff aria-hidden="true" className="size-5" /> : <Eye aria-hidden="true" className="size-5" />}
    </button>
  </div>;
});

export function PasswordStrength({ password }: Readonly<{ password: string }>) {
  const checks = [password.length >= 12, password.length >= 15, /[a-z]/.test(password) && /[A-Z]/.test(password), /\d/.test(password), /[^A-Za-z0-9]/.test(password)];
  const score = checks.filter(Boolean).length;
  const label = score >= 5 ? "Strong" : score >= 3 ? "Good" : "Weak";
  const width = password ? Math.max(12, score * 20) : 0;
  return <div aria-live="polite" className="grid gap-2">
    <div className="h-2 overflow-hidden rounded-full bg-surface-raised"><span className={cn("block h-full rounded-full transition-[width,background-color]", score >= 5 ? "bg-status-success" : score >= 3 ? "bg-status-warning" : "bg-status-destructive")} style={{ width: `${width}%` }} /></div>
    <p className="text-sm text-foreground-muted"><strong className="text-foreground">{label}</strong> · Use 12+ characters, mixed case, a number and symbol. 15+ is strongest.</p>
  </div>;
}
