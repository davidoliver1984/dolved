<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Exceptions\ImportPromotionException;
use App\Models\ImportDecisionSnapshot;
use App\Models\ImportItem;
use App\Models\User;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use App\Support\Documents\SafeDocumentSourceUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateImportDecisionSnapshot
{
    public function __construct(private StructuredExtractionCanonicaliser $canonical) {}

    /** @param array<string, mixed> $definition */
    public function handle(ImportItem $item, User $actor, array $definition): ImportDecisionSnapshot
    {
        $this->validateShape($definition);
        $canonical = $this->canonical->canonicalBytes($definition);
        $digest = hash('sha256', $canonical);

        return DB::transaction(function () use ($item, $actor, $canonical, $digest): ImportDecisionSnapshot {
            $locked = ImportItem::query()->with('batch')->lockForUpdate()->findOrFail($item->id);
            if ($locked->preflight_status !== ImportPreflightStatus::Verified
                || $locked->match_status !== ImportMatchStatus::Resolved
                || $locked->batch->retention_expires_at->isPast()
                || ! $this->authorised($locked, $actor)) {
                throw ImportPromotionException::conflict('decision_not_permitted');
            }
            $snapshot = ImportDecisionSnapshot::query()->firstOrCreate(
                ['import_item_id' => $locked->id, 'digest_sha256' => $digest],
                [
                    'public_id' => (string) Str::uuid(),
                    'schema_version' => (int) config('imports.promotion.decision_schema_version'),
                    'canonical_definition' => $canonical,
                    'actor_user_id' => $actor->id,
                ],
            );
            if ($locked->current_decision_snapshot_id !== $snapshot->id) {
                $locked->current_decision_snapshot_id = $snapshot->id;
                $locked->save();
            }

            return $snapshot;
        });
    }

    /** @param array<string, mixed> $definition */
    private function validateShape(array $definition): void
    {
        $required = ['family', 'metadata', 'applicability', 'effective_from'];
        if (! $this->hasExactKeys($definition, $required)
            || ! is_array($definition['family'])
            || ! is_array($definition['metadata'])
            || ! is_array($definition['applicability'])
            || ! is_string($definition['effective_from'])) {
            throw ImportPromotionException::invalid('invalid_decision_shape');
        }
        $family = $definition['family'];
        if (($family['mode'] ?? null) === 'new') {
            if (! $this->hasExactKeys($family, ['mode', 'title']) || ! is_string($family['title']) || trim($family['title']) === '') {
                throw ImportPromotionException::invalid('invalid_family_decision');
            }
        } elseif (($family['mode'] ?? null) === 'successor') {
            if (! $this->hasExactKeys($family, ['mode', 'family_public_id'])
                || ! is_string($family['family_public_id'])
                || ! Str::isUuid($family['family_public_id'])) {
                throw ImportPromotionException::invalid('invalid_family_decision');
            }
        } else {
            throw ImportPromotionException::invalid('invalid_family_decision');
        }
        $metadataKeys = ['category_public_id', 'description', 'owner_user_public_id', 'publisher_label', 'review_due_date', 'source_url', 'tag_public_ids'];
        if (! $this->hasExactKeys($definition['metadata'], $metadataKeys)
            || ! $this->nullableString($definition['metadata']['category_public_id'])
            || ! $this->nullableString($definition['metadata']['description'])
            || ! is_string($definition['metadata']['owner_user_public_id'])
            || ! Str::isUuid($definition['metadata']['owner_user_public_id'])
            || ! $this->nullableString($definition['metadata']['publisher_label'])
            || ! $this->nullableString($definition['metadata']['review_due_date'])
            || ! $this->nullableString($definition['metadata']['source_url'])
            || ! is_array($definition['metadata']['tag_public_ids'])
            || ! array_is_list($definition['metadata']['tag_public_ids'])
            || ! $this->publicIdList($definition['metadata']['tag_public_ids'])
            || ! $this->hasExactKeys($definition['applicability'], ['location_public_ids'])
            || ! is_array($definition['applicability']['location_public_ids'])
            || ! array_is_list($definition['applicability']['location_public_ids'])
            || ! $this->publicIdList($definition['applicability']['location_public_ids'])
            || ! $this->validDate($definition['effective_from'])
            || ($definition['metadata']['review_due_date'] !== null
                && ! $this->validDate($definition['metadata']['review_due_date']))
            || ($definition['metadata']['source_url'] !== null
                && ! SafeDocumentSourceUrl::accepts($definition['metadata']['source_url']))) {
            throw ImportPromotionException::invalid('invalid_metadata_decision');
        }
        if ($definition['metadata']['category_public_id'] !== null
            && ! Str::isUuid($definition['metadata']['category_public_id'])) {
            throw ImportPromotionException::invalid('invalid_metadata_decision');
        }
    }

    /** @param array<string, mixed> $value @param list<string> $expected */
    private function hasExactKeys(array $value, array $expected): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }

    private function authorised(ImportItem $item, User $actor): bool
    {
        return $actor->workspaceMemberships()->where('workspace_id', $item->workspace_id)->exists();
    }

    private function nullableString(mixed $value): bool
    {
        return $value === null || is_string($value);
    }

    /** @param array<int, mixed> $values */
    private function publicIdList(array $values): bool
    {
        if (count($values) !== count(array_unique($values, SORT_REGULAR))) {
            return false;
        }
        foreach ($values as $value) {
            if (! is_string($value) || ! Str::isUuid($value)) {
                return false;
            }
        }

        return true;
    }

    private function validDate(string $value): bool
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)->format('Y-m-d') === $value;
        } catch (\Throwable) {
            return false;
        }
    }
}
