import { describe, expect, it } from "vitest";
import { queryFromSavedViewDefinition, savedViewDefinitionFromQuery } from "@/lib/saved-view";

describe("saved-view URL conversion", () => {
  it("captures only the versioned library fields and maps page size", () => {
    expect(savedViewDefinitionFromQuery({
      search: "medication",
      status: "indexed",
      category: "11111111-1111-4111-8111-111111111111",
      sort: "title",
      direction: "asc",
      per_page: "50",
      historical: "true",
      page: "9",
      unsupported: "ignored",
    })).toEqual({
      search: "medication",
      filters: {
        category: "11111111-1111-4111-8111-111111111111",
        status: "indexed",
      },
      sort: "title",
      direction: "asc",
      page_size: 50,
      historical: true,
    });
  });

  it("opens the definition as a fresh live-library query", () => {
    expect(queryFromSavedViewDefinition({
      search: "policy",
      filters: { owner: "22222222-2222-4222-8222-222222222222", review_status: "due_soon" },
      page_size: 100,
      historical: true,
    })).toEqual({
      search: "policy",
      owner: "22222222-2222-4222-8222-222222222222",
      review_status: "due_soon",
      per_page: "100",
      historical: "true",
    });
  });
});
