<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use Illuminate\Support\Str;

/** ADR-0039: metadata evidence, never an operational instruction or a chunk. */
final readonly class ApplicabilityScopeFact
{
    public const VERSION = 'applicability-scope-fact-v1';

    public const BREADTH_VERSION = 'applicability-scope-breadth-fact-v1';

    public function __construct(public array $value)
    {
        $breadth = ($value['representation'] ?? null) === self::BREADTH_VERSION;
        if (! $breadth && ($value['representation'] ?? null) !== self::VERSION) {
            throw new \InvalidArgumentException('Invalid resolved applicability scope fact.');
        }
        if (
            ($breadth && (($value['result'] ?? null) !== 'recorded_scope'
                || ($value['basis'] ?? null) !== 'recorded_breadth'
                || ! is_array($value['recorded_locations'] ?? null)))
            || (! $breadth && (! in_array($value['basis'] ?? null, ['universal', 'direct_match', 'inherited_match', 'outside_recorded_scope'], true)
                || ($value['result'] ?? null) !== ($value['basis'] === 'outside_recorded_scope' ? 'does_not_apply' : 'applies')))
            || ! in_array($value['need_id'] ?? null, ['n1', 'n2', 'n3'], true)
            || ! is_string($value['policy_title'] ?? null)
            || (! $breadth && ! is_string($value['location_name'] ?? null))
            || ! is_int($value['snapshot']['snapshot_id'] ?? null)
            || ! is_string($value['snapshot_digest'] ?? null) || strlen($value['snapshot_digest']) !== 64
            || ($value['citation_type'] ?? null) !== 'applicability_snapshot') {
            throw new \InvalidArgumentException('Invalid resolved applicability scope fact.');
        }
        foreach (['workspace_id', 'family_id', 'document_version_id', 'corpus_id'] as $key) {
            if (! is_string($value[$key] ?? null) || ! Str::isUuid($value[$key])) {
                throw new \InvalidArgumentException('Invalid scope fact identity.');
            }
        }
        if (! $breadth && (! is_string($value['location_id'] ?? null) || ! Str::isUuid($value['location_id']))) {
            throw new \InvalidArgumentException('Invalid scope fact location.');
        }
        if ($breadth) {
            $ids = [];
            foreach ($value['recorded_locations'] as $entry) {
                if (! is_array($entry) || ! is_string($entry['location_id'] ?? null)
                    || ! Str::isUuid($entry['location_id']) || ! is_string($entry['name'] ?? null)
                    || ! is_string($entry['kind'] ?? null) || ! is_array($entry['ancestor_ids'] ?? null)) {
                    throw new \InvalidArgumentException('Invalid recorded scope location.');
                }
                $ids[] = $entry['location_id'];
            }
            if (count($ids) !== count(array_unique($ids)) || $ids !== ($value['snapshot']['location_ids'] ?? null)) {
                throw new \InvalidArgumentException('Recorded scope locations differ from the snapshot.');
            }
        }
    }

    public function renderedText(): string
    {
        // Quote and escape registry labels as data, not executable Markdown/HTML.
        $label = static fn (string $text): string => str_replace(
            ['[', ']', '*', '_', '`', '<', '>'], ['\\[', '\\]', '\\*', '\\_', '\\`', '&lt;', '&gt;'],
            json_encode($text, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $policy = $label($this->value['policy_title']);
        if ($this->value['representation'] === self::BREADTH_VERSION) {
            $names = array_map(fn (array $entry): string => $label($entry['name']), $this->value['recorded_locations']);
            $statement = $this->value['snapshot']['scope'] === 'universal'
                ? "The recorded scope of {$policy} is universal."
                : "The recorded scope of {$policy} lists ".implode(', ', $names).'.';
            if (in_array('region', array_column($this->value['recorded_locations'], 'kind'), true)) {
                $statement .= ' A recorded region entry also covers its registered descendant locations.';
            }
        } else {
            $location = $label($this->value['location_name']);
            $statement = match ($this->value['result']) {
                'applies' => "The recorded scope of {$policy} includes {$location}.",
                'does_not_apply' => "The recorded scope of {$policy} does not include {$location}.",
                default => "The recorded scope of {$policy} could not be resolved for {$location}.",
            };
        }

        return $statement.' This is a statement of recorded policy applicability only; it does not establish what action is safe or required. The reason for this scope is not established by the scope record.';
    }
}
