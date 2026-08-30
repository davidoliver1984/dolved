import { forwardedAuthCookieHeader } from "@/lib/auth-cookies";
import { serverEnvironment } from "@/lib/env/server";

type Context = { params: Promise<{ documentPublicId: string; workspacePublicId: string }> };

async function proxy(request: Request, context: Context): Promise<Response> {
  const { documentPublicId, workspacePublicId } = await context.params;
  const cookie = await forwardedAuthCookieHeader();
  const headers = new Headers({ Accept: request.headers.get("accept") ?? "*/*", Origin: serverEnvironment.FRONTEND_URL });
  if (cookie) headers.set("Cookie", cookie);
  const range = request.headers.get("range");
  if (range) headers.set("Range", range);
  const response = await fetch(`${serverEnvironment.API_INTERNAL_URL}/api/workspaces/${encodeURIComponent(workspacePublicId)}/documents/${encodeURIComponent(documentPublicId)}/source`, { cache: "no-store", headers, method: request.method });
  const outgoing = new Headers();
  for (const name of ["accept-ranges", "content-disposition", "content-length", "content-range", "content-type", "x-content-type-options"]) {
    const value = response.headers.get(name);
    if (value) outgoing.set(name, value);
  }
  if (new URL(request.url).searchParams.get("download") === "1") {
    const disposition = outgoing.get("content-disposition")?.replace(/^inline;/, "attachment;");
    if (disposition) outgoing.set("Content-Disposition", disposition);
  }
  return new Response(request.method === "HEAD" ? null : response.body, { headers: outgoing, status: response.status });
}

export const GET = proxy;
export const HEAD = proxy;
