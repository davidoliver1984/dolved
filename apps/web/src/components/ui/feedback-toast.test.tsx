import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { FeedbackToastProvider } from "@/components/ui/feedback-toast";

describe("FeedbackToastProvider", () => {
  afterEach(cleanup);

  it("uses the dedicated success colour for successful actions", () => {
    render(<FeedbackToastProvider><div>Content</div></FeedbackToastProvider>);

    fireEvent(window, new CustomEvent("dolved:feedback", { detail: { message: "Document approved.", tone: "success" } }));

    const toast = screen.getByText("Document approved.").parentElement;
    expect(toast?.className).toContain("bg-status-success");
    expect(toast?.className).toContain("text-status-success-foreground");
    expect(toast?.className).not.toContain("bg-[var(--ring)]");
  });
});
