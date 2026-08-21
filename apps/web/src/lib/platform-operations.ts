export const PLATFORM_OPERATION_PATHS = [
  "/app/platform/operations",
  "/app/platform/operations/alerts",
  "/app/platform/operations/telemetry",
  "/app/platform/operations/policy",
] as const;

export type PlatformOperationsPath = (typeof PLATFORM_OPERATION_PATHS)[number];

export function isPlatformOperationsPath(value: unknown): value is PlatformOperationsPath {
  return typeof value === "string" && PLATFORM_OPERATION_PATHS.some((path) => path === value);
}
