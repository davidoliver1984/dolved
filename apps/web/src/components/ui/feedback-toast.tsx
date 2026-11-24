"use client";

import { CheckCircle2, X, XCircle } from "lucide-react";
import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { cn } from "@/lib/utils";

type ToastTone = "success" | "destructive";
type Toast = { id: number; message: string; tone: ToastTone };
type ToastContextValue = { showToast: (message: string, tone?: ToastTone) => void };

const ToastContext = createContext<ToastContextValue>({ showToast: () => undefined });

export function FeedbackToastProvider({ children }: Readonly<{ children: ReactNode }>) {
  const [toast, setToast] = useState<Toast | null>(null);
  const showToast = useCallback((message: string, tone: ToastTone = "success") => {
    setToast({ id: Date.now(), message, tone });
  }, []);

  useEffect(() => {
    if (!toast) return;
    const timeout = window.setTimeout(() => setToast((current) => current?.id === toast.id ? null : current), 5000);
    return () => window.clearTimeout(timeout);
  }, [toast]);

  useEffect(() => {
    const receive = (event: Event) => {
      const detail = (event as CustomEvent<{ message?: string; tone?: ToastTone }>).detail;
      if (detail?.message) showToast(detail.message, detail.tone);
    };
    window.addEventListener("dolved:feedback", receive);
    return () => window.removeEventListener("dolved:feedback", receive);
  }, [showToast]);

  const value = useMemo(() => ({ showToast }), [showToast]);
  const Icon = toast?.tone === "destructive" ? XCircle : CheckCircle2;

  return <ToastContext.Provider value={value}>
    {children}
    {toast ? <div className="pointer-events-none fixed inset-x-4 top-5 z-[100] flex justify-center" role={toast.tone === "destructive" ? "alert" : "status"}>
      <div className={cn("pointer-events-auto flex w-full max-w-xl items-center gap-3 rounded-xl px-4 py-3 font-semibold text-white shadow-2xl", toast.tone === "destructive" ? "bg-status-destructive" : "bg-[var(--ring)]")}>
        <Icon aria-hidden="true" className="size-5 shrink-0" />
        <span className="min-w-0 flex-1">{toast.message}</span>
        <button aria-label="Dismiss notification" className="grid size-9 place-items-center rounded-md hover:bg-white/15" onClick={() => setToast(null)} type="button"><X aria-hidden="true" className="size-4" /></button>
      </div>
    </div> : null}
  </ToastContext.Provider>;
}

export function useFeedbackToast(): ToastContextValue {
  return useContext(ToastContext);
}
