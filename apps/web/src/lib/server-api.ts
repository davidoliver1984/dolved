import "server-only";

import { cookies } from "next/headers";
import type { User } from "@/lib/api";

const internalApiUrl =
  process.env.API_INTERNAL_URL?.replace(/\/$/, "") ?? "http://localhost:8000";
const frontendUrl = process.env.FRONTEND_URL ?? "http://localhost:3000";

async function serverFetch(path: string): Promise<Response> {
  const cookieHeader = (await cookies()).toString();

  return fetch(`${internalApiUrl}${path}`, {
    cache: "no-store",
    headers: {
      Accept: "application/json",
      Cookie: cookieHeader,
      Origin: frontendUrl,
    },
  });
}

export async function platformAccess(): Promise<Response> {
  return serverFetch("/api/platform/status");
}

export async function currentUser(): Promise<User | null> {
  const response = await serverFetch("/api/auth/user");

  if (!response.ok) {
    return null;
  }

  const payload = (await response.json()) as { data: { user: User } };

  return payload.data.user;
}
