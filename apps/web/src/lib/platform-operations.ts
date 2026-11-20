export const PLATFORM_OPERATION_PATHS = [
  "/app/platform/operations",
  "/app/platform/operations/alerts",
  "/app/platform/operations/telemetry",
  "/app/platform/operations/policy",
] as const;

export type PlatformOperationsPath = (typeof PLATFORM_OPERATION_PATHS)[number];
export type PlatformAdministrationPath = PlatformOperationsPath | "/app/platform/tenants" | `/app/platform/tenants/${string}/branding`;

export function isPlatformOperationsPath(value: unknown): value is PlatformAdministrationPath {
  return typeof value === "string" && (
    PLATFORM_OPERATION_PATHS.some((path) => path === value)
    || value === "/app/platform/tenants"
    || /^\/app\/platform\/tenants\/[0-9a-f-]{36}\/branding$/i.test(value)
  );
}
