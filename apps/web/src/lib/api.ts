import { clientEnvironment } from "@/lib/env/client";

export type User = {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
};

export type WorkspaceRole = "owner" | "admin" | "member";

export type Workspace = {
  public_id: string;
  name: string;
  slug: string;
  role: WorkspaceRole;
};

type ValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors: ValidationErrors = {},
  ) {
    super(message);
  }
}

function cookieValue(name: string): string | null {
  const prefix = `${name}=`;
  const cookie = document.cookie
    .split("; ")
    .find((item) => item.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null;
}

function isUnsafe(method: string): boolean {
  return !["GET", "HEAD", "OPTIONS"].includes(method.toUpperCase());
}

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const method = init.method ?? "GET";
  const headers = new Headers(init.headers);

  headers.set("Accept", "application/json");

  if (init.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  if (isUnsafe(method)) {
    await fetch(`${clientEnvironment.NEXT_PUBLIC_API_URL}/sanctum/csrf-cookie`, {
      credentials: "include",
      headers: { Accept: "application/json" },
    });

    const token = cookieValue("XSRF-TOKEN");

    if (token) {
      headers.set("X-XSRF-TOKEN", token);
    }
  }

  const response = await fetch(
    `${clientEnvironment.NEXT_PUBLIC_API_URL}${path}`,
    {
      ...init,
      credentials: "include",
      headers,
    },
  );

  const payload = response.status === 204 ? null : await response.json();

  if (!response.ok) {
    throw new ApiError(
      payload?.message ?? "The request could not be completed.",
      response.status,
      payload?.errors,
    );
  }

  return payload as T;
}

export function firstError(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return "Something went wrong. Please try again.";
  }

  const validationMessage = Object.values(error.errors).flat()[0];

  return validationMessage ?? error.message;
}
