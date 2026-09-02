<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\AssembleDocumentGovernanceEmailEnvelope;
use App\Actions\Documents\ClaimDocumentGovernanceEmailEnvelope;
use App\Actions\Documents\CompleteDocumentGovernanceEmailAttempt;
use App\Actions\Documents\ReclaimExpiredDocumentGovernanceEmailAttempts;
use App\Actions\Documents\ScanDocumentGovernanceRemindersAndAuthorityTransitions;
use App\Actions\Documents\SealDocumentGovernanceEmailEnvelope;
use App\Actions\Documents\VerifyDocumentGovernanceEmailAttempt;
use App\Enums\DocumentGovernanceEventKey;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Enums\GovernanceEmailAttemptStatus;
use App\Enums\GovernanceEmailEnvelopeStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\DocumentGovernanceEmailEnvelope;
use App\Models\DocumentGovernanceEmailEnvelopeAttempt;
use App\Models\DocumentGovernanceNotification;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceNotificationSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentGovernanceEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_immediate_and_digest_envelopes_have_deterministic_membership_and_sealing(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $assemble = app(AssembleDocumentGovernanceEmailEnvelope::class);
        $immediate = $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));
        $this->assertSame(GovernanceEmailEnvelopeStatus::Ready, $immediate?->assembly_status);
        $this->assertNotNull($immediate?->sealed_rendering_basis_digest);
        $this->assertCount(1, $immediate?->members ?? []);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC'));
        $first = $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::GovernanceReviewDueSoon));
        $second = $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::GovernanceReviewOverdue));
        $this->assertSame($first?->id, $second?->id);
        $this->assertSame(GovernanceEmailEnvelopeStatus::Assembling, $first?->assembly_status);
        $sealed = app(SealDocumentGovernanceEmailEnvelope::class)->handle($first->id);
        $this->assertSame(GovernanceEmailEnvelopeStatus::Ready, $sealed?->assembly_status);
        $this->assertSame([1, 2], $sealed?->members()->pluck('ordinal')->all());

        $late = $assemble->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::GovernanceReviewDueSoon));
        $this->assertNotSame($sealed?->id, $late?->id);
        $this->assertSame('2026-09-03', $late?->digest_date?->toDateString());
    }

    public function test_workspace_gate_suppresses_without_consuming_an_attempt(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        WorkspaceNotificationSetting::query()->create([
            'workspace_id' => $workspace->id,
            'email_delivery_enabled' => false,
            'default_email_enabled' => true,
        ]);
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)
            ->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));

        $this->assertNull(app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id));
        $this->assertSame(GovernanceEmailEnvelopeStatus::Suppressed, $envelope->refresh()->assembly_status);
        $this->assertSame('no_deliverable_members', $envelope->suppression_reason);
        $this->assertDatabaseCount('document_governance_email_envelope_attempts', 0);
        $this->assertDatabaseHas('document_governance_email_envelope_member_decisions', [
            'decision' => 'suppressed',
            'suppression_reason' => 'workspace_email_disabled',
        ]);
    }

    public function test_claim_verification_and_acceptance_are_fenced_and_idempotent(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)
            ->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));
        $claim = app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id);
        $attempt = $claim['attempt'];
        $verified = app(VerifyDocumentGovernanceEmailAttempt::class)
            ->handle($attempt->id, $attempt->attempt_token, $attempt->generation);
        $this->assertNotNull($verified);

        $complete = app(CompleteDocumentGovernanceEmailAttempt::class);
        $this->assertTrue($complete->handle($attempt->id, $attempt->attempt_token, 1, 'provider-1'));
        $this->assertFalse($complete->handle($attempt->id, $attempt->attempt_token, 1, 'provider-1'));
        $this->assertSame(GovernanceEmailEnvelopeStatus::Sent, $envelope->refresh()->assembly_status);
        $this->assertSame(GovernanceEmailAttemptStatus::Accepted, $attempt->refresh()->status);
    }

    public function test_expired_attempt_is_abandoned_and_envelope_becomes_retryable(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)
            ->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));
        $attempt = app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id)['attempt'];
        DocumentGovernanceEmailEnvelopeAttempt::query()->whereKey($attempt->id)->update(['lease_expires_at' => now()->subSecond()]);

        $this->assertSame(1, app(ReclaimExpiredDocumentGovernanceEmailAttempts::class)->handle());
        $this->assertSame(GovernanceEmailAttemptStatus::Abandoned, $attempt->refresh()->status);
        $this->assertSame(GovernanceEmailEnvelopeStatus::Ready, $envelope->refresh()->assembly_status);
        $this->assertSame(2, app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id)['attempt']->generation);
    }

    public function test_digest_tampering_fails_closed_before_any_provider_boundary(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $envelope = app(AssembleDocumentGovernanceEmailEnvelope::class)
            ->handle($this->notification($owner, $workspace, DocumentGovernanceEventKey::ImportBatchCompleted));
        $attempt = app(ClaimDocumentGovernanceEmailEnvelope::class)->handle($envelope->id)['attempt'];
        DocumentGovernanceEmailEnvelope::query()->whereKey($envelope->id)->update([
            'dispatch_decision_digest' => str_repeat('0', 64),
        ]);

        $this->assertNull(app(VerifyDocumentGovernanceEmailAttempt::class)
            ->handle($attempt->id, $attempt->attempt_token, $attempt->generation));
        $this->assertSame(GovernanceEmailAttemptStatus::FailedPermanent, $attempt->refresh()->status);
        $this->assertSame(GovernanceEmailEnvelopeStatus::FailedPermanent, $envelope->refresh()->assembly_status);
        $this->assertSame('rendering_integrity_failure', $envelope->terminal_failure_category);
    }

    public function test_preferences_are_scoped_and_workspace_settings_require_an_administrator(): void
    {
        [$owner, $workspace] = $this->ownerWorkspace();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->member()->create();
        $url = "/api/workspaces/{$workspace->public_id}/governance-notification-preferences";

        $this->actingAs($member)->putJson($url.'/workspace', [
            'email_delivery_enabled' => false,
            'default_email_enabled' => false,
        ])->assertNotFound();
        $this->actingAs($owner)->putJson($url.'/workspace', [
            'email_delivery_enabled' => true,
            'default_email_enabled' => false,
        ])->assertOk()->assertJsonPath('data.default_email_enabled', false);
        $this->actingAs($member)->putJson($url.'/personal', [
            'category_group' => 'review_reminders',
            'email_enabled' => false,
        ])->assertOk();
        $this->actingAs($member)->getJson($url)->assertOk()
            ->assertJsonPath('data.workspace.can_manage', false)
            ->assertJsonPath('data.personal.0.category_group', 'review_reminders');
    }

    public function test_daily_scanner_uses_occurrence_identity_as_the_only_idempotency_authority(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->ownerWorkspace();
        $family = DocumentFamily::factory()->for($workspace)->create([
            'owner_user_id' => $owner->id,
            'review_due_date' => today()->addDays(7),
        ]);
        $blocked = Document::factory()->for($workspace)->for($family, 'family')->create([
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'approved_at' => now()->subDay(),
            'effective_from' => now()->addDay(),
        ]);
        Document::factory()->for($workspace)->for($family, 'family')->create([
            'status' => DocumentStatus::Indexed,
            'governance_status' => DocumentGovernanceStatus::Approved,
            'approved_at' => now()->subDays(2),
            'effective_from' => now()->subDays(2),
            'predecessor_document_id' => $blocked->id,
        ]);

        $scanner = app(ScanDocumentGovernanceRemindersAndAuthorityTransitions::class);
        $scanner->handle();
        $scanner->handle();
        $this->assertDatabaseCount('document_governance_events', 3);
        $this->assertDatabaseHas('document_governance_events', ['event_key' => DocumentGovernanceEventKey::GovernanceAuthorityBlocked->value]);
        $this->assertDatabaseHas('document_governance_events', ['event_key' => DocumentGovernanceEventKey::GovernanceAuthorityAttained->value]);
        $this->assertDatabaseMissing('document_governance_events', ['occurrence_key' => null]);
    }

    private function notification(User $recipient, Workspace $workspace, DocumentGovernanceEventKey $event): DocumentGovernanceNotification
    {
        $membership = WorkspaceMembership::query()->where('workspace_id', $workspace->id)->where('user_id', $recipient->id)->firstOrFail();

        return DocumentGovernanceNotification::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'recipient_user_id' => $recipient->id,
            'recipient_user_public_id' => $recipient->public_id,
            'recipient_workspace_membership_id' => $membership->id,
            'event_key' => $event,
            'source_event_id' => (string) Str::uuid(),
            'template_key' => $event->value,
            'template_version' => 1,
            'parameters' => [],
            'severity' => 'info',
            'expires_at' => now()->addDays(90),
        ]);
    }

    /** @return array{User, Workspace} */
    private function ownerWorkspace(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        return [$owner, Workspace::factory()->withOwner($owner)->create()];
    }
}
