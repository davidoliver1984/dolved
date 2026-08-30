import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { KnowledgeReadinessPanel } from "@/components/KnowledgeReadinessPanel";

afterEach(cleanup);

describe("KnowledgeReadinessPanel", () => {
  it("truthfully explains an empty workspace without presenting upload as readiness", () => {
    render(<KnowledgeReadinessPanel onAsk={vi.fn()} onChooseQuestion={vi.fn()} searchableDocumentCount={0} starterQuestions={[]} workspaceId="workspace-1" />);

    expect(screen.getByText("0 documents currently searchable within your workspace’s knowledge base")).not.toBeNull();
    expect(screen.getByText("Prepare a document before asking Dolved")).not.toBeNull();
    expect(screen.getByText(/Uploaded files are not searchable automatically/)).not.toBeNull();
    expect(screen.getByRole("link", { name: /Upload documents/ }).getAttribute("href")).toBe("/app/workspaces/workspace-1/documents");
    expect(screen.queryByRole("button", { name: /Ask Dolved/ })).toBeNull();
  });

  it("shows exact readiness, deterministic questions and both real actions", () => {
    const choose = vi.fn();
    const ask = vi.fn();
    render(<KnowledgeReadinessPanel onAsk={ask} onChooseQuestion={choose} searchableDocumentCount={2} starterQuestions={[{ family_public_id: "family-1", question: "What are the key points in Medication policy?" }]} workspaceId="workspace-1" />);

    expect(screen.getByText("2 documents currently searchable within your workspace’s knowledge base")).not.toBeNull();
    fireEvent.click(screen.getByRole("button", { name: "What are the key points in Medication policy?" }));
    expect(choose).toHaveBeenCalledWith("What are the key points in Medication policy?");
    fireEvent.click(screen.getByRole("button", { name: /Ask Dolved/ }));
    expect(ask).toHaveBeenCalledOnce();
    expect(screen.getByRole("link", { name: /View searchable documents/ }).getAttribute("href")).toBe("/app/workspaces/workspace-1/documents?searchable=true");
  });

  it("preserves the five user stages and all ten distinct readiness states", () => {
    render(<KnowledgeReadinessPanel onAsk={vi.fn()} onChooseQuestion={vi.fn()} searchableDocumentCount={1} starterQuestions={[]} workspaceId="workspace-1" />);
    fireEvent.click(screen.getByText("How documents become searchable"));
    expect(screen.getByText("Upload documents")).not.toBeNull();
    expect(screen.getByText("Ask grounded questions")).not.toBeNull();
    fireEvent.click(screen.getByText("See all ten readiness states"));
    expect(screen.getByText("1. Selected or uploading")).not.toBeNull();
    expect(screen.getByText("9. Approved, current, indexed and searchable")).not.toBeNull();
    expect(screen.getByText("10. Warning or failed")).not.toBeNull();
  });
});
