import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const { initialConversationMock, notFoundMock, readinessMock, starterQuestionsMock, userWorkspaceMock } = vi.hoisted(() => ({
  initialConversationMock: vi.fn(),
  notFoundMock: vi.fn(() => {
    throw new Error("NEXT_NOT_FOUND");
  }),
  userWorkspaceMock: vi.fn(),
  readinessMock: vi.fn(),
  starterQuestionsMock: vi.fn(),
}));

vi.mock("next/navigation", () => ({ notFound: notFoundMock }));
vi.mock("@/lib/server-api", () => ({
  initialConversation: initialConversationMock,
  userWorkspace: userWorkspaceMock,
  workspaceKnowledgeReadiness: readinessMock,
  workspaceStarterQuestions: starterQuestionsMock,
}));
vi.mock("@/components/RoutedChatWorkspace", () => ({
  RoutedChatWorkspace: ({ conversationId, workspaceId }: { conversationId: string; workspaceId: string }) => (
    <div>{workspaceId}:{conversationId}</div>
  ),
}));

import ConversationPage from "./page";

const workspace = {
  public_id: "workspace-1",
  name: "Alderbridge",
  slug: "alderbridge",
  role: "owner",
};
const conversation = {
  id: "conversation-1",
  title: "Policy",
  status: "active",
  created_at: "2026-08-21T10:00:00Z",
  updated_at: "2026-08-21T10:00:00Z",
  messages: [],
  runs: [],
};

describe("conversation route ownership", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    userWorkspaceMock.mockResolvedValue(workspace);
    initialConversationMock.mockResolvedValue(conversation);
    readinessMock.mockResolvedValue({ searchable_document_count: 1 });
    starterQuestionsMock.mockResolvedValue([]);
  });

  it("renders only after both workspace and conversation identities validate", async () => {
    render(await ConversationPage({ params: Promise.resolve({ workspacePublicId: "workspace-1", conversationPublicId: "conversation-1" }) }));
    expect(screen.getByText("workspace-1:conversation-1")).not.toBeNull();
    expect(initialConversationMock).toHaveBeenCalledWith("workspace-1", "conversation-1");
  });

  it.each(["invalid", "deleted", "cross-workspace"])(
    "uses tenant-safe not found for a %s conversation identity",
    async () => {
      initialConversationMock.mockResolvedValue(null);
      await expect(ConversationPage({ params: Promise.resolve({ workspacePublicId: "workspace-1", conversationPublicId: "concealed" }) })).rejects.toThrow("NEXT_NOT_FOUND");
      expect(notFoundMock).toHaveBeenCalledOnce();
    },
  );
});
