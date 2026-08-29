<?php

namespace Tests\Unit\Logging;

use App\Logging\FailureIsolatedStreamHandler;
use App\Logging\PrivacySafeJsonFormatter;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PrivacySafeJsonFormatterTest extends TestCase
{
    public function test_it_only_serializes_allowlisted_context(): void
    {
        $formatter = new PrivacySafeJsonFormatter('rag-platform-api', 'test');
        $encoded = $formatter->format(new LogRecord(
            new DateTimeImmutable,
            'test',
            Level::Info,
            'sensitive document text',
            [
                'event_name' => 'document.ingestion.claimed.v1',
                'correlation_id' => 'correlation-1',
                'document_id' => 'document-1',
                'result_status' => 206,
                'requested_range' => 'bytes=10-19',
                'byte_count' => 10,
                'prompt' => 'private prompt',
            ],
        ));

        $payload = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('document.ingestion.claimed.v1', $payload['event_name']);
        self::assertSame('correlation-1', $payload['correlation_id']);
        self::assertSame('document-1', $payload['document_id']);
        self::assertSame(206, $payload['result_status']);
        self::assertSame('bytes=10-19', $payload['requested_range']);
        self::assertSame(10, $payload['byte_count']);
        self::assertArrayNotHasKey('message', $payload);
        self::assertArrayNotHasKey('prompt', $payload);
        self::assertStringNotContainsString('sensitive', $encoded);
        self::assertStringNotContainsString('private prompt', $encoded);
    }

    public function test_it_sanitizes_throwables_without_messages_or_arguments(): void
    {
        $secret = 'private evidence value';
        $exception = new RuntimeException($secret);
        $formatter = new PrivacySafeJsonFormatter('rag-platform-api', 'test');
        $encoded = $formatter->format(new LogRecord(
            new DateTimeImmutable,
            'test',
            Level::Error,
            'untrusted '.$secret,
            [
                'event_name' => 'generation.failed.v1',
                'exception' => $exception,
            ],
        ));

        $payload = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(RuntimeException::class, $payload['exception_type']);
        self::assertArrayHasKey('exception_frames', $payload);
        self::assertStringNotContainsString($secret, $encoded);
    }

    public function test_stream_failures_are_isolated(): void
    {
        $handler = new FailureIsolatedStreamHandler('/definitely-unavailable/log/output.log');
        $record = new LogRecord(
            new DateTimeImmutable,
            'test',
            Level::Info,
            'ignored',
            ['event_name' => 'logging.test.v1'],
        );

        self::assertFalse($handler->handle($record));
    }
}
