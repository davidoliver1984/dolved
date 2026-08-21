import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ChatWorkspace } from "@/components/ChatWorkspace";
import type { ChatStreamEvent } from "@/lib/conversation-stream";
import type { Conversation } from "@/lib/conversations";

const emptyConversation: Conversation = {
  id: "conversation-1",
  title: null,
  status: "active",
  created_at: "2026-08-18T12:00:00Z",
  updated_at: "2026-08-18T12:00:00Z",
  messages: [],
  runs: [],
};

afterEach(cleanup);

function services() {
  let receive: ((event: ChatStreamEvent) => void) | undefined;
  const completed: Conversation = {
    ...emptyConversation,
    title: "Medication policy",
    messages: [
      {
        id: "message-user",
        ordinal: 1,
        role: "user",
        kind: null,
        text: "What is the medication rule?",
        in_reply_to_message_id: null,
        created_at: "2026-08-18T12:01:00Z",
      },
      {
        id: "message-assistant",
        ordinal: 2,
        role: "assistant",
        kind: "grounded_answer",
        text: "Use the current procedure.",
        in_reply_to_message_id: "message-user",
        created_at: "2026-08-18T12:02:00Z",
      },
    ],
    runs: [
      {
        id: "run-1",
        user_message_id: "message-user",
        assistant_message_id: "message-assistant",
        retry_of_run_id: null,
        status: "completed",
        failure_code: null,
        delivery_mode: "streaming_parts",
        retryable: false,
        answer: {
          id: "answer-1",
          outcome: "answered",
          unsupported_aspects: [],
          insufficiency_reason: null,
          parts: [
            {
              id: "part-1",
              text: "Use the current procedure.",
              citations: [
                {
                  id: "snapshot-1",
                  document_id: "document-1",
                  cited_text: "The current procedure requires a safety check.",
                  source_provenance: [{ kind: "text", line_start: 4 }],
                },
              ],
            },
          ],
        },
        created_at: "2026-08-18T12:01:00Z",
        completed_at: "2026-08-18T12:02:00Z",
      },
    ],
  };
  let getCalls = 0;
  return {
    api: {
      list: vi.fn().mockResolvedValue([]),
      create: vi.fn().mockResolvedValue(emptyConversation),
      get: vi.fn().mockImplementation(async () => {
        getCalls += 1;
        return getCalls > 1 ? completed : emptyConversation;
      }),
      send: vi.fn().mockResolvedValue({ run_id: "run-1", status: "queued" }),
      retry: vi.fn(),
      cancel: vi.fn(),
      subscribe: vi.fn(
        (
          _workspace: string,
          _conversation: string,
          _run: string,
          next: (event: ChatStreamEvent) => void,
        ) => {
          receive = next;
          return vi.fn();
        },
      ),
    },
    completed,
    emit(event: ChatStreamEvent) {
      receive?.(event);
    },
  };
}

describe("ChatWorkspace", () => {
  it("creates a conversation, sends with Enter and renders streamed grounded citations", async () => {
    const harness = services();
    const user = userEvent.setup();
    render(
      <ChatWorkspace
        services={harness.api}
        workspaceId="workspace-1"
        workspaceName="Alderbridge"
      />,
    );

    const composer = await screen.findByLabelText("Ask a question");
    await user.type(composer, "What is the medication rule?");
    fireEvent.keyDown(composer, { key: "Enter" });

    await waitFor(() => expect(harness.api.send).toHaveBeenCalledOnce());
    expect(harness.api.send).toHaveBeenCalledWith(
      "workspace-1",
      "conversation-1",
      "What is the medication rule?",
      expect.any(String),
    );

    harness.emit({
      sequence: 1,
      type: "answer_part_accepted_for_display",
      provisional: true,
      payload: { text: "Use the current", citations: [{ reference: "cite_early" }] },
    });
    expect(await screen.findByText("Use the current")).not.toBeNull();

    harness.emit({
      sequence: 2,
      type: "answer_completed",
      provisional: false,
      payload: {
        parts: [
          {
            id: "part-1",
            text: "Use the current procedure.",
            citations: [{ id: "snapshot-1", provisional_reference: "cite_final" }],
          },
        ],
      },
    });

    expect(await screen.findByText("Use the current procedure.")).not.toBeNull();
    await user.click(screen.getByText("1 citation"));
    expect(screen.getByText("cite_final")).not.toBeNull();
  });

  it("keeps prior messages visible when a later request fails", async () => {
    const harness = services();
    harness.api.list.mockResolvedValue([{ ...emptyConversation, title: "Existing" }]);
    harness.api.get.mockResolvedValue({
      ...emptyConversation,
      messages: [
        {
          id: "saved",
          ordinal: 1,
          role: "user",
          kind: null,
          text: "Saved question",
          in_reply_to_message_id: null,
          created_at: "2026-08-18T12:00:00Z",
        },
      ],
      runs: [],
    });
    harness.api.send.mockRejectedValue(new Error("offline"));
    const user = userEvent.setup();
    render(
      <ChatWorkspace
        services={harness.api}
        workspaceId="workspace-1"
        workspaceName="Alderbridge"
      />,
    );

    expect(await screen.findByText("Saved question")).not.toBeNull();
    await user.type(screen.getByLabelText("Ask a question"), "Another question");
    await user.click(screen.getByRole("button", { name: "Send" }));

    expect((await screen.findByRole("alert")).textContent).toContain("Something went wrong");
    expect(screen.getByText("Saved question")).not.toBeNull();
  });

  it("renders durable citation evidence after reopening a conversation", async () => {
    const harness = services();
    harness.api.list.mockResolvedValue([
      { ...emptyConversation, title: "Medication policy" },
    ]);
    harness.api.get.mockResolvedValue(harness.completed);
    const user = userEvent.setup();
    render(
      <ChatWorkspace
        services={harness.api}
        workspaceId="workspace-1"
        workspaceName="Alderbridge"
      />,
    );

    await user.click(await screen.findByText("1 citation"));
    expect(screen.getByText("Document document-1")).not.toBeNull();
    expect(
      screen.getByText("The current procedure requires a safety check."),
    ).not.toBeNull();
    await user.click(screen.getByText("Source location"));
    expect(screen.getByText(/line_start/)).not.toBeNull();
  });

  it("fails closed when workspace authorization is revoked mid-stream", async () => {
    const harness = services();
    const user = userEvent.setup();
    render(<ChatWorkspace services={harness.api} workspaceId="workspace-1" workspaceName="Alderbridge" />);

    const composer = await screen.findByLabelText("Ask a question");
    await user.type(composer, "Can I still see this?");
    await user.click(screen.getByRole("button", { name: "Send" }));
    await waitFor(() => expect(harness.api.send).toHaveBeenCalledOnce());

    harness.emit({ sequence: 1, type: "authorization_revoked", provisional: false, payload: {} });

    expect(await screen.findByText("Workspace access ended")).not.toBeNull();
    expect(screen.getByText(/No further answer content will be shown/)).not.toBeNull();
    expect(screen.getByLabelText("Ask a question").hasAttribute("disabled")).toBe(true);
    expect(screen.getByRole("button", { name: "Send" }).hasAttribute("disabled")).toBe(true);
  });
});
