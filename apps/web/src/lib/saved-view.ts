import type { SavedViewDefinition } from "@/lib/api";

type Query = Record<string, string | string[] | undefined>;

function one(value: string | string[] | undefined): string {
  return Array.isArray(value) ? (value[0] ?? "") : (value ?? "");
}

export function savedViewDefinitionFromQuery(query: Query): SavedViewDefinition {
  const filters: NonNullable<SavedViewDefinition["filters"]> = {};
  for (const key of ["category", "applicability", "owner", "review_status", "status"] as const) {
    const value = one(query[key]);
    if (value) Object.assign(filters, { [key]: value });
  }
  const pageSize = Number(one(query.per_page));
  const definition: SavedViewDefinition = {};
  if (one(query.search)) definition.search = one(query.search);
  if (Object.keys(filters).length) definition.filters = filters;
  if (one(query.sort)) definition.sort = one(query.sort) as SavedViewDefinition["sort"];
  if (one(query.direction)) definition.direction = one(query.direction) as SavedViewDefinition["direction"];
  if ([25, 50, 100].includes(pageSize)) definition.page_size = pageSize as 25 | 50 | 100;
  if (one(query.historical) === "true") definition.historical = true;

  return definition;
}

export function queryFromSavedViewDefinition(definition: SavedViewDefinition): Record<string, string> {
  const query: Record<string, string> = {};
  if (definition.search) query.search = definition.search;
  if (definition.sort) query.sort = definition.sort;
  if (definition.direction) query.direction = definition.direction;
  if (definition.page_size) query.per_page = String(definition.page_size);
  if (definition.historical) query.historical = "true";
  for (const [key, value] of Object.entries(definition.filters ?? {})) {
    if (value) query[key] = String(value);
  }
  return query;
}
