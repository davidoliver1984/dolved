import "server-only";

type LogLevel = "debug" | "info" | "warning" | "error";
type SafeValue = string | number | boolean | null;
type LogContext = Record<string, unknown>;

const EVENT_NAME = /^[a-z][a-z0-9_.-]*\.v[1-9][0-9]*$/;
const SAFE_FIELDS = new Set([
  "conversation_id",
  "correlation_id",
  "document_id",
  "duration_ms",
  "duration_seconds",
  "failure_class",
  "operation_kind",
  "outcome",
  "request_id",
  "run_id",
  "span_id",
  "trace_id",
  "workspace_id",
]);

function safeContext(context: LogContext): Record<string, SafeValue> {
  return Object.fromEntries(
    Object.entries(context).flatMap(([key, value]) => {
      if (!SAFE_FIELDS.has(key)) {
        return [];
      }
      if (
        value === null ||
        typeof value === "string" ||
        typeof value === "number" ||
        typeof value === "boolean"
      ) {
        return [[key, value as SafeValue]];
      }
      return [];
    }),
  );
}

function exceptionFrames(error: Error): Array<{
  file: string;
  function: string | null;
  line: number;
}> {
  return (error.stack?.split("\n").slice(1) ?? []).flatMap((frame) => {
    const match = frame.match(
      /^\s*at\s+(?:([^()]+)\s+\()?([^():]+):(\d+):\d+\)?$/,
    );
    if (!match) {
      return [];
    }
    return [
      {
        file: match[2]?.split("/").at(-1) ?? "unknown",
        function: match[1]?.trim() ?? null,
        line: Number(match[3]),
      },
    ];
  });
}

export function structuredLog(
  eventName: string,
  level: LogLevel,
  context: LogContext = {},
  exception?: unknown,
): void {
  try {
    const payload: Record<string, unknown> = {
      timestamp: new Date().toISOString(),
      level,
      service: process.env.OTEL_SERVICE_NAME ?? "rag-platform-web",
      environment: process.env.APP_ENV ?? process.env.NODE_ENV ?? "production",
      event_name: EVENT_NAME.test(eventName)
        ? eventName
        : "application.unclassified.v1",
      ...safeContext(context),
    };

    if (exception instanceof Error) {
      payload.exception_type = exception.name || "Error";
      const frames = exceptionFrames(exception);
      if (frames.length > 0) {
        payload.exception_frames = frames;
      }
    }

    process.stderr.write(`${JSON.stringify(payload)}\n`);
  } catch {
    // Observability is never allowed to fail the request it observes.
  }
}
