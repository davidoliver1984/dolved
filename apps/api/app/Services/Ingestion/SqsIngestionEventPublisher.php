<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\IngestionEventPublisher;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\SqsQueue;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use RuntimeException;
use Throwable;

class SqsIngestionEventPublisher implements IngestionEventPublisher
{
    public function __construct(
        private readonly QueueFactory $queues,
    ) {}

    public function publish(array $payload): string
    {
        $connection = $this->queues->connection('sqs');

        if (! $connection instanceof SqsQueue) {
            throw new RuntimeException(
                'The ingestion publisher requires an SQS queue connection.',
            );
        }

        $messageAttributes = [];

        try {
            TraceContextPropagator::getInstance()->inject($messageAttributes);
        } catch (Throwable) {
            // Publication must continue if trace-context injection fails.
            $messageAttributes = [];
        }

        $sqsAttributes = [];

        foreach ($messageAttributes as $name => $value) {
            $sqsAttributes[$name] = [
                'DataType' => 'String',
                'StringValue' => $value,
            ];
        }

        $options = $sqsAttributes === []
            ? []
            : ['MessageAttributes' => $sqsAttributes];
        $messageId = $connection->pushRaw(
            json_encode($payload, JSON_THROW_ON_ERROR),
            null,
            $options,
        );

        if (! is_string($messageId) || $messageId === '') {
            throw new RuntimeException(
                'SQS did not confirm ingestion event publication.',
            );
        }

        return $messageId;
    }
}
