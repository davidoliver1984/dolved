<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DocumentGovernanceEventKey;

final class GovernanceEmailCategories
{
    /** @return list<string> */
    public static function groups(): array
    {
        return array_values(array_unique(array_map(
            fn (DocumentGovernanceEventKey $key): string => self::group($key),
            array_filter(DocumentGovernanceEventKey::cases(), self::eligible(...)),
        )));
    }

    public static function eligible(DocumentGovernanceEventKey $key): bool
    {
        return ! in_array($key, [
            DocumentGovernanceEventKey::ImportItemProcessingFailed,
            DocumentGovernanceEventKey::ImportItemRequiresUserAction,
            DocumentGovernanceEventKey::ImportItemMatchAmbiguous,
            DocumentGovernanceEventKey::GovernanceAuthorityApproaching,
            DocumentGovernanceEventKey::GovernanceAuthorityAttained,
        ], true);
    }

    public static function group(DocumentGovernanceEventKey $key): string
    {
        return in_array($key, [
            DocumentGovernanceEventKey::GovernanceReviewDueSoon,
            DocumentGovernanceEventKey::GovernanceReviewOverdue,
        ], true) ? 'review_reminders' : $key->value;
    }

    public static function digest(DocumentGovernanceEventKey $key): bool
    {
        return self::group($key) === 'review_reminders';
    }
}
