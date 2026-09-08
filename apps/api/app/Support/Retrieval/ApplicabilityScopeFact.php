<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use Illuminate\Support\Str;

/** ADR-0039: metadata evidence, never an operational instruction or a chunk. */
final readonly class ApplicabilityScopeFact
{
    public const VERSION = 'applicability-scope-fact-v1';

    public function __construct(public array $value)
    {
        if (($value['representation'] ?? null) !== self::VERSION
            || ! in_array($value['need_id'] ?? null, ['n1', 'n2', 'n3'], true)
            || ! in_array($value['basis'] ?? null, ['universal', 'direct_match', 'inherited_match', 'outside_recorded_scope'], true)
            || ($value['result'] ?? null) !== ($value['basis'] === 'outside_recorded_scope' ? 'does_not_apply' : 'applies')
            || ! is_string($value['policy_title'] ?? null) || ! is_string($value['location_name'] ?? null)
            || ! is_int($value['snapshot']['snapshot_id'] ?? null)
            || ! is_string($value['snapshot_digest'] ?? null) || strlen($value['snapshot_digest']) !== 64
            || ($value['citation_type'] ?? null) !== 'applicability_snapshot') {
            throw new \InvalidArgumentException('Invalid resolved applicability scope fact.');
        }
        foreach (['workspace_id', 'family_id', 'document_version_id', 'location_id', 'corpus_id'] as $key) {
            if (! is_string($value[$key] ?? null) || ! Str::isUuid($value[$key])) {
                throw new \InvalidArgumentException('Invalid scope fact identity.');
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
        $location = $label($this->value['location_name']);
        $statement = match ($this->value['result']) {
            'applies' => "The recorded scope of {$policy} includes {$location}.",
            'does_not_apply' => "The recorded scope of {$policy} does not include {$location}.",
            default => "The recorded scope of {$policy} could not be resolved for {$location}.",
        };

        return $statement.' This is a statement of recorded policy applicability only; it does not establish what action is safe or required. The reason for this scope is not established by the scope record.';
    }
}
