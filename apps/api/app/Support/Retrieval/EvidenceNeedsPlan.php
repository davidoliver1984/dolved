<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use App\Enums\RequestedEvidenceType;
use App\Enums\RetrievalTemporalMode;
use App\Enums\RetrievalTemporalReferenceKind;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;
use Opis\JsonSchema\Validator;

/** Interpretation only. Authority and applicability resolution remain application-owned. */
final readonly class EvidenceNeedsPlan
{
    private function __construct(
        public array $value,
        public ClassifierLineage $lineage,
        public PlannerUsage $usage,
    ) {}

    public static function fromArray(array $value, array $turns, array $lineage, array $usage): self
    {
        self::validate($value, $turns);
        $identity = ClassifierLineage::fromArray($lineage);
        $observation = PlannerUsage::fromArray($usage);
        if ($identity->contractSchemaVersion !== 'evidence-needs-plan-v2'
            || $identity->promptVersion !== 'bounded-evidence-needs-v10'
            || $identity->adapterVersion !== 'structured-chat-needs-v5'
            || $identity->provider !== $observation->provider
            || $identity->model !== $observation->model) {
            throw new InvalidArgumentException('Needs planner identity does not match its contract or usage.');
        }

        return new self($value, $identity, $observation);
    }

    /** A validated need becomes one production retrieval lane/pair, not a second planner. */
    public function retrievalPlan(string $needId): RetrievalPlan
    {
        $need = null;
        foreach ($this->value['needs'] as $item) {
            if ($item['need_id'] === $needId) {
                $need = $item;
                break;
            }
        }
        if ($need === null) {
            throw new InvalidArgumentException('Unknown evidence need.');
        }
        // Descriptive edges must never affect execution or generation input.
        unset($need['supports_need_id']);
        foreach ($this->value['bindings'] ?? [] as $binding) {
            if ($binding['need_id'] === $needId) {
                $need['subject_binding'] = $binding;
                break;
            }
        }
        $authority = $need['authority'];
        $compare = $need['operation'] === 'compare';
        $mode = $compare ? RetrievalTemporalMode::Compare : match ($authority['kind']) {
            'current' => RetrievalTemporalMode::Current,
            'date', 'period' => RetrievalTemporalMode::ValidAtDate,
            'previous', 'historical_reference' => RetrievalTemporalMode::HistoricalReference,
            default => throw new InvalidArgumentException('Unsupported retrieval authority.'),
        };
        $reference = match ($authority['kind']) {
            'period' => ['kind' => RetrievalTemporalReferenceKind::CalendarPeriod, 'value' => $authority['value']],
            'historical_reference' => ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => $authority['value']],
            'previous' => $compare ? null : ['kind' => RetrievalTemporalReferenceKind::HistoricalReference, 'value' => 'previous'],
            default => null,
        };

        return new RetrievalPlan(
            $need['aspect'], $mode,
            $authority['kind'] === 'date' ? CarbonImmutable::createFromFormat('!Y-m-d', $authority['value']) : null,
            $reference,
            $need['applicability']['kind'] === 'generic' ? [] : [$need['applicability']['source']['quote']],
            null, $this->lineage, $this->usage,
            factDate: $need['fact_reference']['value'] ?? null,
            requestedEvidenceType: $compare ? RequestedEvidenceType::CurrentVersusHistoricalComparison : RequestedEvidenceType::PolicyOrProceduralRequirements,
            evidenceNeed: $need,
        );
    }

    public static function validate(array $value, array $turns): void
    {
        self::validateWireShape($value, 'plan');
        $texts = [];
        foreach ($turns as $turn) {
            if (! is_array($turn) || count($turn) !== 2 || ! is_string($turn['turn_id'] ?? null)
                || trim($turn['turn_id']) === '' || ! is_string($turn['text'] ?? null)
                || trim($turn['text']) === '' || isset($texts[$turn['turn_id']])) {
                throw new InvalidArgumentException('Invalid authorised user turns.');
            }
            $texts[$turn['turn_id']] = $turn['text'];
        }
        if ($texts === []) {
            throw new InvalidArgumentException('Authorised user turns are required.');
        }
        if ($value['decision'] === 'clarify') {
            if ($value['needs'] !== [] || $value['clarification'] === null) {
                throw new InvalidArgumentException('Clarification needs a question and no needs.');
            }

            return;
        }
        $needs = $value['needs'];
        if ($needs === [] || $value['clarification'] !== null || ! in_array(true, array_column($needs, 'central'), true)) {
            throw new InvalidArgumentException('Plan requires central needs and no clarification.');
        }
        $edges = [];
        $turnOrder = array_flip(array_keys($texts));
        $previousPosition = [-1, -1];
        foreach ($needs as $index => $need) {
            if ($need['need_id'] !== 'n'.($index + 1)) {
                throw new InvalidArgumentException('Need identifiers must be unique and sequential.');
            }
            $edges[$need['need_id']] = $need['supports_need_id'];
            $authority = $need['authority'];
            if ($need['operation'] === 'compare' && $authority['kind'] === 'current') {
                throw new InvalidArgumentException('Comparison requires a historical or transition selector.');
            }
            if ($authority['kind'] === 'current') {
                if ($authority['value'] !== null || $authority['source'] !== null || $authority['precision'] !== 'none') {
                    throw new InvalidArgumentException('Current authority has no temporal selector.');
                }
            } else {
                $allowed = match ($authority['kind']) {
                    'date', 'transition_boundary' => ['day'],
                    'period', 'transition_period' => ['month', 'year', 'interval'],
                    'previous', 'historical_reference' => ['named'],
                };
                if ($authority['value'] === null || $authority['source'] === null || ! in_array($authority['precision'], $allowed, true)) {
                    throw new InvalidArgumentException('Authority requires a grounded, precision-consistent selector.');
                }
                self::dateValue($authority['value'], $authority['precision']);
                self::dateGrounding($authority['value'], $authority['precision'], $authority['source']['quote']);
                if ($authority['kind'] === 'historical_reference' && ! str_contains($authority['source']['quote'], $authority['value'])) {
                    throw new InvalidArgumentException('Named historical reference must retain its wording.');
                }
            }
            if (str_starts_with($authority['kind'], 'transition_') && $need['operation'] !== 'compare') {
                throw new InvalidArgumentException('Transition requires comparison.');
            }
            if ($need['applicability']['kind'] !== 'generic' && $need['applicability']['source'] === null) {
                throw new InvalidArgumentException('Specific applicability requires a reference.');
            }
            $spans = [$need['question_span'], $authority['source'], $need['applicability']['source']];
            if ($need['fact_reference'] !== null) {
                $fact = $need['fact_reference'];
                self::dateValue($fact['value'], $fact['precision']);
                self::dateGrounding($fact['value'], $fact['precision'], $fact['source']['quote']);
                $spans[] = $fact['source'];
            }
            foreach ($spans as $span) {
                if ($span !== null && (! isset($texts[$span['turn_id']]) || ! str_contains($texts[$span['turn_id']], $span['quote']))) {
                    throw new InvalidArgumentException('Reference is not grounded in an authorised user turn.');
                }
            }
            $questionSpan = $need['question_span'];
            $position = [$turnOrder[$questionSpan['turn_id']], strpos($texts[$questionSpan['turn_id']], $questionSpan['quote'])];
            if ($position < $previousPosition) {
                throw new InvalidArgumentException('Needs must retain user question order.');
            }
            $previousPosition = $position;
        }
        foreach (array_keys($edges) as $origin) {
            $seen = [];
            for ($node = $origin; $node !== null; $node = $edges[$node]) {
                if (! array_key_exists($node, $edges) || isset($seen[$node])) {
                    throw new InvalidArgumentException('Missing or cyclic support reference.');
                }
                $seen[$node] = true;
            }
        }
    }

    public static function validateCatalogue(array $value, array $catalogue, array $turns): void
    {
        $bindings = $value['bindings'] ?? [];
        if (array_column($bindings, 'need_id') !== array_column($value['needs'], 'need_id')) {
            throw new InvalidArgumentException('Every need requires one catalogue binding.');
        }
        $allowed = array_column($catalogue, 'family_id');
        $texts = array_column($turns, 'text', 'turn_id');
        foreach ($bindings as $index => $binding) {
            $ids = $binding['family_ids'];
            $matched = in_array($binding['subject_status'], ['matched', 'ambiguous'], true);
            if (count(array_unique($ids)) !== count($ids) || array_diff($ids, $allowed) !== []
                || ($ids !== []) !== $matched
                || ($binding['subject_status'] === 'ambiguous' && count($ids) < 2)) {
                throw new InvalidArgumentException('Invalid authorised catalogue binding.');
            }
            $source = $binding['ownership_source'];
            $external = $binding['ownership'] === 'external_tenant';
            if (($source !== null) !== $external || ($source !== null
                && (! isset($texts[$source['turn_id']]) || ! str_contains($texts[$source['turn_id']], $source['quote'])))
                || ($external && $value['needs'][$index]['applicability']['kind'] !== 'generic')) {
                throw new InvalidArgumentException('Ownership must be grounded and separate from location.');
            }
        }
    }

    public static function validateWireShape(array $value, string $name): void
    {
        if (! in_array($name, ['plan', 'request', 'response'], true)) {
            throw new InvalidArgumentException('Unknown needs contract.');
        }
        $schema = json_decode((string) file_get_contents('/contracts/http/retrieval-needs/v2/'.$name.'.schema.json'), flags: JSON_THROW_ON_ERROR);
        $data = json_decode(json_encode($value, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
        if (! (new Validator)->validate($data, $schema)->isValid()) {
            throw new InvalidArgumentException('Invalid evidence needs shape.');
        }
    }

    private static function dateGrounding(string $value, string $precision, string $quote): void
    {
        if ($precision === 'interval') {
            foreach (explode('/', $value) as $endpoint) {
                self::dateGrounding($endpoint, 'day', $quote);
            }

            return;
        }
        if ($precision === 'named' || preg_match('/(?<![0-9])'.preg_quote($value, '/').'(?![0-9])/', $quote) === 1) {
            return;
        }
        $parts = explode('-', $value);
        if (count($parts) < 2) {
            throw new InvalidArgumentException('Year is not supported by user quote.');
        }
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $month = $months[(int) $parts[1] - 1];
        $name = '(?:'.$month.'|'.substr($month, 0, 3).'\\.?)';
        if ($precision === 'month') {
            $pattern = '\\b'.$name.'\\s+'.$parts[0].'\\b';
        } else {
            $day = (int) $parts[2];
            $pattern = '\\b(?:0?'.$day.'(?:st|nd|rd|th)?\\s+'.$name.'\\s+'.$parts[0].'|'.$name.'\\s+0?'.$day.'(?:st|nd|rd|th)?,?\\s+'.$parts[0].')\\b';
        }
        if (preg_match('/'.$pattern.'/i', $quote) !== 1) {
            throw new InvalidArgumentException('Calendar value or precision is not supported by user quote.');
        }
    }

    private static function dateValue(string $value, string $precision): void
    {
        if ($precision === 'named') {
            return;
        }
        if ($precision === 'interval') {
            $parts = explode('/', $value);
            if (count($parts) !== 2 || $parts[0] > $parts[1]) {
                throw new InvalidArgumentException('Invalid date interval.');
            }
            foreach ($parts as $part) {
                self::dateValue($part, 'day');
            }

            return;
        }
        $format = match ($precision) {
            'day' => 'Y-m-d', 'month' => 'Y-m', 'year' => 'Y'
        };
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
        if ($date === false || $date->format($format) !== $value || (int) $date->format('Y') < 1) {
            throw new InvalidArgumentException('Date does not match its precision.');
        }
    }
}
