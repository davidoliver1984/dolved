<?php

declare(strict_types=1);

namespace App\Support\Conversation;

use App\Enums\ContextualisationStatus;
use InvalidArgumentException;

final readonly class ContextualisationResult
{
    /** @param array<string, mixed>|null $interpretationMetadata @param array<string, mixed>|null $usage */
    public function __construct(
        public ContextualisationStatus $status,
        public ?string $resolvedQuery,
        public bool $usedPriorContext,
        public ?array $interpretationMetadata,
        public ?string $clarificationQuestion,
        public string $contextualiserVersion,
        public ?array $usage,
    ) {}

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $keys = array_keys($value);
        sort($keys);
        $expected = ['clarification_question', 'contextualiser_version', 'interpretation_metadata', 'resolved_query', 'status', 'usage', 'used_prior_context'];
        sort($expected);
        if ($keys !== $expected) {
            throw new InvalidArgumentException('The contextualisation result has an invalid shape.');
        }
        $status = ContextualisationStatus::tryFrom((string) $value['status']);
        $query = $value['resolved_query'];
        $question = $value['clarification_question'];
        $metadata = $value['interpretation_metadata'];
        if ($status === null
            || ! is_bool($value['used_prior_context'])
            || ! is_string($value['contextualiser_version'])
            || trim($value['contextualiser_version']) === ''
            || ! self::validMetadata($metadata)
            || ($value['usage'] !== null && ! is_array($value['usage']))) {
            throw new InvalidArgumentException('The contextualisation result is invalid.');
        }
        if ($status === ContextualisationStatus::Resolved) {
            if (! is_string($query) || trim($query) === '' || mb_strlen($query) > 8_000 || $question !== null) {
                throw new InvalidArgumentException('A resolved contextualisation result requires only a resolved query.');
            }
            $query = trim($query);
        } else {
            if ($query !== null || ! is_string($question) || trim($question) === '' || mb_strlen($question) > (int) config('conversation.clarification_max_characters')) {
                throw new InvalidArgumentException('A clarification result requires only a bounded clarification question.');
            }
            $question = trim($question);
        }

        return new self($status, $query, $value['used_prior_context'], $metadata, $question, trim($value['contextualiser_version']), $value['usage']);
    }

    private static function validMetadata(mixed $metadata): bool
    {
        if ($metadata === null) {
            return true;
        }
        if (! is_array($metadata) || array_keys($metadata) !== ['used_turn_ordinals']) {
            return false;
        }
        $ordinals = $metadata['used_turn_ordinals'];
        if (! is_array($ordinals) || count($ordinals) > 3) {
            return false;
        }

        return array_is_list($ordinals)
            && count($ordinals) === count(array_unique($ordinals))
            && collect($ordinals)->every(fn (mixed $ordinal): bool => is_int($ordinal) && $ordinal > 0);
    }
}
