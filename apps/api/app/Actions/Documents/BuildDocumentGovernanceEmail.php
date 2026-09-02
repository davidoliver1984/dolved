<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Contracts\Documents\ResolveDocumentGovernanceEmailBranding;
use App\Data\Documents\ResolvedGovernanceEmailBranding;
use App\Enums\DocumentGovernanceEventKey;
use App\Mail\DocumentGovernanceMail;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceNotification;
use App\Models\Workspace;
use App\Support\Documents\GovernanceEmailCategories;
use App\Support\Documents\ResolveDocumentGovernanceNotificationRoute;
use LogicException;

final readonly class BuildDocumentGovernanceEmail
{
    public function __construct(
        private ResolveDocumentGovernanceNotificationRoute $resolveRoute,
        private ResolveDocumentGovernanceEmailBranding $resolveBranding,
    ) {}

    public function handle(DocumentGovernanceEmailEnvelope $envelope): DocumentGovernanceMail
    {
        $this->validateSealedIdentity($envelope);
        $workspace = Workspace::query()->findOrFail($envelope->workspace_id);
        $members = $envelope->members()->with(['notification', 'decision'])->orderBy('ordinal')->get();
        $included = $members->filter(fn ($member): bool => $member->decision?->decision === 'included');
        if ($included->isEmpty() || $included->contains(fn ($member): bool => $member->notification === null)) {
            throw new LogicException('A governance email requires every included notification to remain available at dispatch time.');
        }

        $items = $included->map(fn ($member): array => $this->safeCopy($member->notification))->values()->all();
        $digest = $envelope->template_key === 'governance.review.digest';
        $firstNotification = $included->first()->notification;
        $route = $digest
            ? "/app/workspaces/{$workspace->public_id}/documents/attention"
            : $this->resolveRoute->handle($firstNotification, $workspace);
        if ($route === null) {
            throw new LogicException('The governance email action route is unavailable or is not allowlisted.');
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $heading = $digest ? 'Document reviews need attention' : $items[0]['title'];
        $summary = $digest
            ? 'Review the document governance reminders waiting in your workspace.'
            : $items[0]['message'];
        $branding = $this->safeBranding($this->resolveBranding->resolve(
            (string) $envelope->branding_configuration_identity,
            (string) $envelope->resolved_accent_identity,
        ));

        return new DocumentGovernanceMail(
            mailSubject: "{$heading} · {$envelope->workspace_display_name_snapshot}",
            heading: $heading,
            summary: $summary,
            workspaceName: (string) $envelope->workspace_display_name_snapshot,
            actionLabel: $digest ? 'Review governance work' : 'Open in Dolved',
            actionUrl: $frontend.$route,
            preferenceUrl: $frontend."/app/workspaces/{$workspace->public_id}/settings/notifications",
            items: $digest ? $items : [],
            idempotencyKey: $envelope->envelope_key,
            brandName: $branding->brandName,
            accentColour: $branding->accentColour,
            logoUrl: $branding->logoUrl,
        );
    }

    private function validateSealedIdentity(DocumentGovernanceEmailEnvelope $envelope): void
    {
        if ($envelope->template_version !== 1
            || $envelope->branding_configuration_identity === null
            || $envelope->resolved_accent_identity === null
            || $envelope->workspace_display_name_snapshot === null
            || $envelope->sealed_rendering_basis_digest === null) {
            throw new LogicException('The governance email rendering identity is unsupported or incomplete.');
        }

        if ($envelope->template_key === 'governance.review.digest') {
            if ($envelope->category_group !== 'review_reminders') {
                throw new LogicException('The governance digest template is incompatible with its category.');
            }

            return;
        }

        $notification = $envelope->members()->with('notification')->first()?->notification;
        if (! $notification instanceof DocumentGovernanceNotification
            || $envelope->template_key !== $notification->event_key->value
            || GovernanceEmailCategories::group($notification->event_key) !== $envelope->category_group) {
            throw new LogicException('The immediate governance email template is incompatible with its notification.');
        }
    }

    private function safeBranding(ResolvedGovernanceEmailBranding $branding): ResolvedGovernanceEmailBranding
    {
        $logoValid = $branding->logoUrl === null
            || filter_var($branding->logoUrl, FILTER_VALIDATE_URL) !== false
                && parse_url($branding->logoUrl, PHP_URL_SCHEME) === 'https';
        if (! $logoValid || trim($branding->brandName) === '' || mb_strlen($branding->brandName) > 100) {
            return $this->dolvedBranding();
        }

        $accent = strtoupper($branding->accentColour);
        if (! preg_match('/^#[0-9A-F]{6}$/', $accent) || $this->contrastAgainstWhite($accent) < 4.5) {
            $accent = '#008466';
        }

        return new ResolvedGovernanceEmailBranding(
            brandName: $branding->brandName,
            accentColour: $accent,
            logoUrl: $branding->logoUrl,
        );
    }

    private function dolvedBranding(): ResolvedGovernanceEmailBranding
    {
        return new ResolvedGovernanceEmailBranding('Dolved', '#008466');
    }

    private function contrastAgainstWhite(string $hex): float
    {
        $components = [substr($hex, 1, 2), substr($hex, 3, 2), substr($hex, 5, 2)];
        $linear = array_map(function (string $component): float {
            $value = hexdec($component) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $components);
        $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];

        return 1.05 / ($luminance + 0.05);
    }

    /** @return array{title: string, message: string} */
    private function safeCopy(DocumentGovernanceNotification $notification): array
    {
        return match ($notification->event_key) {
            DocumentGovernanceEventKey::ImportBatchCompleted => $this->copy('Import complete', 'Your document import finished.'),
            DocumentGovernanceEventKey::ImportBatchCompletedWithExceptions => $this->copy('Import needs attention', 'Your import finished with items that need attention.'),
            DocumentGovernanceEventKey::ImportItemProcessingFailed => $this->copy('Document could not be processed', 'Review the staged document and choose what to do next.'),
            DocumentGovernanceEventKey::ImportItemRequiresUserAction => $this->copy('Document needs your input', 'Review the staged document before it can continue.'),
            DocumentGovernanceEventKey::ImportItemMatchAmbiguous => $this->copy('Document match needs review', 'Choose the correct existing document family or create a new one.'),
            DocumentGovernanceEventKey::GovernanceVersionApproved => $this->copy('Document version approved', 'A document version was approved.'),
            DocumentGovernanceEventKey::PromotionCompleted => $this->copy('Document added to the library', 'A staged document was promoted successfully.'),
            DocumentGovernanceEventKey::PromotionFailed => $this->copy('Document promotion failed', 'A staged document could not be promoted.'),
            DocumentGovernanceEventKey::GovernanceReviewDueSoon => $this->copy('Review due soon', 'A document family is approaching its review date.'),
            DocumentGovernanceEventKey::GovernanceReviewOverdue => $this->copy('Review overdue', 'A document family has passed its review date.'),
            DocumentGovernanceEventKey::GovernanceOwnershipReassignmentRequired => $this->copy('Document owner required', 'Assign an eligible owner to a document family.'),
            DocumentGovernanceEventKey::GovernanceAuthorityBlocked => $this->copy('Scheduled authority blocked', 'A scheduled document version cannot attain authority.'),
            DocumentGovernanceEventKey::GovernanceAuthorityApproaching => $this->copy('Scheduled change approaching', 'A document version is approaching its authority date.'),
            DocumentGovernanceEventKey::GovernanceAuthorityAttained => $this->copy('Scheduled change active', 'A document version has attained authority.'),
            DocumentGovernanceEventKey::ApplicabilitySuccessorCompleted => $this->copy('Applicability update complete', 'A successor document was prepared successfully.'),
            DocumentGovernanceEventKey::ApplicabilitySuccessorFailed => $this->copy('Applicability update failed', 'A successor document could not be prepared.'),
            DocumentGovernanceEventKey::BulkOperationCompleted => $this->copy('Bulk operation complete', 'The bulk document operation completed.'),
            DocumentGovernanceEventKey::BulkOperationCompletedWithExceptions => $this->copy('Bulk operation completed with exceptions', 'Review the items that could not be changed.'),
            DocumentGovernanceEventKey::BulkOperationFailedBeforeExecution => $this->copy('Bulk operation did not start', 'The bulk document operation failed before execution.'),
            DocumentGovernanceEventKey::DeletionOperationStuckOrFailed => $this->copy('Document deletion needs attention', 'A deletion operation did not complete normally.'),
        };
    }

    /** @return array{title: string, message: string} */
    private function copy(string $title, string $message): array
    {
        return ['title' => $title, 'message' => $message];
    }
}
