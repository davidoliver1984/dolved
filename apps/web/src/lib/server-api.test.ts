import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const { forwardedAuthCookieHeaderMock } = vi.hoisted(() => ({
  forwardedAuthCookieHeaderMock: vi.fn(),
}));

vi.mock("server-only", () => ({}));
vi.mock("@/lib/auth-cookies", () => ({
  forwardedAuthCookieHeader: forwardedAuthCookieHeaderMock,
}));

import {
  currentUser,
  hasPlatformOperationsAccess,
  initialConversation,
  initialWorkspaceDocument,
  platformAccess,
  platformOperations,
  userWorkspace,
  userWorkspaces,
  workspaceUploadConfiguration,
} from "@/lib/server-api";

const user = {
  id: 1,
  name: "David Oliver",
  email: "david@example.test",
  email_verified_at: "2026-08-02T10:00:00Z",
};

const workspace = {
  public_id: "11111111-1111-4111-8111-111111111111",
  name: "Atlas Research",
  slug: "atlas-research",
  role: "owner" as const,
};

const uploadConfiguration = {
  formats: { pdf: ["application/pdf"] },
  max_upload_bytes: 25 * 1024 * 1024,
  upload_concurrency: 3,
};

describe("server-side Laravel API functions", () => {
  beforeEach(() => {
    forwardedAuthCookieHeaderMock.mockReset();
    forwardedAuthCookieHeaderMock.mockResolvedValue(
      "rag-platform-session=session; XSRF-TOKEN=xsrf",
    );
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it.each([401, 403, 500])(
    "preserves platform access status %i",
    async (status) => {
      vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response(null, { status }),
      );

      await expect(platformAccess()).resolves.toMatchObject({ status });
    },
  );

  it("fails platform-operation discovery soft and preserves bounded access states", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 404 }));
    fetchMock.mockRejectedValueOnce(new TypeError("backend unavailable"));
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 404 }));
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 503 }));

    await expect(hasPlatformOperationsAccess()).resolves.toBe(false);
    await expect(hasPlatformOperationsAccess()).resolves.toBe(false);
    await expect(platformOperations()).resolves.toEqual({ status: "concealed" });
    await expect(platformOperations()).resolves.toEqual({ status: "unavailable" });
  });

  it("loads only the server-owned platform health endpoint", async () => {
    const snapshot = {
      status: "available",
      health_status: "healthy",
      as_of: "2026-08-20T12:00:00Z",
      freshness: "current",
      metrics: {},
      grafana_url: "http://127.0.0.1:3001",
    };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      Response.json({ data: snapshot }),
    );

    await expect(platformOperations()).resolves.toEqual({ status: "ok", data: snapshot });
    expect(String(fetchMock.mock.calls[0][0])).toMatch(/\/api\/platform\/operations\/health$/);
    expect(String(fetchMock.mock.calls[0][0])).not.toContain("query=");
  });

  it("forwards only allowlisted cookies with the required server request options", async () => {
    const fetchMock = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValue(Response.json({ data: { user } }));

    await currentUser();

    const [url, init] = fetchMock.mock.calls[0];
    const headers = new Headers(init?.headers);

    expect(String(url)).toMatch(/\/api\/auth\/user$/);
    expect(init?.cache).toBe("no-store");
    expect(headers.get("Accept")).toBe("application/json");
    expect(headers.get("Cookie")).toBe(
      "rag-platform-session=session; XSRF-TOKEN=xsrf",
    );
    expect(headers.get("Origin")).toMatch(/^https?:\/\//);
  });

  it("does not send an empty Cookie header", async () => {
    forwardedAuthCookieHeaderMock.mockResolvedValue("");
    const fetchMock = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValue(new Response(null, { status: 401 }));

    await currentUser();

    const headers = new Headers(fetchMock.mock.calls[0][1]?.headers);
    expect(headers.has("Cookie")).toBe(false);
  });

  it("unwraps the current user and treats only 401 as signed out", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    fetchMock.mockResolvedValueOnce(Response.json({ data: { user } }));
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 401 }));
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 500 }));

    await expect(currentUser()).resolves.toEqual(user);
    await expect(currentUser()).resolves.toBeNull();
    await expect(currentUser()).rejects.toThrow(
      "The current account is unavailable.",
    );
  });

  it("unwraps workspace lists and rejects forbidden responses", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    fetchMock.mockResolvedValueOnce(Response.json({ data: [workspace] }));
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 403 }));

    await expect(userWorkspaces()).resolves.toEqual([workspace]);
    await expect(userWorkspaces()).rejects.toThrow(
      "The workspace list is unavailable.",
    );
  });

  it("unwraps one workspace, encodes its ID and maps 404 to null", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    fetchMock.mockResolvedValueOnce(Response.json({ data: workspace }));
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 404 }));

    await expect(userWorkspace("workspace/with spaces")).resolves.toEqual(
      workspace,
    );
    expect(String(fetchMock.mock.calls[0][0])).toMatch(
      /\/api\/workspaces\/workspace%2Fwith%20spaces$/,
    );
    await expect(userWorkspace("missing")).resolves.toBeNull();
  });

  it("rejects backend failures while loading one workspace", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(null, { status: 503 }),
    );

    await expect(userWorkspace(workspace.public_id)).rejects.toThrow(
      "The workspace is unavailable.",
    );
  });

  it("loads upload configuration and maps a missing workspace to null", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    fetchMock.mockResolvedValueOnce(
      Response.json({ data: uploadConfiguration }),
    );
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 404 }));

    await expect(
      workspaceUploadConfiguration(workspace.public_id),
    ).resolves.toEqual(uploadConfiguration);
    await expect(workspaceUploadConfiguration("missing")).resolves.toBeNull();
  });

  it("rejects backend failures while loading upload configuration", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(null, { status: 500 }),
    );

    await expect(
      workspaceUploadConfiguration(workspace.public_id),
    ).rejects.toThrow("Document upload configuration is unavailable.");
  });

  it("loads a conversation by encoded workspace and conversation identity and maps concealment to null", async () => {
    const conversation = {
      id: "conversation-1",
      title: "Current policy",
      status: "active",
      created_at: "2026-08-21T10:00:00Z",
      updated_at: "2026-08-21T10:00:00Z",
      messages: [],
      runs: [],
    };
    const fetchMock = vi.spyOn(globalThis, "fetch");
    fetchMock.mockResolvedValueOnce(Response.json({ data: conversation }));
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 404 }));

    await expect(initialConversation("workspace/one", "conversation one")).resolves.toEqual(conversation);
    expect(String(fetchMock.mock.calls[0][0])).toMatch(
      /\/api\/workspaces\/workspace%2Fone\/conversations\/conversation%20one$/,
    );
    await expect(initialConversation("workspace", "concealed")).resolves.toBeNull();
  });

  it("loads an authorised live document and maps removed or concealed documents to null", async () => {
    const document = {
      public_id: "document-1",
      source_filename: "Medication procedure.pdf",
      media_type: "application/pdf",
      size_bytes: 2048,
      status: "indexed",
      governance_status: "approved",
      failure_category: null,
      failure_message: null,
      extraction_warnings: [],
      capabilities: { retry: false, delete: true },
      created_at: "2026-08-21T10:00:00Z",
    };
    const fetchMock = vi.spyOn(globalThis, "fetch");
    fetchMock.mockResolvedValueOnce(Response.json({ data: document }));
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 404 }));

    await expect(initialWorkspaceDocument("workspace/one", "document one")).resolves.toEqual(document);
    expect(String(fetchMock.mock.calls[0][0])).toMatch(
      /\/api\/workspaces\/workspace%2Fone\/documents\/document%20one$/,
    );
    await expect(initialWorkspaceDocument("workspace", "removed")).resolves.toBeNull();
  });
});
