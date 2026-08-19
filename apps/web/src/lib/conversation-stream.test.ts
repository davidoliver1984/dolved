import { afterEach, describe, expect, it, vi } from "vitest";

import { subscribeToGenerationRun } from "@/lib/conversation-stream";

class FakeEventSource {
  static latest: FakeEventSource;
  readonly listeners = new Map<string, EventListener>();
  onerror: ((event: Event) => void) | null = null;
  closed = false;

  constructor(
    public readonly url: string,
    public readonly options: EventSourceInit,
  ) {
    FakeEventSource.latest = this;
  }

  addEventListener(type: string, listener: EventListener) {
    this.listeners.set(type, listener);
  }

  emit(type: string, data: object) {
    this.listeners.get(type)?.(
      new MessageEvent(type, { data: JSON.stringify(data) }),
    );
  }

  close() {
    this.closed = true;
  }
}

describe("conversation stream", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("uses credentialed SSE, de-duplicates sequences and closes on completion", () => {
    vi.stubGlobal("EventSource", FakeEventSource);
    const received = vi.fn();
    subscribeToGenerationRun("workspace", "conversation", "run", received, vi.fn());
    const source = FakeEventSource.latest;
    expect(source.options.withCredentials).toBe(true);
    source.emit("run_progress", { sequence: 1, type: "run_progress", provisional: false, payload: { stage: "retrieving" } });
    source.emit("run_progress", { sequence: 1, type: "run_progress", provisional: false, payload: { stage: "retrieving" } });
    source.emit("answer_completed", { sequence: 2, type: "answer_completed", provisional: false, payload: {} });
    expect(received).toHaveBeenCalledTimes(2);
    expect(source.closed).toBe(true);
  });

  it("treats membership revocation as a terminal stream event", () => {
    vi.stubGlobal("EventSource", FakeEventSource);
    const received = vi.fn();
    subscribeToGenerationRun("workspace", "conversation", "run", received, vi.fn());
    const source = FakeEventSource.latest;
    source.emit("authorization_revoked", {
      sequence: 1,
      type: "authorization_revoked",
      provisional: false,
      payload: { code: "workspace_membership_revoked" },
    });
    expect(received).toHaveBeenCalledOnce();
    expect(source.closed).toBe(true);
  });
});
