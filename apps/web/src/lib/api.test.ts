import { afterEach, describe, expect, it, vi } from "vitest";
import { apiFetch, ApiError, firstError } from "@/lib/api";

describe("apiFetch", () => {
  afterEach(() => {
    document.cookie = "XSRF-TOKEN=; Max-Age=0; Path=/";
  });

  it("gets Sanctum CSRF state and returns the readable XSRF token on writes", async () => {
    document.cookie = "XSRF-TOKEN=signed%3Dtoken; Path=/";
    const fetchMock = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(
        Response.json({ data: { user: { id: 1 } } }, { status: 200 }),
      );

    await apiFetch("/api/auth/login", {
      method: "POST",
      body: JSON.stringify({ email: "david@example.com" }),
    });

    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(fetchMock.mock.calls[0][0]).toBe(
      "http://localhost:8000/sanctum/csrf-cookie",
    );

    const request = fetchMock.mock.calls[1][1];
    const headers = new Headers(request?.headers);

    expect(request?.credentials).toBe("include");
    expect(headers.get("X-XSRF-TOKEN")).toBe("signed=token");
  });

  it("does not make a CSRF bootstrap request for safe reads", async () => {
    const fetchMock = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(Response.json({ data: { user: null } }));

    await apiFetch("/api/auth/user");

    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("reports an unreachable API without collapsing it to a generic error", async () => {
    vi.spyOn(globalThis, "fetch").mockRejectedValue(new TypeError("fetch failed"));

    await expect(
      apiFetch("/api/auth/register", {
        method: "POST",
        body: JSON.stringify({ email: "david@example.test" }),
      }),
    ).rejects.toMatchObject({
      message: "Dolved could not reach the API. Please try again.",
      status: 0,
    });
  });

  it("reports redirected session responses explicitly", async () => {
    document.cookie = "XSRF-TOKEN=signed%3Dtoken; Path=/";
    vi.spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce({
        redirected: true,
        status: 200,
      } as Response);

    await expect(
      apiFetch("/api/auth/register", {
        method: "POST",
        body: JSON.stringify({ email: "david@example.test" }),
      }),
    ).rejects.toMatchObject({
      message: "Your session changed. Refresh the page and try again.",
      status: 200,
    });
  });

  it("reports non-JSON API responses explicitly", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValueOnce(
      new Response("<html>not JSON</html>", {
        status: 502,
        headers: { "Content-Type": "text/html" },
      }),
    );

    await expect(apiFetch("/api/auth/user")).rejects.toMatchObject({
      message: "Dolved received an unexpected response. Please try again.",
      status: 502,
    });
  });

  it("prefers useful validation errors returned by Laravel", () => {
    const error = new ApiError("Invalid input.", 422, {
      email: ["Enter a valid email address."],
    });

    expect(firstError(error)).toBe("Enter a valid email address.");
  });
});
