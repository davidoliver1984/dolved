<?php

namespace App\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use OpenTelemetry\API\Trace\Span;
use Throwable;

final class PrivacySafeJsonFormatter implements FormatterInterface
{
    /** @var list<string> */
    private const SAFE_CONTEXT_FIELDS = [
        'attempt_count',
        'byte_count',
        'claim_outcome',
        'conversation_id',
        'correlation_id',
        'document_id',
        'document_status',
        'duration_ms',
        'duration_seconds',
        'event_id',
        'event_version',
        'failure_class',
        'failure_stage',
        'invitation_id',
        'key_id',
        'operation_kind',
        'outbox_marked_published',
        'outcome',
        'request_id',
        'requested_range',
        'result_status',
        'retry_delay_seconds',
        'run_id',
        'transport_message_id',
        'verification_outcome',
        'workspace_id',
    ];

    public function __construct(
        private readonly string $serviceName = 'rag-platform-api',
        private readonly string $environment = 'production',
    ) {}

    public function format(LogRecord $record): string
    {
        try {
            $eventName = $record->context['event_name'] ?? 'application.unclassified.v1';
            if (! is_string($eventName) || preg_match('/^[a-z][a-z0-9_.-]*\.v[1-9][0-9]*$/', $eventName) !== 1) {
                $eventName = 'application.unclassified.v1';
            }

            $payload = [
                'timestamp' => $record->datetime->format(DATE_ATOM),
                'level' => strtolower($record->level->getName()),
                'service' => $this->serviceName,
                'environment' => $this->environment,
                'event_name' => $eventName,
            ];

            $spanContext = Span::getCurrent()->getContext();
            if ($spanContext->isValid()) {
                $payload['trace_id'] = $spanContext->getTraceId();
                $payload['span_id'] = $spanContext->getSpanId();
            }

            foreach (self::SAFE_CONTEXT_FIELDS as $field) {
                if (! array_key_exists($field, $record->context)) {
                    continue;
                }

                $value = $record->context[$field];
                if (is_null($value) || is_scalar($value)) {
                    $payload[$field] = $value;
                }
            }

            $exception = $record->context['exception'] ?? null;
            if ($exception instanceof Throwable) {
                $payload['exception_type'] = $exception::class;
                $payload['exception_frames'] = $this->safeFrames($exception);
            } elseif (isset($record->context['error_type']) && is_string($record->context['error_type'])) {
                $payload['exception_type'] = $record->context['error_type'];
            }

            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ).PHP_EOL;
        } catch (Throwable) {
            return '{"event_name":"logging.format_failed.v1","level":"error"}'.PHP_EOL;
        }
    }

    /** @param array<LogRecord> $records */
    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }

    /** @return list<array{file: string|null, function: string|null, line: int|null}> */
    private function safeFrames(Throwable $exception): array
    {
        return array_map(
            static fn (array $frame): array => [
                'file' => isset($frame['file']) ? basename((string) $frame['file']) : null,
                'function' => isset($frame['function']) ? (string) $frame['function'] : null,
                'line' => isset($frame['line']) ? (int) $frame['line'] : null,
            ],
            $exception->getTrace(),
        );
    }
}
