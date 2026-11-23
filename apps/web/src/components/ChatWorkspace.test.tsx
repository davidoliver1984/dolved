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
  let disconnect: ((event: Event) => void) | undefined;
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
        provisional_content_retracted: false,
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
                  display_name: "Medication procedure.pdf",
                  type_label: "PDF document",
                  size_bytes: 2048,
                  source_state: "available",
                  source_route: "/app/workspaces/workspace-1/documents/document-1",
                  cited_text: "The current procedure requires a safety check.",
                  source_provenance: [{ kind: "text", line_start: 4 }],
                  provenance_label: "Text source · line 4",
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
          fail: (event: Event) => void,
        ) => {
          receive = next;
          disconnect = fail;
          return vi.fn();
        },
      ),
    },
    completed,
    emit(event: ChatStreamEvent) {
      receive?.(event);
    },
    disconnect() {
      disconnect?.(new Event("error"));
    },
  };
}

describe("ChatWorkspace", () => {
  it("uses the configured chat welcome copy in the page header and empty state", async () => {
    const fixture = services();
    render(<ChatWorkspace
      initialConversationId={null}
      services={fixture.api}
      showConversationNavigation={false}
      welcomeHeading="Ask Cherish"
      welcomeIntroduction="Ask about approved Cherish policies and procedures."
      workspaceId="workspace-1"
      workspaceName="Dolved pilot"
    />);

    expect(await screen.findByRole("heading", { level: 1, name: "Ask Cherish" })).not.toBeNull();
    expect(screen.getAllByText("Ask Cherish")).toHaveLength(2);
    expect(screen.getAllByText("Ask about approved Cherish policies and procedures.")).toHaveLength(2);
  });

  it("binds an explicit registered site when creating a conversation", async () => {
    const fixture = services();
    const user = userEvent.setup();
    render(<ChatWorkspace
      initialConversationId={null}
      locations={[{ public_id: "09d65ad7-299f-46f7-b9f1-a0ae564cd31a", name: "Oakfield" }]}
      services={fixture.api}
      workspaceId="workspace-1"
      workspaceName="Care"
    />);

    await user.selectOptions(await screen.findByLabelText("Site context"), "09d65ad7-299f-46f7-b9f1-a0ae564cd31a");
    await user.type(screen.getByLabelText("Ask a question"), "What is the local rule?");
    await user.click(screen.getByRole("button", { name: "Send" }));

    await waitFor(() => expect(fixture.api.create).toHaveBeenCalledWith(
      "workspace-1",
      "09d65ad7-299f-46f7-b9f1-a0ae564cd31a",
    ));
  });

  it("labels Dify context as retrieved sources rather than claim citations", async () => {
    const fixture = services();
    fixture.completed.site_context = { scope_key: "global", location_id: null, name: "Organisation-wide only" };
    fixture.completed.runs![0].answer = {
      id: "answer-1",
      outcome: "answered",
      presentation: "saved-message-v1",
      text: "Use the current procedure.",
      sources_retrieved: [{
        id: "source-1",
        rank: 1,
        score: 0.812,
        document_id: "document-1",
        display_name: "Data Protection Policy.pdf",
        source_state: "available",
        source_route: "/app/workspaces/workspace-1/documents/document-1",
        retrieved_text: "Personal devices must not be used.",
      }],
    };
    fixture.api.get.mockResolvedValue(fixture.completed);
    const user = userEvent.setup();
    render(<ChatWorkspace initialConversationId="conversation-1" services={fixture.api} workspaceId="workspace-1" workspaceName="Care" />);

    const sourceSummary = await screen.findByText("Sources retrieved");
    expect(screen.getByText(/not claim-level citations/i)).not.toBeNull();
    expect(sourceSummary.closest("details")?.open).toBe(false);
    await user.click(sourceSummary);
    expect(sourceSummary.closest("details")?.open).toBe(true);
    expect(screen.getByRole("list", { name: "Sources retrieved" })).not.toBeNull();
    await user.click(screen.getByRole("button", { name: /Data Protection Policy.pdf/ }));
    expect(screen.getByText("Personal devices must not be used.")).not.toBeNull();
    expect(screen.getByText("Site context:").parentElement?.textContent).toContain("Organisation-wide only");
    expect(screen.queryByRole("list", { name: "Answer sources" })).toBeNull();
  });

  it("observes an existing pending pilot run after route remount without sending again", async () => {
    const fixture = services();
    const pending = structuredClone(fixture.completed);
    pending.messages = pending.messages!.slice(0,1);
    Object.assign(pending.runs![0], { presentation: "saved-message-v1", status: "generating", completed_at: null, assistant_message_id: null, answer: null });
    fixture.completed.runs![0].presentation = "saved-message-v1";
    fixture.api.get.mockResolvedValueOnce(pending).mockResolvedValue(fixture.completed);
    const view = render(<ChatWorkspace initialConversationId="conversation-1" services={fixture.api} workspaceId="workspace-1" workspaceName="Care" />);
    await waitFor(() => expect(fixture.api.subscribe).toHaveBeenCalledOnce());
    expect(view.container.querySelector('[data-chat-pending="true"]')).not.toBeNull();
    fixture.emit({ sequence: 4, type: "answer_completed", provisional: false, payload: {} });
    await waitFor(() => expect(view.container.querySelector('[data-completion-path="sse"]')).not.toBeNull());
    expect(screen.getByRole("status").textContent).toBe("Answer complete.");
    expect(fixture.api.send).not.toHaveBeenCalled();
  });

  it("uses neutral Dify progress and releases the interface when the user stops waiting", async () => {
    const fixture = services();
    const pending = structuredClone(fixture.completed);
    pending.messages = pending.messages!.slice(0, 1);
    Object.assign(pending.runs![0], {
      presentation: "saved-message-v1",
      status: "retrieving",
      completed_at: null,
      assistant_message_id: null,
      answer: null,
      interaction: { progress: "dify_blocking", stop_action: "stop_waiting" },
    });
    fixture.api.get.mockResolvedValue(pending);
    const user = userEvent.setup();
    const view = render(<ChatWorkspace assistantName="Cherish" initialConversationId="conversation-1" services={fixture.api} workspaceId="workspace-1" workspaceName="Care" />);

    await waitFor(() => expect(view.container.querySelector('[data-chat-progress="Searching and preparing…"]')).toBeTruthy());
    fixture.emit({ sequence: 2, type: "run_progress", provisional: false, payload: { stage: "generating" } });
    expect(view.container.querySelector('[data-chat-progress="Searching and preparing…"]')).toBeTruthy();
    await user.click(screen.getByRole("button", { name: "Stop waiting" }));

    await waitFor(() => expect(fixture.api.cancel).toHaveBeenCalledWith("workspace-1", "conversation-1", "run-1"));
    expect(view.container.querySelector('[data-chat-pending="false"]')).toBeTruthy();
    expect(screen.getByText(/Provider processing may continue/)).toBeTruthy();
  });

  it("keeps a newly created conversation mounted until its live run settles", async () => {
    const fixture = services();
    let resolveSend!: (receipt: {run_id: string; status: string}) => void;
    fixture.api.send.mockImplementation(()=>new Promise((resolve)=>{resolveSend=resolve;}));
    const navigate=vi.fn();
    render(<ChatWorkspace initialConversationId={null} onConversationCreated={navigate} services={fixture.api} workspaceId="workspace-1" workspaceName="Care" />);
    const input=await screen.findByLabelText("Ask a question");
    fireEvent.change(input,{target:{value:"What is the procedure?"}});fireEvent.keyDown(input,{key:"Enter"});
    await waitFor(()=>expect(fixture.api.send).toHaveBeenCalledOnce());
    expect(navigate).not.toHaveBeenCalled();
    resolveSend({run_id:"run-1",status:"queued"});
    await waitFor(()=>expect(fixture.api.subscribe).toHaveBeenCalledOnce());
    expect(screen.getByText("Sending your question…")).toBeTruthy();
    expect(navigate).not.toHaveBeenCalled();
    fixture.emit({ sequence: 4, type: "answer_completed", provisional: false, payload: {} });
    await waitFor(()=>expect(navigate).toHaveBeenCalledExactlyOnceWith("conversation-1"));
    expect(screen.getByText("Use the current procedure.")).toBeTruthy();
  });
  it.each(["History is outside this pilot's scope.", "The request exceeds answer capacity; nothing was truncated.", "The answer could not be generated safely."])("omits legacy outcome badge for saved presentation: %s", async (text) => {
    const fixture = services();
    fixture.completed.messages![1].text = text;
    fixture.completed.runs![0].answer = { id: "answer-1", outcome: "insufficient_evidence", presentation: "saved-message-v1", text, citations: [] };
    fixture.api.get.mockResolvedValue(fixture.completed);
    render(<ChatWorkspace initialConversationId="conversation-1" services={fixture.api} workspaceId="workspace-1" workspaceName="Care" />);
    expect(await screen.findByText(text)).toBeTruthy();
    expect(screen.queryByText("insufficient evidence")).toBeNull();
    expect(screen.getByRole("status").textContent).toBe("Answer complete.");
  });

  it("settles a completed persisted submission without waiting for a missed SSE event or resending", async () => {
    const fixture = services();
    fixture.completed.runs![0].presentation = "saved-message-v1";
    fixture.api.get.mockResolvedValue(fixture.completed);
    const view = render(<ChatWorkspace initialConversationId={null} services={fixture.api} workspaceId="workspace-1" workspaceName="Care" />);
    const input = await screen.findByLabelText("Ask a question");
    fireEvent.change(input, { target: { value: "What is the current procedure?" } });
    fireEvent.keyDown(input, { key: "Enter" });
    await waitFor(() => expect(view.container.querySelector('[data-completion-path="persisted_state"]')).not.toBeNull());
    expect(screen.queryByText("Sending your question…")).toBeNull();
    expect(screen.getByRole("status").textContent).toBe("Answer complete.");
    expect(fixture.api.send).toHaveBeenCalledOnce();
    expect(fixture.api.subscribe).not.toHaveBeenCalled();
  });

  it("settles from persisted recovery after a lost terminal event and ignores late progress", async () => {
    const fixture = services();
    fixture.completed.runs![0].presentation = "saved-message-v1";
    const view = render(<ChatWorkspace initialConversationId={null} services={fixture.api} workspaceId="workspace-1" workspaceName="Care" />);
    const input = await screen.findByLabelText("Ask a question");
    fireEvent.change(input, { target: { value: "What is the current procedure?" } });
    fireEvent.keyDown(input, { key: "Enter" });
    await waitFor(() => expect(fixture.api.subscribe).toHaveBeenCalledOnce());
    fixture.disconnect();
    await waitFor(() => expect(view.container.querySelector('[data-completion-path="persisted_recovery"]')).not.toBeNull());
    fixture.emit({ sequence: 1, type: "run_progress", provisional: false, payload: {stage:"retrieving"} });
    expect(screen.getByRole("status").textContent).toBe("Answer complete.");
    expect(view.container.querySelector('[data-chat-pending="false"]')).not.toBeNull();
    expect(fixture.api.send).toHaveBeenCalledOnce();
  });
  it("renders canonical saved pilot text once with citations and no competing parts or notices", async () => {
    const fixture = services();
    const saved = "Wear gloves.\n\nUnanswered or unconfirmed parts\n\n- Earlier policy is outside current policy scope.\n- Location coverage not established for Cedar outreach.\n<script>not executable</script>";
    fixture.completed.messages![1].text = saved;
    const answer = fixture.completed.runs![0].answer!;
    answer.presentation = "saved-message-v1";
    answer.text = saved;
    answer.citations = answer.parts![0].citations;
    answer.parts = [{ id: "ignored", text: "COMPETING RAW TEXT", citations: [] }];
    answer.unsupported_aspects = ["DUPLICATE NOTICE"];
    answer.insufficiency_reason = "DUPLICATE FAILURE";
    fixture.api.get.mockResolvedValue(fixture.completed);
    const view = render(<ChatWorkspace initialConversationId="conversation-1" services={fixture.api} workspaceId="workspace-1" workspaceName="Care" />);
    await waitFor(() => expect(view.container.querySelector("[data-canonical-answer]")?.textContent).toBe(saved));
    expect(view.container.querySelectorAll("[data-canonical-answer]")).toHaveLength(1);
    expect(screen.queryByText("COMPETING RAW TEXT")).toBeNull();
    expect(screen.queryByText("DUPLICATE NOTICE")).toBeNull();
    expect(screen.queryByText("DUPLICATE FAILURE")).toBeNull();
    expect(view.container.querySelector("script")).toBeNull();
    expect(screen.getByRole("list", { name: "Answer sources" })).toBeTruthy();
    expect(fixture.api.send).not.toHaveBeenCalled();
    view.unmount();
    const reloaded = render(<ChatWorkspace initialConversationId="conversation-1" services={fixture.api} workspaceId="workspace-1" workspaceName="Care" />);
    await waitFor(() => expect(reloaded.container.querySelector("[data-canonical-answer]")?.textContent).toBe(saved));
    expect(fixture.api.send).not.toHaveBeenCalled();
  });
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
    await user.click(await screen.findByRole("button", { name: "[1], show source evidence" }));
    expect(await screen.findByText("Medication procedure.pdf")).not.toBeNull();
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

    await user.click(await screen.findByRole("button", { name: "[1], show source evidence" }));
    expect(screen.getByText("Medication procedure.pdf")).not.toBeNull();
    expect(
      screen.getByText("The current procedure requires a safety check."),
    ).not.toBeNull();
    expect(screen.getByText("Text source · line 4")).not.toBeNull();
    expect(screen.getByRole("link", { name: "View source" }).getAttribute("href")).toBe("/app/workspaces/workspace-1/documents/document-1");
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

  it.each([
    ["internal_failure", false, "Answer could not be completed"],
    ["run_timeout", false, "Answer attempt timed out"],
    ["stream_protocol_failure", true, "Provisional answer retracted"],
  ])("renders the typed %s terminal presentation", async (failureCode, retracted, heading) => {
    const harness = services();
    harness.api.list.mockResolvedValue([{ ...emptyConversation, title: "Failed answer" }]);
    harness.api.get.mockResolvedValue({
      ...emptyConversation,
      messages: [{ id: "question", ordinal: 1, role: "user", kind: null, text: "Question", in_reply_to_message_id: null, created_at: "2026-08-18T12:00:00Z" }],
      runs: [{
        id: `run-${failureCode}`,
        user_message_id: "question",
        assistant_message_id: null,
        retry_of_run_id: null,
        status: "failed",
        failure_code: failureCode,
        delivery_mode: "streaming_parts",
        retryable: true,
        provisional_content_retracted: retracted,
        answer: null,
        created_at: "2026-08-18T12:00:00Z",
        completed_at: "2026-08-18T12:01:00Z",
      }],
    });

    render(<ChatWorkspace services={harness.api} workspaceId="workspace-1" workspaceName="Alderbridge" />);

    expect(await screen.findByText(heading)).not.toBeNull();
    expect(screen.getByRole("button", { name: "Retry answer" })).not.toBeNull();
    if (retracted) expect(screen.getByText(/provisional content was removed/i)).not.toBeNull();
  });

  it("uses one bounded live region while keeping the transcript navigable", async () => {
    const harness = services();
    const user = userEvent.setup();
    render(<ChatWorkspace services={harness.api} workspaceId="workspace-1" workspaceName="Alderbridge" />);

    const transcript = (await screen.findByText("What do you need to know?")).closest("div[aria-busy]");
    expect(transcript?.hasAttribute("aria-live")).toBe(false);
    expect(screen.getAllByRole("status")).toHaveLength(1);

    await user.type(screen.getByLabelText("Ask a question"), "Question");
    await user.click(screen.getByRole("button", { name: "Send" }));
    await waitFor(() => expect(harness.api.send).toHaveBeenCalledOnce());
    harness.emit({ sequence: 1, type: "run_progress", provisional: false, payload: { stage: "retrieving" } });
    await waitFor(() => expect(screen.getByRole("status").textContent).toBe("Finding eligible evidence…"));

    harness.disconnect();
    await waitFor(() => expect(screen.getByRole("status").textContent).toBe("Live updates interrupted. Reconnecting."));
    expect(await screen.findByText(/live update connection was interrupted/i)).not.toBeNull();
  });
});
