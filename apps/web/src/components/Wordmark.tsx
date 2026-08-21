import Link from "next/link";
import { cn } from "@/lib/utils";

export function Wordmark({
  className,
  href = "/",
}: Readonly<{ className?: string; href?: string }>) {
  return (
    <Link className={cn("font-display text-2xl font-extrabold tracking-[-0.075em]", className)} href={href}>
      dolved
    </Link>
  );
}
