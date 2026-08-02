import "server-only";

import { cookies } from "next/headers";

const FORWARDED_AUTH_COOKIE_NAMES = [
  "rag-platform-session",
  "XSRF-TOKEN",
] as const;

type CookieReader = {
  get(name: string): { value: string } | undefined;
};

export function allowlistedAuthCookieHeader(
  cookieStore: CookieReader,
): string {
  return FORWARDED_AUTH_COOKIE_NAMES.flatMap((name) => {
    const cookie = cookieStore.get(name);

    return cookie ? [`${name}=${cookie.value}`] : [];
  }).join("; ");
}

export async function forwardedAuthCookieHeader(): Promise<string> {
  return allowlistedAuthCookieHeader(await cookies());
}
